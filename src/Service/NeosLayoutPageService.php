<?php

declare(strict_types=1);

namespace nlxNeosContent\Service;

use GuzzleHttp\Client;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\InputBag;

class NeosLayoutPageService
{
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly EntityRepository $cmsPageRepository,
        private readonly EntityRepository $nlxNeosNodeRepository,
    ) {
    }

    public function createCmsPagesForMissingNeosNodes(InputBag $payload, Context $context): void
    {
        $pageTypes = $this->getFilterPageTypes($payload);
        $neosNodes = $this->getNeosLayoutPages($pageTypes);

        $alreadyPresentNeosLayouts = $this->getShopwarePagesWithConnectedNeosNode($context);
        $neosNodesWithoutExistingShopwareLayouts = $this->removeAlreadyPresentNeosLayouts(
            $neosNodes,
            $alreadyPresentNeosLayouts
        );

        $this->createCmsPagesForNodes($neosNodesWithoutExistingShopwareLayouts, $context);
    }

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
            $response = $client->get($apiUrl, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
            ]);

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

        $this->cmsPageRepository->create($pagesData, $context);
    }

    private function getFilterPageTypes($payload): array
    {
        $pageTypeFilterValues = 'product_list|product_detail|landingpage|page';

        if ($payload->has('filter')) {
            foreach ($payload->getIterator() as $parameter => $value) {
                if ($parameter === 'filter') {
                    if (is_array($value)) {
                        foreach ($value as $filter) {
                            if ($filter['field'] === 'type') {
                                $pageTypeFilterValues = $filter['value'];
                            }
                        }
                    }
                }
            }
        }

        return explode('|', $pageTypeFilterValues);
    }

    private function getShopwarePagesWithConnectedNeosNode(Context $context): array
    {
        $nlxNeosNodeEntities = $this->nlxNeosNodeRepository->search(
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

        $availableNodeIdentifiers = [];
        /** @var Entity $nlxNeosNodeEntity */
        foreach ($nlxNeosNodeEntities as $nlxNeosNodeEntity) {
            $availableNodeIdentifiers[$nlxNeosNodeEntity->getVars()['cmsPageId']] = $nlxNeosNodeEntity->getVars(
            )['nodeIdentifier'];
        }

        return $availableNodeIdentifiers;
    }

    private function removeAlreadyPresentNeosLayouts(array $pages, array $alreadyPresentNeosLayouts): array
    {
        return array_filter($pages, function ($page) use ($alreadyPresentNeosLayouts) {
            return !in_array($page['nodeIdentifier'], $alreadyPresentNeosLayouts);
        });
    }
}


