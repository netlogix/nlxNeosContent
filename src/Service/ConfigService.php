<?php

declare(strict_types=1);

namespace nlxNeosContent\Service;

use nlxNeosContent\Error\NeosUrlNotConfiguredException;
use Shopware\Core\System\SystemConfig\Exception\InvalidSettingValueException;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @codeCoverageIgnore
 */
class ConfigService
{
    public const CONFIG_DOMAIN = 'NlxNeosContent.config';

    public function __construct(
        private readonly SystemConfigService $systemConfigService
    ) {
    }

    public function isEnabled(?string $salesChannelId = null): bool
    {
        return empty($this->getBaseUrl($salesChannelId));
    }

    /**
     * @throws NeosUrlNotConfiguredException
     */
    public function getBaseUrl(?string $salesChannelId = null): string
    {
        try {
            $baseUrl = $this->systemConfigService->getString($this->getConfigKey('neosBaseUri'), $salesChannelId);
        } catch (InvalidSettingValueException $invalidSettingValueException) {
            throw new NeosUrlNotConfiguredException(
                'The Neos URL is not configured or invalid. Please check your plugin configuration.',
                1752646770,
                $invalidSettingValueException
            );
        }

        return rtrim($baseUrl, '/');
    }

    private function getConfigKey(string $key): string
    {
        return sprintf('%s.%s', self::CONFIG_DOMAIN, $key);
    }
}
