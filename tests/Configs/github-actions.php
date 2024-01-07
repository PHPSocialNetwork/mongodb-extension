<?php

use Phpfastcache\Drivers\Mongodb\Config as MongodbConfig;

return (fn(MongodbConfig $config) => $config->setItemDetailedDate(true)
    ->setHost('127.0.0.1')
    ->setOptions(getenv('MONGODB_AUTH_SOURCE') ? ['authSource' => getenv('MONGODB_AUTH_SOURCE')] : [])
    ->setDatabaseName('pfc_test')
    ->setCollectionName('pfc_test')
    ->setUsername('test')
    ->setPassword('phpfastcache')
)(new MongodbConfig());
