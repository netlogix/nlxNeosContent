<?php

declare(strict_types=1);

namespace nlxNeosContent\HttpClient;

use nlxNeosContent\Service\ConfigService;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class NeosClientFactory
{
    public function __construct()
    {
    }

    public static function create(
        ConfigService $configService,
    ): HttpClientInterface {
        return HttpClient::createForBaseUri(
            $configService->getInternalBaseUrl(),
            [
                'headers' => [
                    'Host' => parse_url($configService->getBaseUrl(), PHP_URL_HOST),
                ],
            ]
        );
    }

}
