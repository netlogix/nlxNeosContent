<?php

declare(strict_types=1);

// The Shopware project the plugin is installed in, whose autoload-dev doesn't include the plugin's tests.
$classLoader = require (getenv('PROJECT_ROOT') ?: getcwd()) . '/vendor/autoload.php';
$classLoader->addPsr4('nlxNeosContent\\Tests\\', __DIR__);
