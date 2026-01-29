<?php

declare(strict_types=1);

namespace nlxNeosContent\Listener;

use nlxNeosContent\Factory\NeosPageTreeItemFactory;
use nlxNeosContent\Neos\Endpoint\AbstractNeosPageTreeLoader;
use nlxNeosContent\Service\ConfigService;
use Shopware\Core\Content\Category\Event\NavigationLoadedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
readonly class NavigationLoadedListener
{
    public function __construct(
        private AbstractNeosPageTreeLoader $neosPageTreeLoader,
        private NeosPageTreeItemFactory $neosPageTreeItemFactory,
        private ConfigService $configService,
    ) {
    }

    public function __invoke(NavigationLoadedEvent $navigationLoadedEvent): void
    {
        if ($this->configService->isEnabled()) {
            return;
        }
        $navigation = $navigationLoadedEvent->getNavigation();
        $tree = $navigation->getTree();

        $pages = $this->neosPageTreeLoader->load(
            $navigationLoadedEvent->getSalesChannelContext()->getSalesChannelId(),
            $navigationLoadedEvent->getSalesChannelContext()->getLanguageId(),

        );
        $pages = iterator_to_array($this->neosPageTreeItemFactory->create($pages));
        $navigation->setTree(array_merge($tree, $pages));
    }
}
