<?php

declare(strict_types=1);

namespace nlxNeosContent\Core\Content\Admin\ValueResolver;

use nlxNeosContent\Service\TokenService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

readonly class NeosTokenValueResolver implements ValueResolverInterface
{
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
