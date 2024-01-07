<?php

use Phpfastcache\Drivers\Mongodb\Config as MongodbConfig;

return (new MongodbConfig())
    ->setItemDetailedDate(true)
    ->setHost('127.0.0.1')
    ->setOptions(getenv('MONGODB_AUTH_SOURCE') ? ['authSource' => getenv('MONGODB_AUTH_SOURCE')] : [])
    ->setDatabaseName('pfc_test')
    ->setCollectionName('pfc_test')
    ->setUsername('test')
    ->setPassword('phpfastcache');
