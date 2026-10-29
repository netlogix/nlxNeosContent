<?php

declare(strict_types=1);

$classLoader = require (getenv('PROJECT_ROOT') ?: getcwd()) . '/vendor/autoload.php';
$classLoader->addPsr4('nlxNeosContent\\', dirname(__DIR__) . '/src');
$classLoader->addPsr4('nlxNeosContent\\Tests\\', __DIR__);
