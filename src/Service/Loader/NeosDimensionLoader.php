<?php

namespace nlxNeosContent\Service\Loader;

use GuzzleHttp\Exception\GuzzleException;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @deprecated
 */
class NeosDimensionLoader extends AbstractNeosDimensionLoader
{
    public function __construct(
        private readonly HttpClientInterface $neosClient
    ) {
    }

    public function getDecorated(): AbstractNeosDimensionLoader
    {
        throw new DecorationPatternException(self::class);
    }

    public function load(): array
    {
        try {
            $response = $this->neosClient->request('GET', '/neos/shopware-api/dimension-mapping');
        } catch (GuzzleException $e) {
            throw new \Exception('Could not fetch dimension mapping from Neos: ' . $e->getMessage(), 1757507413, $e);
        }

        $body = $response->getBody()->getContents();
        return json_decode($body, true) ?? [];
    }
}
