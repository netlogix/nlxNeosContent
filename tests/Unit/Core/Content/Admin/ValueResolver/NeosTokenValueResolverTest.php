<?php

declare(strict_types=1);

namespace nlxNeosContent\Tests\Unit\Core\Content\Admin\ValueResolver;

use nlxNeosContent\Core\Content\Admin\ValueResolver\NeosTokenValueResolver;
use nlxNeosContent\Service\TokenService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class NeosTokenValueResolverTest extends TestCase
{
    public function testRequestWithoutUserIsDenied(): void
    {
        $tokenService = $this->createMock(TokenService::class);
        $tokenService->expects(static::never())->method('generateFromRequest');

        $this->expectException(AccessDeniedHttpException::class);

        (new NeosTokenValueResolver($tokenService))->resolve(
            new Request(),
            new ArgumentMetadata('token', null, false, false, null)
        );
    }
}
