<?php

declare(strict_types=1);

namespace nlxNeosContent\Service;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use nlxNeosContent\Core\Content\NeosNode\NeosNodeEntity;
use Shopware\Administration\Notification\NotificationService;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
class NeosLayoutPageService
{
    public const string AVAILABLE_FILTER_PAGE_TYPES = 'product_list|product_detail|landingpage|page';

    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly EntityRepository $cmsPageRepository,
        private readonly EntityRepository $nlxNeosNodeRepository,
        private readonly NotificationService $notificationService,
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
        $baseUrl = $this->systemConfigService->get('NlxNeosContent.config.neosBaseUri');
        if (!$baseUrl) {
            throw new \RuntimeException('Neos Base URI is not configured', 1743418180);
        }

        $language = '/en-GB';
        $contents = [];
        foreach ($pageTypes as $pageType) {
            if (!in_array($pageType, ['product_list', 'product_detail', 'landingpage', 'page'])) {
                throw new \InvalidArgumentException(sprintf('Invalid page type: %s', $pageType), 1743497434);
            }

            $apiUrl = sprintf('%s/shopware-api/layout/pages/%s%s', $baseUrl, $pageType, $language);
            $client = new Client();
            try {
                $response = $client->get($apiUrl, [
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
                    NotFilter::CONNECTION_AND,
                    [
                        new EqualsFilter('cmsPageId', null),
                    ]
                )
            ),
            $context
        )->getEntities();
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

    public function createNotification(Context $context, ?string $message = null): void
    {
        //FIXME translate Message
        if (empty($message)) {
            $message = 'Neos layout pages could not be fetched. This might be due to a misconfiguration of the Neos Base URI or an issue with the Neos API.';
        }
        $this->notificationService->createNotification(
            [
                'id' => Uuid::randomHex(),
                'requiredPrivileges' => [],
                'message' => $message,
                'status' => 'error',
            ],
            $context
        );
    }
}


