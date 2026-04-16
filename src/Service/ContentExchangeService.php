<?php

declare(strict_types=1);

namespace nlxNeosContent\Service;

use GuzzleHttp\Exception\RequestException;
use nlxNeosContent\Core\Content\Cms\Aggregate\CmsSection\NeosCmsSectionCollection;
use nlxNeosContent\Error\NeosContentFetchException;
use Psr\Http\Client\ClientInterface;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionCollection;
use Shopware\Core\Content\Cms\CmsPageEntity;
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
     * @throws NeosContentFetchException
     */
    public function getAlternativeCmsSectionsFromNeos(
        CmsPageEntity $cmsPage,
        SalesChannelContext $salesChannelContext
    ): CmsSectionCollection {
        $elements = $this->fetchNeosContentForCmsPage(
            $cmsPage,
            $salesChannelContext
        );

        return $this->serializer->denormalize($elements, NeosCmsSectionCollection::class,'json');
    }

    public function fetchCmsSectionsFromNeosByPath(string $pathInfo, SalesChannelContext $salesChannelContext): CmsSectionCollection
    {
        $domain = $salesChannelContext->getSalesChannel()->getDomains()->filter(function ($domain) use ($salesChannelContext) {
            return $domain->getId() === $salesChannelContext->getDomainId();
        })->first();

        $response = $this->neosClient->get(sprintf("/neos/shopware-api/content-by-path/%s", trim($pathInfo, '/')), [
            'headers' => [
                'x-sw-language-id' => $salesChannelContext->getLanguageId(),
                'x-sw-sales-channel-id' => $salesChannelContext->getSalesChannelId(),
                'x-sw-sales-channel-domain' => $domain->getUrl(),
                'x-sw-context-token' => $salesChannelContext->getSalesChannel()->getAccessKey(),
            ]
        ]);

        return $this->serializer->denormalize($response->getBody()->getContents(), NeosCmsSectionCollection::class,'json');
    }

    /**
     * @throws NeosContentFetchException
     */
    private function fetchNeosContentForCmsPage(
        CmsPageEntity $cmsPage,
        SalesChannelContext $salesChannelContext
    ): string {
        $languageId = $salesChannelContext->getLanguageId();
        $salesChannelId = $salesChannelContext->getSalesChannelId();
        $domain = $salesChannelContext->getSalesChannel()->getDomains()->filter(function ($domain) use ($salesChannelContext) {
            return $domain->getId() === $salesChannelContext->getDomainId();
        })->first();

        try {
            $response = $this->neosClient->get(sprintf("/neos/shopware-api/content/%s/", $cmsPage->getId()), [
                'headers' => [
                    'x-sw-language-id' => $languageId,
                    'x-sw-sales-channel-id' => $salesChannelId,
                    'x-sw-sales-channel-domain' => $domain->getUrl(),
                    'x-sw-context-token' => $salesChannelContext->getSalesChannel()->getAccessKey(),
                ]
            ]);
        } catch (RequestException $e) {
            throw new NeosContentFetchException (
                sprintf(
                    'Failed to fetch content from Neos for node CmsPage "%s" for sales channel "%s" and language "%s" with Errormessage: %s',
                    $cmsPage->getName(),
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

    /**
     * Loads the slot data into the given blocks for given resolver context.
     */
    public function loadSlotData(CmsBlockCollection $blocks, ResolverContext $resolverContext): void
    {
        $slots = $this->cmsSlotsDataResolver->resolve($blocks->getSlots(), $resolverContext);

        $blocks->setSlots($slots);
    }
}
