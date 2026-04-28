<?php

declare(strict_types=1);

namespace nlxNeosContent\Service;

use nlxNeosContent\Error\NeosUrlNotConfiguredException;
use Shopware\Core\System\SystemConfig\Exception\InvalidSettingValueException;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @codeCoverageIgnore
 */
class ConfigService
{
    public const CONFIG_DOMAIN = 'NlxNeosContent.config';
    public const SETTING_DOMAIN = 'NlxNeosContent.settings';

    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        #[Autowire(env: 'NEOS_CONTENT_INTERNAL_BASE_URI')]
        private readonly ?string $internalBaseUrl = null,
    ) {}

    public function isEnabled(?string $salesChannelId = null): bool
    {
        return !empty($this->getBaseUrl($salesChannelId));
    }

    public function getInternalBaseUrl(?string $salesChannelId = null): string
    {
        return empty($this->internalBaseUrl) ? $this->getBaseUrl($salesChannelId) : $this->internalBaseUrl;
    }

    /**
     * @throws NeosUrlNotConfiguredException
     */
    public function getBaseUrl(?string $salesChannelId = null): string
    {
        try {
            $baseUrl = $this->systemConfigService->getString($this->getSettingKey('neosBaseUri'), $salesChannelId);
        } catch (InvalidSettingValueException $invalidSettingValueException) {
            throw new NeosUrlNotConfiguredException(
                'The Neos URL is not configured or invalid. Please check your plugin configuration.',
                1752646770,
                $invalidSettingValueException
            );
        }

        return rtrim($baseUrl, '/');
    }

    public function isNavigationExtensionEnabled(?string $salesChannelId = null): bool
    {
        return $this->systemConfigService->getBool($this->getConfigKey('extendNavigation'), $salesChannelId);
    }

    private function getConfigKey(string $key): string
    {
        return sprintf('%s.%s', self::CONFIG_DOMAIN, $key);
    }

    private function getSettingKey(string $key): string
    {
        return sprintf('%s.%s', self::SETTING_DOMAIN, $key);
    }
}
