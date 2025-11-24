<?php

declare(strict_types=1);

namespace nlxNeosContent\Neos\Endpoint;

use nlxNeosContent\Neos\DTO\NeosPageCollection;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\UnwrappingDenormalizer;
use Symfony\Component\Serializer\SerializerInterface;

#[AsAlias(AbstractNeosPageTreeLoader::class)]
readonly class NeosPageTreeLoader extends AbstractNeosPageTreeLoader
{
    function __construct(
        #[Autowire(service: 'nlx-neos-content.neos-client')]
        private ClientInterface $neosClient,
        #[Autowire(service: 'serializer')]
        private SerializerInterface $serializer,
    ) {
    }

    public function getDecorated(): AbstractNeosPageTreeLoader
    {
        throw new DecorationPatternException(self::class);
    }

    public function load(string $salesChannelId, string $languageId): NeosPageCollection
    {
        /** @var ResponseInterface $response */
        $response = $this->neosClient->get('neos/shopware-api/pagetree', [
            'headers' => [
                'x-sw-sales-channel-id' => $salesChannelId,
                'x-sw-language-id' => $languageId,
            ]
        ]);
        $content = $response->getBody()->getContents();

        return $this->serializer->deserialize($content, NeosPageCollection::class, 'json', [
            UnwrappingDenormalizer::UNWRAP_PATH => '[pages]'
        ]);
    }
}
