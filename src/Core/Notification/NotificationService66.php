<?php

declare(strict_types=1);

namespace nlxNeosContent\Core\Notification;


use Shopware\Administration\Notification\NotificationService as ShopwareNotificationService;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class NotificationService66
    extends ShopwareNotificationService
    implements NotificationServiceInterface
{

}
