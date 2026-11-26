<?php

declare(strict_types=1);

namespace nlxNeosContent\Tests\Unit\Listener;

use nlxNeosContent\Error\RequestError\NeosUrlNotConfiguredException;
use nlxNeosContent\Listener\AdminInfoConfigListener;
use nlxNeosContent\Service\ConfigService;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Event\AdminInfoConfigEvent;

class AdminInfoConfigListenerTest extends TestCase
{
    public function testAddsTheSettingsTheAdministrationNeeds(): void
    {
        $configService = $this->createMock(ConfigService::class);
        $configService->method('getBaseUrl')->willReturn('https://neos.example');
        $configService->method('isNavigationExtensionEnabled')->willReturn(true);

        static::assertSame(
            ['neosBaseUri' => 'https://neos.example', 'navigationExtensionEnabled' => true],
            $this->dispatch($configService)
        );
    }

    public function testMissingNeosUrlIsNull(): void
    {
        $configService = $this->createMock(ConfigService::class);
        $configService->method('getBaseUrl')->willReturn('');

        static::assertNull($this->dispatch($configService)['neosBaseUri']);
    }

    public function testInvalidNeosUrlDoesNotBreakTheConfig(): void
    {
        $configService = $this->createMock(ConfigService::class);
        $configService->method('getBaseUrl')->willThrowException(new NeosUrlNotConfiguredException('invalid', 1));

        static::assertNull($this->dispatch($configService)['neosBaseUri']);
    }

    /**
     * @return array<string, mixed>
     */
    private function dispatch(ConfigService $configService): array
    {
        $event = new AdminInfoConfigEvent(['version' => '6.7.0.0']);
        (new AdminInfoConfigListener($configService))($event);

        static::assertSame('6.7.0.0', $event->getConfig()['version']);

        return $event->getConfig()[AdminInfoConfigListener::CONFIG_KEY];
    }
}
