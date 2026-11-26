<?php

declare(strict_types=1);

namespace nlxNeosContent\Core\Content\Admin\ApiRoutes\NeosProxy;

use Lcobucci\JWT\UnencryptedToken;
use nlxNeosContent\Core\Content\Admin\Dto\NeosLayoutRequestDto;
use nlxNeosContent\Core\Content\Admin\ValueResolver\NeosTokenValueResolver;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Attribute\ValueResolver;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Admin API routes that forward a fixed request to Neos. The Neos paths are hardcoded, so callers
 * can't reach anything else on the Neos host.
 */
#[Route(defaults: ['_routeScope' => ['api']])]
class NeosProxyRoute extends AbstractNeosProxyRoute
{
    public function __construct(
        #[Autowire(service: 'nlx-neos-content.neos-client')]
        private readonly HttpClientInterface $neosClient,
        #[Autowire(param: 'kernel.shopware_version')]
        private readonly string $shopwareVersion,
    ) {
    }

    public function getDecorated(): AbstractNeosProxyRoute
    {
        return $this;
    }

    // category:read, because the category tree loads it for users without a Neos role, too.
    #[Route(path: '/api/_action/neos/page-tree', name: 'api.neos.page-tree', defaults: ['_acl' => ['category:read']], methods: ['GET'])]
    public function loadPageTree(Context $context): Response
    {
        return $this->relay(fn () => $this->neosClient->request('GET', 'neos/shopware-api/pagetree', [
            'headers' => [
                'x-sw-language-id' => $context->getLanguageId(),
            ],
        ]));
    }

    #[Route(path: '/api/_action/neos/layout', name: 'api.neos.layout', defaults: ['_acl' => ['neos:edit']], methods: ['POST'])]
    public function createLayout(
        #[MapRequestPayload(acceptFormat: 'json')]
        NeosLayoutRequestDto $layoutRequest,
        #[ValueResolver(resolver: NeosTokenValueResolver::RESOLVER_NAME)]
        UnencryptedToken $token,
        Context $context
    ): Response {
        return $this->relay(fn () => $this->neosClient->request('POST', 'neos/shopware-api/createPage', [
            'headers' => [
                'x-sw-language-id' => $context->getLanguageId(),
                'shopwareAccessToken' => $token->toString(),
                'shopwareVersion' => $this->shopwareVersion,
                'apiUrl' => EnvironmentHelper::getVariable('APP_URL'),
            ],
            'json' => [
                'data' => [
                    'title' => $layoutRequest->title,
                    'pageType' => $layoutRequest->pageType,
                ],
            ],
        ]));
    }

    /**
     * Client errors from Neos keep their status, everything else (server errors, redirects,
     * unreachable Neos, no JSON) becomes 502.
     *
     * @param \Closure(): ResponseInterface $request
     */
    private function relay(\Closure $request): JsonResponse
    {
        try {
            $response = $request();
            $statusCode = $response->getStatusCode();
            $data = json_decode($response->getContent(false), true);
        } catch (ExceptionInterface) {
            return new JsonResponse(['message' => 'Neos is not reachable'], Response::HTTP_BAD_GATEWAY);
        }

        if ($statusCode >= 400 && $statusCode < 500) {
            // A 401 would make the administration drop the user's own session.
            return new JsonResponse(
                is_array($data) ? $data : ['message' => 'Neos rejected the request'],
                $statusCode === Response::HTTP_UNAUTHORIZED ? Response::HTTP_FORBIDDEN : $statusCode
            );
        }

        if ($statusCode >= 300 || !is_array($data)) {
            return new JsonResponse(['message' => 'Unexpected response from Neos'], Response::HTTP_BAD_GATEWAY);
        }

        return new JsonResponse($data);
    }
}
