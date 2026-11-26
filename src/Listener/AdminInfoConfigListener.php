<?php

declare(strict_types=1);

namespace nlxNeosContent\Listener;

use nlxNeosContent\Error\RequestError\NeosUrlNotConfiguredException;
use nlxNeosContent\Service\ConfigService;
use Shopware\Core\Framework\Api\Event\AdminInfoConfigEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Adds the plugin settings the administration needs to /api/_info/config, which every logged-in
 * user gets. The administration loads it before any plugin code, so it is available in
 * Shopware.Context.app.config.nlxNeosContent without granting system_config:read.
 */
#[AsEventListener]
readonly class AdminInfoConfigListener
{
    public const CONFIG_KEY = 'nlxNeosContent';

    public function __construct(
        private ConfigService $configService,
    ) {
    }

    public function __invoke(AdminInfoConfigEvent $event): void
    {
        try {
            $neosBaseUri = $this->configService->getBaseUrl();
        } catch (NeosUrlNotConfiguredException) {
            // A broken setting must not break the administration, it shows Neos as not configured instead.
            $neosBaseUri = '';
        }

        $event->addConfig(self::CONFIG_KEY, [
            'neosBaseUri' => $neosBaseUri === '' ? null : $neosBaseUri,
            'navigationExtensionEnabled' => $this->configService->isNavigationExtensionEnabled(),
        ]);
    }
}
