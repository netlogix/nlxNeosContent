<?php

declare(strict_types=1);

namespace nlxNeosContent\Tests\Integration\PluginLifecycle;

use Doctrine\DBAL\Connection;
use nlxNeosContent\NlxNeosContent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

class PluginReinstallTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @return array<string, array{bool}>
     */
    public static function uninstallModes(): array
    {
        return [
            'without keeping user data, as plugin:install --reinstall does' => [false],
            'keeping user data' => [true],
        ];
    }

    #[DataProvider('uninstallModes')]
    public function testPluginCanBeInstalledAgainAfterUninstall(bool $keepUserData): void
    {
        $lifecycleService = static::getContainer()->get(PluginLifecycleService::class);
        $context = Context::createDefaultContext();

        $lifecycleService->uninstallPlugin($this->plugin(), $context, $keepUserData);
        $lifecycleService->installPlugin($this->plugin(), $context);
        $lifecycleService->activatePlugin($this->plugin(), $context);

        static::assertSame(
            'neos_connection',
            static::getContainer()->get(Connection::class)->fetchOne("SHOW COLUMNS FROM nlx_neos_node LIKE 'neos_connection'")
        );
    }

    private function plugin(): PluginEntity
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('baseClass', NlxNeosContent::class));
        $plugin = static::getContainer()->get('plugin.repository')->search($criteria, Context::createDefaultContext())->first();
        static::assertInstanceOf(PluginEntity::class, $plugin);

        return $plugin;
    }
}
