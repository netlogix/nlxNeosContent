<?php

declare(strict_types=1);

namespace nlxNeosContent\Routing;

use nlxNeosContent\Service\ConfigService;
use nlxNeosContent\Service\NeosPageTreeService;
use nlxNeosContent\Storefront\Controller\NeosPageController;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

#[AsDecorator('router')]
readonly class Router implements RouterInterface, WarmableInterface
{
    /**
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     */
    function __construct(
        #[AutowireDecorated]
        private readonly RouterInterface $inner,
        #[Autowire(service: ConfigService::class)]
        private readonly ConfigService $configService,
        private readonly RequestStack $requestStack,
        private readonly NeosPageTreeService $neosPageTreeService,
        private readonly EntityRepository $salesChannelRepository,
    ) {
    }

    public function setContext(RequestContext $context): void
    {
        $this->inner->setContext($context);
    }

    public function getContext(): RequestContext
    {
        return $this->inner->getContext();
    }

    public function getRouteCollection(): RouteCollection
    {
        return $this->inner->getRouteCollection();
    }

    public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
    {
        return $this->inner->generate($name, $parameters, $referenceType);
    }

    public function match(string $pathinfo): array
    {
        try {
            return $this->inner->match($pathinfo);
        } catch (\Exception $e) {
            // Deliberately not also checking isNavigationExtensionEnabled() here: without a
            // request in scope yet, we can't know which sales channel it'd need to be checked
            // for, and checking the global default would wrongly block/allow every channel
            // the same way regardless of its own override. resolveCandidateTreeArgs() checks
            // it per candidate sales channel instead - the only place that's actually correct.
            if (!$this->configService->isEnabled()) {
                throw $e;
            }
            try {
                return $this->matchNeosPath($pathinfo);
            } catch (\Exception) {
            }

            throw $e;
        }
    }

    protected function matchNeosPath(string $pathinfo): array
    {
        // Throws NoTreeItemFoundException when no candidate's tree contains the path,
        // which bubbles up to match()'s catch block and falls back to the original
        // routing exception.
        $this->neosPageTreeService->searchForPathInPageTrees($pathinfo, $this->resolveCandidateTreeArgs());

        // Delegate to the real, registered route (the same one NeosAwareSeoResolver rewrites a
        // stale alias to) instead of hand-building a route array that would otherwise have to
        // be kept in sync with whatever that route actually declares.
        return $this->inner->match(NeosPageController::CONTENT_BY_PATH_ROUTE_PREFIX . ltrim($pathinfo, '/'));
    }

    /**
     * @return iterable<array{string, string}> tuples of [salesChannelId, languageId]
     */
    private function resolveCandidateTreeArgs(): iterable
    {
        $request = $this->requestStack->getCurrentRequest();

        $salesChannelId = $request?->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID);
        $languageId = $request?->headers->get(PlatformRequest::HEADER_LANGUAGE_ID);

        if ($salesChannelId !== null && $languageId !== null) {
            // match()'s own gate only checks the global default, so a sales channel that
            // overrides extendNavigation to disabled must still be excluded here - same
            // per-channel check the enumeration fallback below already applies.
            if ($this->configService->isNavigationExtensionEnabled($salesChannelId)) {
                yield [$salesChannelId, $languageId];
            }

            return;
        }

        //No storefront request to read the sales channel/language from - e.g. when called
        //via RouteBlocklistService while validating a seo url from the Admin API. Check every
        //Neos-connected sales channel's languages instead of assuming a single one.
        $criteria = new Criteria();
        $criteria->addAssociation('domains');
        $criteria->addAssociation('type');
        $criteria->addFilter(new EqualsFilter('type.id', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));
        $criteria->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [new EqualsFilter('domains.id', null)]));

        $seen = [];

        /** @var SalesChannelEntity $salesChannel */
        foreach ($this->salesChannelRepository->search($criteria, Context::createDefaultContext())->getEntities() as $salesChannel) {
            if (!$this->configService->isNavigationExtensionEnabled($salesChannel->getId())) {
                continue;
            }

            foreach ($salesChannel->getDomains() ?? [] as $domain) {
                $key = $salesChannel->getId() . '-' . $domain->getLanguageId();
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                yield [$salesChannel->getId(), $domain->getLanguageId()];
            }
        }
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        if ($this->inner instanceof WarmableInterface) {
            return $this->inner->warmUp($cacheDir, $buildDir);
        }

        return [];
    }
}
