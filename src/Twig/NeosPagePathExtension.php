<?php

declare(strict_types=1);

namespace nlxNeosContent\Twig;

use nlxNeosContent\Error\Routing\UnknownNeosPathException;
use nlxNeosContent\Service\NeosPageTreeService;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Shopware\Storefront\Framework\StorefrontFrameworkException;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

#[AsTaggedItem('twig.extension')]
class NeosPagePathExtension extends AbstractExtension
{
    public function __construct(
        private readonly NeosPageTreeService $neosPageTreeService,
        private readonly LoggerInterface $logger,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('neos_page_path', $this->getNeosPagePath(...), ['needs_context' => true]),
            new TwigFunction('neos_page_url', $this->getNeosPageUrl(...)),
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

    /**
     * Builds the URL for an already-known Neos content path, without resolving it
     * through the page tree first. Use this over {@see getNeosPagePath()} whenever
     * the path is already at hand (e.g. from a NeosPageDTO), to avoid a redundant
     * tree lookup per call.
     */
    public function getNeosPageUrl(string $path): string
    {
        return $this->getSalesChannelBaseUrl() . '/' . ltrim($path, '/');
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

        return $this->getNeosPageUrl($pathInfo);
    }

    private function getSalesChannelBaseUrl(): string
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request) {
            return '';
        }

        $salesChannelBasePath = trim((string) $request->attributes->get(RequestTransformer::SALES_CHANNEL_BASE_URL), '/');

        return $salesChannelBasePath === '' ? '' : '/' . $salesChannelBasePath;
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
