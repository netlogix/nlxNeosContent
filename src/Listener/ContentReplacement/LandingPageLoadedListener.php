<?php

declare(strict_types=1);

namespace nlxNeosContent\Listener\ContentReplacement;

use nlxNeosContent\Service\ContentExchangeService;
use Shopware\Storefront\Page\LandingPage\LandingPageLoadedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
class LandingPageLoadedListener
{

    public function __construct(
        private readonly ContentExchangeService $contentExchangeService,
    ) {
    }

    public function __invoke(LandingPageLoadedEvent $landingPageLoadedEvent): void
    {
        $page = $landingPageLoadedEvent->getPage();
        if (!$page->hasExtension('nlxNeosNode')) {
            return;
        }
        $landingPage = $page->getLandingPage();
        $cmsPageEntity = $landingPage->getCmsPage();

        $salesChannelContext = $landingPageLoadedEvent->getSalesChannelContext();

        $newSections = $this->contentExchangeService->getAlternativeCmsSectionsFromNeos(
            $cmsPageEntity,
            $salesChannelContext
        );

        $cmsPageEntity->setSections($newSections);
    }
}
