<?php

declare(strict_types=1);

namespace nlxNeosContent\Service;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use nlxNeosContent\Core\Content\NeosNode\NeosNodeEntity;
use nlxNeosContent\Core\Notification\NotificationServiceInterface;
use nlxNeosContent\Error\CanNotDeleteDefaultLayoutPageException;
use Psr\Http\Client\ClientInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Autoconfigure(public: true)]
class NeosLayoutPageService
{
    public const AVAILABLE_FILTER_PAGE_TYPES = 'product_list|product_detail|landingpage|page';

    public function __construct(
        private readonly EntityRepository $cmsPageRepository,
        private readonly EntityRepository $nlxNeosNodeRepository,
        private readonly NotificationServiceInterface $notificationService,
        private readonly ClientInterface $neosClient,
        private readonly SystemConfigService $systemConfigService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getAvailableFilterPageTypes(): array
    {
        return explode('|', self::AVAILABLE_FILTER_PAGE_TYPES);
    }

    public function initialNodeImport(Context $context): void
    {
        try {
            $neosNodes = $this->getNeosLayoutPages($this->getAvailableFilterPageTypes());
        } catch (Exception $e) {
            $this->createNotification(
                $context
            );
            return;
        }
        $this->createMissingNeosCmsPages($neosNodes, $context);
    }

    public function createMissingNeosCmsPages(array $neosNodes, Context $context): void
    {
        $presentNeosNodeIdentifiers = $this->getCmsPageIdWithConnectedNodeIdentifier($context);
        $neosNodesWithoutExistingShopwareLayouts = $this->removeAlreadyPresentNeosLayouts(
            $neosNodes,
            $presentNeosNodeIdentifiers
        );

        $this->createCmsPagesForNodes($neosNodesWithoutExistingShopwareLayouts, $context);
    }

    /**
     * Fetches Neos layout pages based on the provided page types.
     * @throws GuzzleException
     */
    public function getNeosLayoutPages(array $pageTypes): array
    {
        $language = '/en-GB';
        $contents = [];
        foreach ($pageTypes as $pageType) {
            if (!in_array($pageType, ['product_list', 'product_detail', 'landingpage', 'page'])) {
                throw new \InvalidArgumentException(sprintf('Invalid page type: %s', $pageType), 1743497434);
            }

            $apiUrl = sprintf('/neos/shopware-api/layout/pages/%s%s', $pageType, $language);
            try {
                $response = $this->neosClient->get($apiUrl, [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                    ],
                ]);
            } catch (Exception $e) {
                throw new \RuntimeException(
                    sprintf('Failed to fetch Neos layout pages: %s', $e->getMessage()),
                    1743497435,
                    $e
                );
            }

            $responseData = json_decode($response->getBody()->getContents(), true);
            foreach ($responseData as $value) {
                $contents[] = $value;
            }
        }

        return $contents;
    }

    private function createCmsPagesForNodes(array $neosNodes, Context $context): void
    {
        $pagesData = [];
        foreach ($neosNodes as $node) {
            $cmsPage = [
                'id' => md5($node['nodeIdentifier']),
                'name' => $node['name'],
                'type' => $node['type'],
                'versionId' => DEFAULTS::LIVE_VERSION,
                'nlxNeosNode' => [
                    'versionId' => DEFAULTS::LIVE_VERSION,
                    'cmsPageVersionId' => DEFAULTS::LIVE_VERSION,
                    'nodeIdentifier' => $node['nodeIdentifier'],
                ],
                'sections' => [
                    [
                        'versionId' => DEFAULTS::LIVE_VERSION,
                        'position' => 0,
                        'type' => 'default',
                        'sizingMode' => 'boxed',
                        'blocks' => [],
                    ],
                ],
                'locked' => true,
            ];

            $pagesData[] = $cmsPage;
        }

        $this->cmsPageRepository->upsert($pagesData, $context);
    }

    public function updateNeosCmsPages(array $neosNodes, Context $context): void
    {
        $pagesWithConnectedNeosNode = $this->getCmsPageIdWithConnectedNodeIdentifier($context);

        $pagesData = [];
        foreach ($neosNodes as $node) {
            $cmsPageId = array_search($node['nodeIdentifier'], $pagesWithConnectedNeosNode, true);

            $cmsPageData = [
                'id' => $cmsPageId,
                'name' => $node['name'],
                'type' => $node['type'],
                'nlxNeosNode' => [
                    'versionId' => DEFAULTS::LIVE_VERSION,
                    'cmsPageVersionId' => DEFAULTS::LIVE_VERSION,
                    'nodeIdentifier' => $node['nodeIdentifier'],
                ],
            ];

            $pagesData[] = $cmsPageData;
        }

        $this->cmsPageRepository->update(
            $pagesData,
            $context
        );
    }

    public function getNeosNodeEntitiesWithConnectedCmsPage(Context $context): EntityCollection
    {
        return $this->nlxNeosNodeRepository->search(
            (new Criteria())->addFilter(
                new NotFilter(
                    MultiFilter::CONNECTION_AND,
                    [
                        new EqualsFilter('cmsPageId', null),
                    ]
                )
            )->addAssociation('cmsPage'),
            $context
        )->getEntities();
    }

    /**
     * Removes CmsPages that have a node identifier which does not exist in Neos.
     * If a CmsPage is set as default, it will not be removed and a notification will be created.
     *
     * @param array $neosNodes
     * @param Context $context
     * @return void
     *
     * @throws CanNotDeleteDefaultLayoutPageException
     */
    public function removeCmsPagesWithInvalidNodeIdentifiers(array $neosNodes, Context $context): void
    {
        $neosNodeEntities = $this->getNeosNodeEntitiesWithConnectedCmsPage($context);
        $configs = $this->systemConfigService->get('core.cms');
        $defaultCmsPageIds = array_filter(
            $configs,
            fn ($key) => str_starts_with($key, 'default_'),
            ARRAY_FILTER_USE_KEY
        );

        $pagesToDelete = [];

        /** @var NeosNodeEntity $neosNodeEntity */
        foreach ($neosNodeEntities as $neosNodeEntity) {
            if (!$neosNodeEntity->getNodeIdentifier()) {
                continue;
            }
            $nodeIdentifier = array_column($neosNodes, 'nodeIdentifier');
            if (in_array($neosNodeEntity->getNodeIdentifier(), $nodeIdentifier)) {
                continue;
            }
            $cmsPageId = $neosNodeEntity->getCmsPageId();

            if (in_array($cmsPageId, $defaultCmsPageIds)) {
                throw new CanNotDeleteDefaultLayoutPageException(
                    $neosNodeEntity->getCmsPage()
                );
            }

            $pagesToDelete[] = ['id' => $cmsPageId];
        }

        $this->cmsPageRepository->delete(
            $pagesToDelete,
            $context
        );
    }

    private function getCmsPageIdWithConnectedNodeIdentifier(Context $context): array
    {
        $neosNodeEntities = $this->getNeosNodeEntitiesWithConnectedCmsPage($context);

        $availableNodeIdentifiers = [];
        /** @var NeosNodeEntity $nlxNeosNodeEntity */
        foreach ($neosNodeEntities as $nlxNeosNodeEntity) {
            $availableNodeIdentifiers[$nlxNeosNodeEntity->getCmsPageId()] = $nlxNeosNodeEntity->getNodeIdentifier();
        }

        return $availableNodeIdentifiers;
    }

    private function removeAlreadyPresentNeosLayouts(array $pages, array $alreadyPresentNeosLayouts): array
    {
        return array_filter($pages, function ($page) use ($alreadyPresentNeosLayouts) {
            return !in_array($page['nodeIdentifier'], $alreadyPresentNeosLayouts);
        });
    }

    public function createNotification(Context $context, ?string $message = null, ?string $status = 'error'): void
    {
        $this->notificationService->createNotification(
            [
                'id' => Uuid::randomHex(),
                'requiredPrivileges' => [],
                'message' => $message ?? $this->translator->trans('nlxNeosContent.notification.neosLayoutPagesFetchError'),
                'status' => $status,
            ],
            $context
        );
    }
}


