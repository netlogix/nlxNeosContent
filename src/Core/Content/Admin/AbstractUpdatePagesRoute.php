<?php declare(strict_types=1);

namespace nlxNeosContent\Core\Content\Admin;

use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Autoconfigure(tags: ['controller.service_arguments'], public: true)]
abstract class AbstractUpdatePagesRoute
{
    abstract public function getDecorated(): AbstractUpdatePagesRoute;

    abstract public function load(Request $request, Context $context): Response;
}
