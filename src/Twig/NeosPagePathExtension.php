<?php

declare(strict_types=1);

namespace nlxNeosContent\Twig;

use nlxNeosContent\Error\Routing\UnknownNeosPathException;
use nlxNeosContent\Service\NeosPageTreeService;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\StorefrontFrameworkException;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

#[AsTaggedItem('twig.extension')]
class NeosPagePathExtension extends AbstractExtension
{
    public function __construct(
        private readonly NeosPageTreeService $neosPageTreeService,
        private readonly LoggerInterface $logger,
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
            throw StorefrontFrameworkException::salesChannelContextObjectNotFound();
        }

        try {
            return $this->fetchNeosPage($nodeIdentifier, $context);
        } catch (\Throwable $e) {
            $this->logger->error('Error while fetching Neos page path', [
                'nodeIdentifier' => $nodeIdentifier,
                'context' => $context,
                'exception' => $e,
            ]);

            return "";
        }

    }

    private function fetchNeosPage(string $nodeIdentifier, SalesChannelContext $context): string
    {
        $normalizedIdentifier = str_replace('-', '', $nodeIdentifier);

        $pathInfo = $this->neosPageTreeService->findPathInfoForIdentifierAndContext(
            $normalizedIdentifier,
            $context
        );

        if ($pathInfo === '' || $pathInfo === null) {
            throw new UnknownNeosPathException(code: 1786546010);
        }

        return '/' . ltrim($pathInfo, '/');
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
