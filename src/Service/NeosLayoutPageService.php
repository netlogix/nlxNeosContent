<?php

declare(strict_types=1);

namespace nlxNeosContent\Service;

use Exception;
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

    public function getNeosCmsPageTemplates(): array
    {
        $context = Context::createDefaultContext();
        try {
            $response = $this->neosClient->get('/neos/shopware-api/layout/pages', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'x-sw-language-id' => $context->getLanguageId(),
                ],
            ]);
        } catch (Exception $e) {
            throw new \RuntimeException(
                sprintf('Failed to fetch Neos layout pages: %s', $e->getMessage()),
                1743497435,
                $e
            );
        }

        return json_decode($response->getBody()->getContents(), true) ?? [];
    }

    public function updateNeosCmsPages(array $neosNodes, Context $context): void
    {
        $pagesData = [];
        foreach ($neosNodes as $node) {
            $cmsPageData = [
                'id' => $node['cmsPageId'],
                'name' => $node['title'],
                'type' => $node['type'],
                'nlxNeosNode' => [
                    'id' => md5($node['cmsPageId'] . '_neos_node'),
                    'versionId' => DEFAULTS::LIVE_VERSION,
                    'cmsPageId' => $node['cmsPageId'],
                    'cmsPageVersionId' => DEFAULTS::LIVE_VERSION,
                    'neosConnection' => true,
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
                new EqualsFilter('neosConnection', true)
            )->addAssociation('cmsPage'),
            $context
        )->getEntities();
    }

    public function removeObsoleteCmsPages(array $neosNodes, Context $context): void
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
            if (!$neosNodeEntity->getNeosConnection()) {
                continue;
            }
            $cmsPageIds = array_column($neosNodes, 'cmsPageId');
            $cmsPageId = $neosNodeEntity->getCmsPageId();
            if (in_array($cmsPageId, $cmsPageIds, true)) {
                continue;
            }

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

    public function createNotification(Context $context, ?string $message = null, ?string $status = 'error'): void
    {
        $this->notificationService->createNotification(
            [
                'id' => Uuid::randomHex(),
                'requiredPrivileges' => [],
                'message' => $message ?? $this->translator->trans(
                        'nlxNeosContent.notification.neosLayoutPagesFetchError'
                    ),
                'status' => $status,
            ],
            $context
        );
    }

    public function processProvidedNodes(array $nodes, Context $context): void
    {
        $pagesData = [];
        foreach ($nodes as $node) {
            $cmsPageData = [
                'id' => $node['cmsPageId'],
                'name' => $node['title'],
                'type' => $node['type'],
                'nlxNeosNode' => [
                    'id' => md5($node['cmsPageId'] . '_neos_node'),
                    'versionId' => DEFAULTS::LIVE_VERSION,
                    'cmsPageId' => $node['cmsPageId'],
                    'cmsPageVersionId' => DEFAULTS::LIVE_VERSION,
                    'neosConnection' => true,
                ],
            ];

            $pagesData[] = $cmsPageData;
        }

        $this->cmsPageRepository->upsert(
            $pagesData,
            $context
        );
    }
}


