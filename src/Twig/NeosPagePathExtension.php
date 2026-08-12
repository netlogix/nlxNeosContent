<?php

declare(strict_types=1);

namespace nlxNeosContent\Twig;

use nlxNeosContent\Service\NeosPageTreeService;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

#[AsTaggedItem('twig.extension')]
class NeosPagePathExtension extends AbstractExtension
{
    public function __construct(
        private readonly NeosPageTreeService $neosPageTreeService,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('neos_page_path', [$this, 'getNeosPagePath'])
        ];
    }

    public function getNeosPagePath(string $nodeIdentifier, ?SalesChannelContext $context = null): string
    {
        $context ??= $this->resolveContext();

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

    private function resolveContext(): ?SalesChannelContext
    {
        $request = $this->requestStack->getCurrentRequest() ?? $this->requestStack->getMainRequest();

        $context = $request?->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);

        return $context instanceof SalesChannelContext ? $context : null;
    }
}
