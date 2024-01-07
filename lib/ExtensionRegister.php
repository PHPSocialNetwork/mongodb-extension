<?php

declare(strict_types=1);

namespace Phpfastcache;

use Phpfastcache\Extensions\Drivers\Mongodb\{Config, Driver, Item};

// Semver Compatibility until v10
class_alias(Config::class, Drivers\Mongodb\Config::class);
class_alias(Driver::class, Drivers\Mongodb\Driver::class);
class_alias(Item::class, Drivers\Mongodb\Item::class);

ExtensionManager::registerExtension('Mongodb', Driver::class);
