<?php

namespace nlxNeosContent\Service\Loader;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

class NeosDimensionLoader extends AbstractNeosDimensionLoader
{
    public function __construct(
        private readonly ClientInterface $neosClient
    ) {
    }

    public function getDecorated(): AbstractNeosDimensionLoader
    {
        throw new DecorationPatternException(self::class);
    }

    public function load(): array
    {
        try {
            $response = $this->neosClient->get('/neos/shopware-api/dimension-mapping');
        } catch (GuzzleException $e) {
            throw new \Exception('Could not fetch dimension mapping from Neos: ' . $e->getMessage(), 1757507413, $e);
        }

        $body = $response->getBody()->getContents();
        return json_decode($body, true) ?? [];
    }
}
