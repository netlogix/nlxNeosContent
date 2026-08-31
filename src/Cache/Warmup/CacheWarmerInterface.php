<?php

declare(strict_types=1);

namespace nlxNeosContent\Cache\Warmup;

use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface CacheWarmerInterface
{
    public function warmUp(SalesChannelContext $salesChannelContext): void;
}
