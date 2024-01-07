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

use Phpfastcache\Config\ConfigurationOption;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;

class Config extends ConfigurationOption
{
    protected string $host = '127.0.0.1';
    protected int $port = 27017;
    protected int $timeout = 3;
    protected string $username = '';
    protected string $password = '';
    protected string $collectionName = 'phpfastcache';
    protected string $databaseName = Driver::MONGODB_DEFAULT_DB_NAME;
    protected string $protocol = 'mongodb';
    protected string $documentPrefix = 'pfc_';

    /** @var array<mixed>  */
    protected array $servers = [];

    /** @var array<mixed>  */
    protected array $options = [];

    /** @var array<mixed>  */
    protected array $driverOptions = [];

    /**
     * @return string
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * @param string $host
     * @return self
     * @throws PhpfastcacheLogicException
     */
    public function setHost(string $host): static
    {
        return $this->setProperty('host', $host);
    }

    /**
     * @return int
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * @param int $port
     * @return self
     * @throws PhpfastcacheLogicException
     */
    public function setPort(int $port): static
    {
        return $this->setProperty('port', $port);
    }

    /**
     * @return int
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * @param int $timeout
     * @return self
     * @throws PhpfastcacheLogicException
     */
    public function setTimeout(int $timeout): static
    {
        return $this->setProperty('timeout', $timeout);
    }

    /**
     * @return string
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * @param string $username
     * @return self
     * @throws PhpfastcacheLogicException
     */
    public function setUsername(string $username): static
    {
        return $this->setProperty('username', $username);
    }

    /**
     * @return string
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * @param string $password
     * @return self
     * @throws PhpfastcacheLogicException
     */
    public function setPassword(string $password): static
    {
        return $this->setProperty('password', $password);
    }

    /**
     * @return array<mixed>
     */
    public function getServers(): array
    {
        return $this->servers;
    }

    /**
     * @param array<mixed> $servers
     * @return self
     * @throws PhpfastcacheLogicException
     */
    public function setServers(array $servers): static
    {
        return $this->setProperty('servers', $servers);
    }

    /**
     * @return string
     */
    public function getCollectionName(): string
    {
        return $this->collectionName;
    }

    /**
     * @param string $collectionName
     * @return self
     * @throws PhpfastcacheLogicException
     */
    public function setCollectionName(string $collectionName): static
    {
        return $this->setProperty('collectionName', $collectionName);
    }

    /**
     * @return string
     */
    public function getDatabaseName(): string
    {
        return $this->databaseName;
    }

    /**
     * @param string $databaseName
     * @return self
     * @throws PhpfastcacheLogicException
     */
    public function setDatabaseName(string $databaseName): static
    {
        return $this->setProperty('databaseName', $databaseName);
    }

    /**
     * @return array<mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @see https://docs.mongodb.com/manual/reference/connection-string/#connections-connection-options
     * @param array<mixed> $options
     * @return Config
     * @throws PhpfastcacheLogicException
     */
    public function setOptions(array $options): static
    {
        return $this->setProperty('options', $options);
    }

    /**
     * @return array<mixed>
     */
    public function getDriverOptions(): array
    {
        return $this->driverOptions;
    }

    /**
     * @param array<mixed> $driverOptions
     * @return self
     * @throws PhpfastcacheLogicException
     */
    public function setDriverOptions(array $driverOptions): static
    {
        return $this->setProperty('driverOptions', $driverOptions);
    }

    /**
     * @return string
     */
    public function getProtocol(): string
    {
        return $this->protocol;
    }

    /**
     * @param string $protocol
     * @return self
     * @throws PhpfastcacheLogicException
     */
    public function setProtocol(string $protocol): static
    {
        return $this->setProperty('protocol', $protocol);
    }

    /**
     * @return string
     */
    public function getDocumentPrefix(): string
    {
        return $this->documentPrefix;
    }

    /**
     * @param string $documentPrefix
     * @return self
     * @throws PhpfastcacheLogicException
     */
    public function setDocumentPrefix(string $documentPrefix): static
    {
        return $this->setProperty('documentPrefix', $documentPrefix);
    }
}
