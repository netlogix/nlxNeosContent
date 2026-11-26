<?php
declare(strict_types=1);

namespace nlxNeosContent\Core\Content\Admin\ApiRoutes\NeosProxy;

use Lcobucci\JWT\UnencryptedToken;
use nlxNeosContent\Core\Content\Admin\Dto\NeosLayoutRequestDto;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\HttpFoundation\Response;

#[Autoconfigure(tags: ['controller.service_arguments'], public: true)]
abstract class AbstractNeosProxyRoute
{
    abstract public function getDecorated(): AbstractNeosProxyRoute;

    abstract public function loadPageTree(Context $context): Response;

    abstract public function createLayout(
        NeosLayoutRequestDto $layoutRequest,
        UnencryptedToken $token,
        Context $context
    ): Response;
}
