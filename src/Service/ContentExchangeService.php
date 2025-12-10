<?php

declare(strict_types=1);

namespace nlxNeosContent\Service;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use nlxNeosContent\Core\Content\Cms\Aggregate\CmsSection\NeosCmsSectionCollection;
use nlxNeosContent\Error\NeosContentFetchException;
use Psr\Http\Client\ClientInterface;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionCollection;
use Shopware\Core\Content\Cms\DataResolver\CmsSlotsDataResolver;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\SerializerInterface;

class ContentExchangeService
{
    public function __construct(
        #[Autowire(service: 'serializer')]
        private readonly SerializerInterface $serializer,
        private readonly CmsSlotsDataResolver $cmsSlotsDataResolver,
        private readonly ClientInterface $neosClient,
    ) {
    }

    /**
     * @throws GuzzleException
     */
    public function getAlternativeCmsSectionsFromNeos(
        string $nodeIdentifier,
        string $languageId,
        string $salesChannelId
    ): CmsSectionCollection {
        $elements = $this->fetchNeosContentByNodeIdentifierAndDimension(
            $nodeIdentifier,
            $languageId,
            $salesChannelId
        );

        return $this->serializer->denormalize($elements, NeosCmsSectionCollection::class,'json');
    }

    public function fetchCmsSectionsFromNeosByPath(string $pathInfo, SalesChannelContext $salesChannelContext): CmsSectionCollection
    {
        $response = $this->neosClient->get(sprintf("/neos/shopware-api/content-by-path/%s", trim($pathInfo, '/')), [
            'headers' => [
                'x-sw-language-id' => $salesChannelContext->getLanguageId(),
                'x-sw-sales-channel-id' => $salesChannelContext->getSalesChannelId(),
            ]
        ]);
        return $this->serializer->denormalize($response->getBody()->getContents(), NeosCmsSectionCollection::class,'json');
    }

    /**
     * @throws NeosContentFetchException
     */
    private function fetchNeosContentByNodeIdentifierAndDimension(
        string $nodeIdentifier,
        string $languageId,
        string $salesChannelId
    ): string {
        try {
            $response = $this->neosClient->get(sprintf("/neos/shopware-api/content/%s/", $nodeIdentifier), [
                'headers' => [
                    'x-sw-language-id' => $languageId,
                    'x-sw-sales-channel-id' => $salesChannelId,
                ]
            ]);
        } catch (RequestException $e) {
            throw new NeosContentFetchException (
                sprintf(
                    'Failed to fetch content from Neos for node identifier "%s" for sales channel "%s" and language "%s": %s',
                    $nodeIdentifier,
                    $salesChannelId,
                    $languageId,
                    $e->getMessage()
                ),
                1752652016,
                $e
            );
        }

        return $response->getBody()->getContents();
    }

    public function loadSlotData(CmsBlockCollection $blocks, ResolverContext $resolverContext): void
    {
        $slots = $this->cmsSlotsDataResolver->resolve($blocks->getSlots(), $resolverContext);

        $blocks->setSlots($slots);
    }
}
