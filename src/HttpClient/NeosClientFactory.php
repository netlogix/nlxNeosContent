<?php

declare(strict_types=1);

namespace nlxNeosContent\HttpClient;

use GuzzleHttp\Client;
use nlxNeosContent\Service\ConfigService;
use Psr\Http\Client\ClientInterface;

class NeosClientFactory
{
    public function __construct() {
    }

    public static function create(
        ConfigService $configService
    ): ClientInterface
    {
        return new Client([
            'base_uri' => $configService->getBaseUrl()
        ]);
    }

}
