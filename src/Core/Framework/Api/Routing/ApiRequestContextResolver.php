<?php

declare(strict_types=1);

namespace netlogixNeosContent\Core\Framework\Api\Routing;

use netlogixNeosContent\Core\Framework\Api\OAuth\NeosScopeEntity;
use netlogixNeosContent\Service\NeosAuthorizationRoleService;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\RequestContextResolverInterface;
use Shopware\Core\PlatformRequest;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[AsDecorator(\Shopware\Core\Framework\Routing\ApiRequestContextResolver::class)]
class ApiRequestContextResolver implements RequestContextResolverInterface
{
    function __construct(
        #[AutowireDecorated]
        private readonly RequestContextResolverInterface $decorated
    ) {
    }

    public function resolve(Request $request): void
    {
        $this->decorated->resolve($request);

        if ($request->attributes->get('oauth_scopes') !== [NeosScopeEntity::IDENTIFIER]) {
            return;
        }

        /** @var Context $context */
        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);
        if ($context === null) {
            return;
        }

        $source = $context->getSource();
        if (!$source instanceof AdminApiSource) {
            return;
        }

        $privileges = NeosAuthorizationRoleService::getWhiteListPrivileges();
        $privileges = $source->isAdmin()
            ? $privileges
            : array_filter($privileges, fn ($p) => $source->isAllowed($p));

        $source->setIsAdmin(false);

        $source->setPermissions($privileges);
    }
}
