<?php

declare(strict_types=1);

namespace nlxNeosContent\Core\Notification;


use Shopware\Core\Framework\Notification\NotificationService as ShopwareNotificationService;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class NotificationService
    extends ShopwareNotificationService
    implements NotificationServiceInterface
{

}
