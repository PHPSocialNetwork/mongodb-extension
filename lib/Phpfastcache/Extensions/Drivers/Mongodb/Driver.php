<?php

/**
 *
 * This file is part of Phpfastcache.
 *
 * @license MIT License (MIT)
 *
 * For full copyright and license information, please see the docs/CREDITS.txt and LICENCE files.
 *
 * @author Georges.L (Geolim4)  <contact@geolim4.com>
 * @author Contributors  https://github.com/PHPSocialNetwork/phpfastcache/graphs/contributors
 */

declare(strict_types=1);

namespace Phpfastcache\Extensions\Drivers\Mongodb;

use LogicException;
use MongoDB\BSON\Binary;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Database;
use MongoDB\Driver\Command;
use MongoDB\Driver\Exception\Exception as MongoDBException;
use MongoDB\Driver\Manager;
use Phpfastcache\Cluster\AggregatablePoolInterface;
use Phpfastcache\Core\Pool\ExtendedCacheItemPoolInterface;
use Phpfastcache\Core\Pool\TaggableCacheItemPoolTrait;
use Phpfastcache\Core\Item\ExtendedCacheItemInterface;
use Phpfastcache\Entities\DriverStatistic;
use Phpfastcache\Exceptions\PhpfastcacheDriverCheckException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;

/**
 * @property Client $instance Instance of driver service
 * @method Config getConfig()
 */
class Driver implements AggregatablePoolInterface
{
    use TaggableCacheItemPoolTrait;

    public const MONGODB_DEFAULT_DB_NAME = 'phpfastcache'; // Public because used in config

    public const MONGODB_INDEX_KEY = '_id';

    public Collection $collection;

    public Database $database;

    protected string $documentPrefix;

    /**
     * @return bool
     * @throws PhpfastcacheDriverCheckException
     */
    public function driverCheck(): bool
    {
        $mongodbExtensionExists = extension_loaded('mongodb');

        if (!$mongodbExtensionExists && extension_loaded('mongo')) {
            throw new PhpfastcacheDriverCheckException(
                'This driver is used to support the pecl MongoDb extension with mongo-php-library. Mongo extension is no longer supported',
            );
        }

        return $mongodbExtensionExists && class_exists(Collection::class);
    }

    /**
     * @return bool
     * @throws MongodbException
     * @throws LogicException
     */
    protected function driverConnect(): bool
    {
        $this->documentPrefix = $this->getConfig()->getDocumentPrefix();
        $timeout = $this->getConfig()->getTimeout() * 1000;
        $collectionName = $this->getConfig()->getCollectionName();
        $databaseName = $this->getConfig()->getDatabaseName();
        $driverOptions = $this->getConfig()->getDriverOptions();

        $this->instance = new Client($this->buildConnectionURI($databaseName), ['connectTimeoutMS' => $timeout], $driverOptions);
        $this->database = $this->instance->selectDatabase($databaseName);

        if (!$this->collectionExists($collectionName)) {
            $this->database->createCollection($collectionName);
            $this->database->selectCollection($collectionName)
                ->createIndex(
                    [self::DRIVER_KEY_WRAPPER_INDEX => 1],
                    ['unique' => true, 'name' => 'unique_key_index']
                );
            $this->database->selectCollection($collectionName)
                ->createIndex(
                    [self::DRIVER_EDATE_WRAPPER_INDEX => 1],
                    ['expireAfterSeconds' => 0,  'name' => 'auto_expire_index']
                );
        }

        $this->collection = $this->database->selectCollection($collectionName);

        return true;
    }

    /**
     * @param ExtendedCacheItemInterface $item
     * @return ?array<string, mixed>
     */
    protected function driverRead(ExtendedCacheItemInterface $item): ?array
    {
        $document = $this->getCollection()->findOne([self::MONGODB_INDEX_KEY => $this->getMongoDbItemKey($item)]);

        if ($document) {
            $return = [
                self::DRIVER_DATA_WRAPPER_INDEX => $this->unserialize($document[self::DRIVER_DATA_WRAPPER_INDEX]->getData()),
                self::DRIVER_TAGS_WRAPPER_INDEX => $document[self::DRIVER_TAGS_WRAPPER_INDEX]->jsonSerialize(),
                self::DRIVER_EDATE_WRAPPER_INDEX => $document[self::DRIVER_EDATE_WRAPPER_INDEX]->toDateTime(),
            ];

            if ($this->getConfig()->isItemDetailedDate()) {
                $return += [
                    self::DRIVER_MDATE_WRAPPER_INDEX => isset($document[self::DRIVER_MDATE_WRAPPER_INDEX])
                        ? $document[self::DRIVER_MDATE_WRAPPER_INDEX]->toDateTime()
                        : new \DateTime(),
                    self::DRIVER_CDATE_WRAPPER_INDEX => isset($document[self::DRIVER_CDATE_WRAPPER_INDEX])
                        ? $document[self::DRIVER_CDATE_WRAPPER_INDEX]->toDateTime()
                        : new \DateTime(),
                ];
            }

            return $return;
        }

        return null;
    }

    /**
     * @param ExtendedCacheItemInterface ...$items
     * @return array<array<string, mixed>>
     */
    protected function driverReadMultiple(ExtendedCacheItemInterface ...$items): array
    {
        $driverArrays = [];
        $keys = array_map(fn(ExtendedCacheItemInterface $item) => $this->getMongoDbItemKey($item), $items);
        $documents = $this->getCollection()->find([self::MONGODB_INDEX_KEY => ['$in' => array_values($keys)]]);

        foreach ($documents as $document) {
            $driverArray = [
                self::DRIVER_DATA_WRAPPER_INDEX => $this->unserialize($document[self::DRIVER_DATA_WRAPPER_INDEX]->getData()),
                self::DRIVER_TAGS_WRAPPER_INDEX => $document[self::DRIVER_TAGS_WRAPPER_INDEX]->jsonSerialize(),
                self::DRIVER_EDATE_WRAPPER_INDEX => $document[self::DRIVER_EDATE_WRAPPER_INDEX]->toDateTime(),
            ];
            if ($this->getConfig()->isItemDetailedDate()) {
                $driverArray[self::DRIVER_MDATE_WRAPPER_INDEX] = isset($document[self::DRIVER_MDATE_WRAPPER_INDEX])
                    ? $document[self::DRIVER_MDATE_WRAPPER_INDEX]->toDateTime()
                    : new \DateTime();
                $driverArray[self::DRIVER_CDATE_WRAPPER_INDEX] = isset($document[self::DRIVER_CDATE_WRAPPER_INDEX])
                    ? $document[self::DRIVER_MDATE_WRAPPER_INDEX]->toDateTime()
                    : new \DateTime();
            }
            $driverArrays[$document[self::DRIVER_KEY_WRAPPER_INDEX]] = $driverArray;
        }
        return $driverArrays;
    }

    /**
     * @return array<int, string>
     */
    protected function driverReadAllKeys(string $pattern = ''): iterable
    {
        $filters = ($pattern !== '' ? [self::DRIVER_KEY_WRAPPER_INDEX => ['$regex' => str_replace('*', '(.*)', $pattern)]] : []);
        $documents = $this->getCollection()->find(
            $filters,
            [
                'limit' => ExtendedCacheItemPoolInterface::MAX_ALL_KEYS_COUNT,
                'typeMap' => [
                    'document' => 'array',
                    'root' => 'array'
                ],
                'projection' => [
                    self::MONGODB_INDEX_KEY => 0,
                    self::DRIVER_KEY_WRAPPER_INDEX => 1,
                ]
            ]
        )->toArray();

        return array_column($documents, self::DRIVER_KEY_WRAPPER_INDEX);
    }

    /**
     * @param ExtendedCacheItemInterface $item
     * @return mixed
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     * @throws PhpfastcacheLogicException
     */
    protected function driverWrite(ExtendedCacheItemInterface $item): bool
    {

        try {
            $set = [
                self::DRIVER_KEY_WRAPPER_INDEX => $item->getKey(),
                self::DRIVER_DATA_WRAPPER_INDEX => new Binary($this->encode($item->_getData()), Binary::TYPE_GENERIC),
                self::DRIVER_TAGS_WRAPPER_INDEX => $item->getTags(),
                self::DRIVER_EDATE_WRAPPER_INDEX => new UTCDateTime($item->getExpirationDate()),
            ];

            if (!empty($this->getConfig()->isItemDetailedDate())) {
                $set += [
                    self::DRIVER_MDATE_WRAPPER_INDEX =>  new UTCDateTime($item->getModificationDate()),
                    self::DRIVER_CDATE_WRAPPER_INDEX =>  new UTCDateTime($item->getCreationDate()),
                ];
            }
            $result = $this->getCollection()->updateOne(
                [self::MONGODB_INDEX_KEY => $this->getMongoDbItemKey($item)],
                [
                    '$set' => $set,
                ],
                ['upsert' => true, 'multiple' => false]
            );
        } catch (MongoDBException $e) {
            throw new PhpfastcacheDriverException('Got an exception while trying to write data to MongoDB server: ' . $e->getMessage(), 0, $e);
        }

        return $result->isAcknowledged();
    }

    /**
     * @param string $key
     * @param string $encodedKey
     * @return bool
     */
    protected function driverDelete(string $key, string $encodedKey): bool
    {
        $deletionResult = $this->getCollection()->deleteOne([self::MONGODB_INDEX_KEY =>  $this->getMongoDbKey($encodedKey)]);

        return $deletionResult->isAcknowledged();
    }

    /**
     * @return bool
     * @throws PhpfastcacheDriverException
     */
    protected function driverClear(): bool
    {
        try {
            return $this->collection->deleteMany([])->isAcknowledged();
        } catch (MongoDBException $e) {
            throw new PhpfastcacheDriverException('Got error while trying to empty the collection: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @return DriverStatistic
     * @throws MongoDBException
     */
    public function getStats(): DriverStatistic
    {
        $serverStats = $this->instance->getManager()->executeCommand(
            $this->getConfig()->getDatabaseName(),
            new Command(
                [
                    'serverStatus' => 1,
                    'recordStats' => 0,
                    'repl' => 0,
                    'metrics' => 0,
                ]
            )
        )->toArray()[0];

        $collectionStats = $this->instance->getManager()->executeCommand(
            $this->getConfig()->getDatabaseName(),
            new Command(
                [
                    'collStats' => $this->getConfig()->getCollectionName(),
                    'verbose' => true,
                ]
            )
        )->toArray()[0];

        $arrayFilterRecursive = static function ($array, callable $callback = null) use (&$arrayFilterRecursive) {
            $array = $callback($array);

            if (\is_object($array) || \is_array($array)) {
                foreach ($array as &$value) {
                    $value = $arrayFilterRecursive($value, $callback);
                }
            }

            return $array;
        };

        $callback = static function ($item) {
            /**
             * Remove unserializable properties
             */
            if ($item instanceof UTCDateTime) {
                return (string)$item;
            }
            return $item;
        };

        $serverStats = $arrayFilterRecursive($serverStats, $callback);
        $collectionStats = $arrayFilterRecursive($collectionStats, $callback);

        return (new DriverStatistic())
            ->setInfo(
                sprintf(
                    'MongoDB version %s, client version %s, SDK version %s.  Uptime (in days): %s',
                    $serverStats->version,
                    phpversion('mongodb'),
                    \Composer\InstalledVersions::getVersion('mongodb/mongodb'),
                    round($serverStats->uptime / 86400, 2)
                )
            )
            ->setSize($collectionStats->size)
            ->setData(implode(', ', array_keys($this->itemInstances)))
            ->setRawData(
                [
                    'serverStatus' => $serverStats,
                    'collStats' => $collectionStats,
                ]
            );
    }

    /**
     * @return Collection
     */
    protected function getCollection(): Collection
    {
        return $this->collection;
    }

    /**
     * Builds the connection URI from the given parameters.
     *
     * @param string $databaseName
     * @return string The connection URI.
     */
    protected function buildConnectionURI(string $databaseName): string
    {
        $databaseName = \urlencode($databaseName);
        $servers = $this->getConfig()->getServers();
        $options = $this->getConfig()->getOptions();

        $protocol = $this->getConfig()->getProtocol();
        $host = $this->getConfig()->getHost();
        $port = $this->getConfig()->getPort();
        $username = $this->getConfig()->getUsername();
        $password = $this->getConfig()->getPassword();

        if (count($servers) > 0) {
            $host = array_reduce(
                $servers,
                static fn ($carry, $data) => $carry . ($carry === '' ? '' : ',') . $data['host'] . ':' . $data['port'],
                ''
            );
            $port = false;
        }

        return implode(
            '',
            [
                "{$protocol}://",
                $username ?: '',
                $password ? ":{$password}" : '',
                $username ? '@' : '',
                $host,
                $port !== false ? ":{$port}" : '',
                $databaseName ? "/{$databaseName}" : '',
                count($options) > 0 ? '?' . http_build_query($options) : '',
            ]
        );
    }

    protected function getMongoDbItemKey(ExtendedCacheItemInterface $item): string
    {
        return $this->getMongoDbKey($item->getEncodedKey());
    }

    protected function getMongoDbKey(string $encodedKey): string
    {
        return $this->documentPrefix . $encodedKey;
    }

    /**
     * Checks if a collection name exists on the Mongo database.
     *
     * @param string $collectionName The collection name to check.
     *
     * @return bool True if the collection exists, false if not.
     */
    protected function collectionExists(string $collectionName): bool
    {
        foreach ($this->database->listCollections() as $collection) {
            if ($collection->getName() === $collectionName) {
                return true;
            }
        }

        return false;
    }
}
