<?php

use Phpfastcache\Drivers\Mongodb\Config as MongodbConfig;

return (fn(MongodbConfig $config) => $config->setItemDetailedDate(true)
    ->setDatabaseName('pfc_test')
    ->setCollectionName('pfc_test')
    ->setUsername('test')
    ->setPassword('phpfastcache')
)(new MongodbConfig());
