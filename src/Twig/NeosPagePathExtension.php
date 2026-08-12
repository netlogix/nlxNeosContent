<?php

declare(strict_types=1);

namespace nlxNeosContent\Twig;

use nlxNeosContent\Service\NeosPageTreeService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

#[AsTaggedItem('twig.extension')]
class NeosPagePathExtension extends AbstractExtension
{
    public function __construct(
        private readonly NeosPageTreeService $neosPageTreeService,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('neos_page_path', $this->getNeosPagePath(...), ['needs_context' => true]),
        ];
    }

    public function getNeosPagePath(array $twigContext, string $nodeIdentifier): string
    {
        $context = $this->getSalesChannelContext($twigContext);

        if (!$context instanceof SalesChannelContext) {
            return '';
        }

        $normalizedIdentifier = str_replace('-', '', $nodeIdentifier);

        $pathInfo = $this->neosPageTreeService->findPathInfoForIdentifierAndContext(
            $normalizedIdentifier,
            $context
        );

        return $pathInfo === '' ? '' : '/' . ltrim($pathInfo, '/');
    }

    private function getSalesChannelContext(array $twigContext): ?SalesChannelContext
    {
        $context = $twigContext['context'] ?? null;
        if ($context instanceof SalesChannelContext) {
            return $context;
        }

        $salesChannelContext = $twigContext['salesChannelContext'] ?? null;
        if ($salesChannelContext instanceof SalesChannelContext) {
            return $salesChannelContext;
        }

        return null;
    }
}
