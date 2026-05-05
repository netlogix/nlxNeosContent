<?php declare(strict_types=1);

namespace nlxNeosContent\Core\Content\Admin\ApiRoutes\UpdateNeosPages;

use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Autoconfigure(tags: ['controller.service_arguments'], public: true)]
abstract class AbstractUpdateNeosPagesRoute
{
    abstract public function getDecorated(): AbstractUpdateNeosPagesRoute;

    abstract public function load(Request $request, Context $context): Response;
}
