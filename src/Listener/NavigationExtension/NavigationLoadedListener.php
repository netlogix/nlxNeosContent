<?php

declare(strict_types=1);

namespace nlxNeosContent\Listener\NavigationExtension;

use nlxNeosContent\Factory\NeosPageTreeItemFactory;
use nlxNeosContent\Service\ConfigService;
use nlxNeosContent\Service\NeosPageTreeService;
use Shopware\Core\Content\Category\Event\NavigationLoadedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
readonly class NavigationLoadedListener
{
    public function __construct(
        private NeosPageTreeService $neosPageTreeService,
        private NeosPageTreeItemFactory $neosPageTreeItemFactory,
        private ConfigService $configService,
    ) {
    }

    public function __invoke(NavigationLoadedEvent $navigationLoadedEvent): void
    {
        if (
            !$this->configService->isEnabled() ||
            !$this->configService->isNavigationExtensionEnabled(
                $navigationLoadedEvent->getSalesChannelContext()->getSalesChannelId()
            )
        ) {
            return;
        }
        $navigation = $navigationLoadedEvent->getNavigation();
        $tree = $navigation->getTree();

        $pages = $this->neosPageTreeService->loadTreeForContext(
            $navigationLoadedEvent->getSalesChannelContext()
        );
        $pages = iterator_to_array($this->neosPageTreeItemFactory->create($pages));
        $navigation->setTree(array_merge($tree, $pages));
    }
}
