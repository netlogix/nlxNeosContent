<?php

declare(strict_types=1);

namespace nlxNeosContent\Twig;

use nlxNeosContent\Service\NeosPageTreeService;
use Shopware\Core\Framework\Adapter\Twig\TwigContextHelper;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
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
        $context = TwigContextHelper::getSalesChannelContext($twigContext);

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
}
