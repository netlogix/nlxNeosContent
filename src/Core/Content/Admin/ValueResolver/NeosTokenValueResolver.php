<?php

declare(strict_types=1);

namespace nlxNeosContent\Core\Content\Admin\ValueResolver;

use nlxNeosContent\Service\TokenService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsTargetedValueResolver;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

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
        $expiration = new \DateTimeImmutable('+5 minutes');
        return [
            $this->tokenService->generateFromRequest(
                $request,
                $expiration
            )
        ];
    }
}
