<?php

declare(strict_types=1);

namespace netlogixNeosContent\Subscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\System\SalesChannel\Event\SalesChannelProcessCriteriaEvent;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(name: 'kernel.event_subscriber')]
class SalesChannelProcessCriteriaSubscriber implements EventSubscriberInterface
{
    public function __construct()
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'sales_channel.product.process.criteria' => 'onSalesChannelProcessCriteria',
        ];
    }

    public function onSalesChannelProcessCriteria(SalesChannelProcessCriteriaEvent $salesChannelProcessCriteriaEvent): void
    {
        $criteria = $salesChannelProcessCriteriaEvent->getCriteria();
        $title = $criteria->getTitle() ?? '';
        if(!str_starts_with($title, 'cms-block-criteria')) {
            return;
        }

        $filters = $criteria->getFilters();
        $filters = array_filter($filters, function ($item) {
            if (!($item instanceof ProductAvailableFilter)) {
                return true;
            }
            return false;
        });
        $criteria->resetFilters();
        $criteria->addFilter(...$filters);
    }
}
