<?php

declare(strict_types=1);

namespace nlxNeosContent\HttpClient;

use nlxNeosContent\NlxNeosContent;
use nlxNeosContent\Service\ConfigService;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class NeosClientFactory
{
    /**
     * Idle timeout in seconds, also covering the wait for the first byte of an uncached Neos page.
     */
    public const TIMEOUT = 5.0;

    /**
     * Sent with every request, so Neos can tell which plugin version it is talking to.
     */
    public const PLUGIN_VERSION_HEADER = 'x-sw-plugin-version';

    public function __construct()
    {
    }

    /**
     * @param list<array{baseClass: string, version: ?string}> $pluginInfos kernel.plugin_infos, read from the plugin table at container build
     */
    public static function create(
        ConfigService $configService,
        array $pluginInfos,
    ): HttpClientInterface {
        if (!$configService->isEnabled()) {
            return new MissingUrlClient();
        }
        return HttpClient::createForBaseUri(
            $configService->getInternalBaseUrl(),
            self::defaultOptions($configService, $pluginInfos)
        );
    }

    /**
     * @param list<array{baseClass: string, version: ?string}> $pluginInfos
     */
    public static function defaultOptions(ConfigService $configService, array $pluginInfos): array
    {
        return [
            'headers' => [
                'Host' => parse_url($configService->getBaseUrl(), PHP_URL_HOST),
                self::PLUGIN_VERSION_HEADER => self::pluginVersion($pluginInfos),
            ],
            // Storefront requests wait on Neos synchronously; without this a hanging Neos holds
            // every PHP worker for default_socket_timeout (usually 60s).
            'timeout' => self::TIMEOUT,
        ];
    }

    private static function pluginVersion(array $pluginInfos): string
    {
        foreach ($pluginInfos as $pluginInfo) {
            if ($pluginInfo['baseClass'] === NlxNeosContent::class) {
                return (string) $pluginInfo['version'];
            }
        }

        return '';
    }
}
