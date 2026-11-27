<?php

declare(strict_types=1);

namespace nlxNeosContent\Tests\Unit\HttpClient;

use nlxNeosContent\HttpClient\NeosClientFactory;
use nlxNeosContent\NlxNeosContent;
use nlxNeosContent\Service\ConfigService;
use PHPUnit\Framework\TestCase;

class NeosClientFactoryTest extends TestCase
{
    public function testEveryRequestCarriesThePluginVersion(): void
    {
        $options = NeosClientFactory::defaultOptions($this->configService(), [
            ['baseClass' => 'Some\\OtherPlugin', 'version' => '9.9.9'],
            ['baseClass' => NlxNeosContent::class, 'version' => '0.8.0'],
        ]);

        static::assertSame('0.8.0', $options['headers'][NeosClientFactory::PLUGIN_VERSION_HEADER]);
        static::assertSame('neos.example', $options['headers']['Host']);
    }

    private function configService(): ConfigService
    {
        $configService = $this->createMock(ConfigService::class);
        $configService->method('getBaseUrl')->willReturn('https://neos.example');

        return $configService;
    }
}
