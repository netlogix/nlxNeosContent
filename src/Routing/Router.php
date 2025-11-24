<?php

declare(strict_types=1);

namespace nlxNeosContent\Routing;

use nlxNeosContent\Storefront\Controller\NeosPageController;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

#[AsDecorator('router')]
class Router implements RouterInterface, WarmableInterface
{
    function __construct(
        #[AutowireDecorated]
        private readonly RouterInterface $inner
    ) {
    }

    public function setContext(RequestContext $context): void
    {
        $this->inner->setContext($context);
    }

    public function getContext(): RequestContext
    {
        return $this->inner->getContext();
    }

    public function getRouteCollection(): RouteCollection
    {
        return $this->inner->getRouteCollection();
    }

    public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
    {
        return $this->inner->generate($name, $parameters, $referenceType);
    }

    public function match(string $pathinfo): array
    {
        try {
            return $this->inner->match($pathinfo);
        } catch (\Exception $e) {
            try {
                return $this->matchNeosPath($pathinfo);
            } catch (\Exception) {
            }

            throw $e;
        }
    }

    protected function matchNeosPath(string $pathinfo): array
    {

        return [
            'neos' => 1,
            '_routeScope' => ['storefront'],
            '_controller'=> NeosPageController::class.'::index',
        ];
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        return $this->inner->warmUp($cacheDir, $buildDir);
    }
}
