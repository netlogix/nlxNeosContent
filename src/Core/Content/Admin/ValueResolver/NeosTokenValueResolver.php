<?php

declare(strict_types=1);

namespace nlxNeosContent\Core\Content\Admin\ValueResolver;

use nlxNeosContent\Service\TokenService;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsTargetedValueResolver;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

#[AsTargetedValueResolver(self::RESOLVER_NAME)]
readonly class NeosTokenValueResolver implements ValueResolverInterface
{
    public const RESOLVER_NAME = 'neos_token';

    function __construct(
        private TokenService $tokenService
    ) {
    }

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        // Neos signs in the Shopware user the token belongs to, so integrations without a user can't get one.
        if (!is_string($request->attributes->get(PlatformRequest::ATTRIBUTE_OAUTH_USER_ID))) {
            throw new AccessDeniedHttpException('A Neos token requires a request authenticated as a Shopware user account, not as an integration.');
        }

        $expiration = new \DateTimeImmutable('+5 minutes');
        return [
            $this->tokenService->generateFromRequest(
                $request,
                $expiration
            )
        ];
    }
}
