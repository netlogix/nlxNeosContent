<?php

declare(strict_types=1);

namespace nlxNeosContent\Tests\Integration\PluginLifecycle;

use Doctrine\DBAL\Connection;
use nlxNeosContent\NlxNeosContent;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class PluginIsActiveTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testPluginIsActiveInKernel(): void
    {
        $plugin = static::getKernel()->getPluginLoader()->getPluginInstances()->get(NlxNeosContent::class);

        static::assertInstanceOf(NlxNeosContent::class, $plugin);
        static::assertTrue($plugin->isActive());
    }

    public function testMigrationsCreatedNeosNodeTable(): void
    {
        $schemaManager = static::getContainer()->get(Connection::class)->createSchemaManager();

        static::assertTrue($schemaManager->tablesExist(['nlx_neos_node']));
    }
}
