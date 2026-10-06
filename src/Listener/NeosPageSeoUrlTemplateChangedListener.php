<?php

declare(strict_types=1);

namespace nlxNeosContent\Listener;

use nlxNeosContent\Service\CachingInvalidationService;
use nlxNeosContent\Service\ConfigService;
use Shopware\Core\System\SystemConfig\Event\SystemConfigChangedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * The SEO paths are rendered into the cached page tree, so a changed template only takes
 * effect once that tree and everything rendered from it (navigation, Neos pages) is dropped.
 */
#[AsEventListener]
readonly class NeosPageSeoUrlTemplateChangedListener
{
    public function __construct(
        private CachingInvalidationService $cachingInvalidationService,
    ) {
    }

    public function __invoke(SystemConfigChangedEvent $event): void
    {
        if ($event->getKey() !== ConfigService::NEOS_PAGE_SEO_URL_TEMPLATE_KEY) {
            return;
        }

        $this->cachingInvalidationService->invalidateNavigationCaches();
        $this->cachingInvalidationService->invalidateNeosPageCaches();
    }
}
