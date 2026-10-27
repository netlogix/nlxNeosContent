<?php

declare(strict_types=1);

use Shopware\Core\TestBootstrapper;

(new TestBootstrapper())
    ->addCallingPlugin()
    ->setForceInstallPlugins(true)
    ->bootstrap()
    ->getClassLoader();
