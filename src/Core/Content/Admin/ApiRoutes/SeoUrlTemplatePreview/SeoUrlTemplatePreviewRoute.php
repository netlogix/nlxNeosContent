<?php

declare(strict_types=1);

namespace nlxNeosContent\Core\Content\Admin\ApiRoutes\SeoUrlTemplatePreview;

use nlxNeosContent\Neos\DTO\NeosPageCollection;
use nlxNeosContent\Neos\DTO\NeosPageDTO;
use nlxNeosContent\Neos\Endpoint\AbstractNeosPageTreeLoader;
use nlxNeosContent\Service\NeosPageSeoUrlTemplateRenderer;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Error\Error as TwigError;

#[Route(defaults: ['_routeScope' => ['api']])]
class SeoUrlTemplatePreviewRoute extends AbstractSeoUrlTemplatePreviewRoute
{
    private const PREVIEW_LIMIT = 10;

    /**
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     */
    public function __construct(
        private readonly EntityRepository $salesChannelRepository,
        private readonly AbstractNeosPageTreeLoader $neosPageTreeLoader,
        private readonly NeosPageSeoUrlTemplateRenderer $seoUrlTemplateRenderer,
    ) {
    }

    public function getDecorated(): AbstractSeoUrlTemplatePreviewRoute
    {
        return $this;
    }

    #[Route(path: '/api/_action/neos/seo-url-template/preview', name: 'api.neos.seo-url-template.preview', methods: ['POST'])]
    public function load(Request $request, Context $context): Response
    {
        $content = json_decode(json: $request->getContent(), associative: true) ?? [];
        $template = trim((string) ($content['template'] ?? ''));
        if ($template === '') {
            $template = NeosPageSeoUrlTemplateRenderer::DEFAULT_TEMPLATE;
        }
        $salesChannelId = $content['salesChannelId'] ?? null;

        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));
        $criteria->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [new EqualsFilter('domains.id', null)]));
        if (\is_string($salesChannelId) && Uuid::isValid($salesChannelId)) {
            $criteria->setIds([$salesChannelId]);
        }

        $salesChannel = $this->salesChannelRepository->search($criteria, $context)->getEntities()->first();
        if ($salesChannel === null) {
            return new JsonResponse([]);
        }

        $tree = $this->neosPageTreeLoader->load($salesChannel->getId(), $salesChannel->getLanguageId());

        try {
            $tree = $this->seoUrlTemplateRenderer->apply($tree, $template);
        } catch (TwigError $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $previews = [];
        foreach ($this->flatten($tree) as $page) {
            if (trim($page->path, '/') === '' || NeosPageDTO::isExternalUrl($page->path)) {
                continue;
            }

            $previews[] = ['label' => $page->label, 'path' => $page->path, 'seoPath' => '/' . trim($page->seoPath, '/')];
            if (\count($previews) >= self::PREVIEW_LIMIT) {
                break;
            }
        }

        return new JsonResponse($previews);
    }

    /**
     * @return \Generator<NeosPageDTO>
     */
    private function flatten(NeosPageCollection $pages): \Generator
    {
        foreach ($pages as $page) {
            yield $page;
            yield from $this->flatten($page->children);
        }
    }
}
