<?php

declare(strict_types=1);

namespace nlxNeosContent\HttpClient;

use nlxNeosContent\Service\ConfigService;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class NeosClientFactory
{
    /**
     * Idle timeout in seconds, also covering the wait for the first byte of an uncached Neos page.
     */
    public const TIMEOUT = 5.0;

    public function __construct()
    {
    }

    public static function create(
        ConfigService $configService,
    ): HttpClientInterface {
        if (!$configService->isEnabled()) {
            return new MissingUrlClient();
        }
        return HttpClient::createForBaseUri(
            $configService->getInternalBaseUrl(),
            [
                'headers' => [
                    'Host' => parse_url($configService->getBaseUrl(), PHP_URL_HOST),
                ],
                // Storefront requests wait on Neos synchronously; without this a hanging Neos holds
                // every PHP worker for default_socket_timeout (usually 60s).
                'timeout' => self::TIMEOUT,
            ]
        );
    }

}
