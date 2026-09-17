<?php

declare(strict_types=1);

namespace nlxNeosContent\Neos\Endpoint;

use nlxNeosContent\Neos\DTO\NeosPageCollection;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\UnwrappingDenormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsAlias(AbstractNeosPageTreeLoader::class)]
readonly class NeosPageTreeLoader extends AbstractNeosPageTreeLoader
{
    /**
     * Bounds a single tree's worst-case latency when fetching SEVERAL concurrently
     * (loadMany() with more than one request) - without it, one slow/cold domain
     * could block the whole batch. A lone request (the common real-storefront-404
     * fast path) gets no such ceiling - it's no different from load(), which has
     * never had one, and doesn't need protecting from a batch it isn't part of.
     */
    private const CONCURRENT_REQUEST_TIMEOUT = 3.0;

    function __construct(
        #[Autowire(service: 'nlx-neos-content.neos-client')]
        private HttpClientInterface $neosClient,
        #[Autowire(service: 'serializer')]
        private SerializerInterface $serializer,
    ) {
    }

    public function getDecorated(): AbstractNeosPageTreeLoader
    {
        throw new DecorationPatternException(self::class);
    }

    public function load(string $salesChannelId, string $languageId, string $domainUrl): NeosPageCollection
    {
        $response = $this->neosClient->request('GET', 'neos/shopware-api/pagetree', [
            'headers' => [
                'x-sw-sales-channel-id' => $salesChannelId,
                'x-sw-language-id' => $languageId,
                'x-sw-sales-channel-domain' => $domainUrl,
            ]
        ]);

        return $this->serializer->deserialize($response->getContent(), NeosPageCollection::class, 'json', [
            UnwrappingDenormalizer::UNWRAP_PATH => '[pages]'
        ]);
    }

    public function loadMany(array $requests): array
    {
        $requestOptions = count($requests) > 1 ? ['timeout' => self::CONCURRENT_REQUEST_TIMEOUT] : [];

        $pending = [];
        foreach ($requests as $request) {
            [$salesChannelId, $languageId, $domainUrl] = $request;
            $response = $this->neosClient->request('GET', 'neos/shopware-api/pagetree', [
                'headers' => [
                    'x-sw-sales-channel-id' => $salesChannelId,
                    'x-sw-language-id' => $languageId,
                    'x-sw-sales-channel-domain' => $domainUrl,
                ],
                ...$requestOptions,
            ]);
            $pending[] = [$request, $response];
        }

        // Requests above were only started, not awaited - Symfony's HttpClient runs them
        // concurrently under the hood, so this loop's total wait is ~one round trip, not N.
        $results = [];
        foreach ($pending as [$request, $response]) {
            [$salesChannelId, $languageId, $domainUrl] = $request;

            try {
                $tree = $this->serializer->deserialize($response->getContent(), NeosPageCollection::class, 'json', [
                    UnwrappingDenormalizer::UNWRAP_PATH => '[pages]'
                ]);
                $results[] = new NeosPageTreeLoadResult($salesChannelId, $languageId, $domainUrl, $tree);
            } catch (\Throwable) {
                // Signals a failed fetch, not a genuinely empty tree - CachedNeosPageTreeLoader
                // must not cache this as if Neos really had no pages here.
                $results[] = new NeosPageTreeLoadResult($salesChannelId, $languageId, $domainUrl, new NeosPageCollection(), failed: true);
            }
        }

        return $results;
    }
}
