<?php

declare(strict_types=1);

namespace netlogixNeosContent\Core\Notification;

use Shopware\Core\Framework\Context;

/**
 * @deprecated
 * this is a workarround to be able to support both shopware ^6.6 and ^6.7 versions since the NotificationService
 * has been moved from Shopware/Administration to Shopware/core - will be removed when ^6.6 support ends
 */
interface NotificationServiceInterface
{
    public function createNotification(array $data, Context $context): void;

    public function getNotifications(Context $context, int $limit, ?string $latestTimestamp): array;
}
