<?php declare(strict_types=1);

namespace nlxNeosContent\Core\Content\Admin\ApiRoutes\CacheInvalidation;

use nlxNeosContent\Core\Content\Admin\Dto\CacheInvalidationDto;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\HttpFoundation\Response;

#[Autoconfigure(tags: ['controller.service_arguments'], public: true)]
abstract class AbstractCacheInvalidationRoute
{
    abstract public function getDecorated(): AbstractCacheInvalidationRoute;

    abstract public function load(
        CacheInvalidationDto $cacheInvalidationDto,
        Context $context
    ): Response;
}
