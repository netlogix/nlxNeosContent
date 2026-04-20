<?php

declare(strict_types=1);

namespace nlxNeosContent\Core\Content\Admin;

use Lcobucci\JWT\UnencryptedToken;
use nlxNeosContent\Core\Content\Admin\Dto\NeosProxyRequestDto;
use nlxNeosContent\Core\Content\Admin\ValueResolver\NeosTokenValueResolver;
use Psr\Http\Client\ClientInterface;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Attribute\ValueResolver;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
class NeosProxyRoute extends AbstractNeosProxyRoute
{
    public function __construct(
        #[Autowire(service: 'nlx-neos-content.neos-client')]
        private readonly ClientInterface $neosClient,
        #[Autowire(param: 'kernel.shopware_version')]
        private readonly string $shopwareVersion,
    ) {
    }

    public function getDecorated(): AbstractNeosProxyRoute
    {
        return $this;
    }

    //This is a generic proxy route for Neos requests. It expects a JSON body with an "action" field that specifies the Neos endpoint to call.
    #[Route(path: '/api/_action/neos/request', name: 'api.neos.request', methods: ['POST'])]
    public function load(
        #[MapRequestPayload(acceptFormat: 'json')]
        NeosProxyRequestDto $neosProxyRequest,
        #[ValueResolver(resolver: NeosTokenValueResolver::RESOLVER_NAME)]
        UnencryptedToken $token,
        Context $context
    ): Response {
        $uri = sprintf('neos/shopware-api/%s', $neosProxyRequest->getAction());

        if ($neosProxyRequest->getMethod() === 'GET') {
            return $this->getRequest($uri, $context);
        } else {
            $payload = [
                "data" => $neosProxyRequest->getPayload()
            ];

            return $this->postRequest($uri, $payload, $token, $context);
        }
    }

    private function getRequest(string $uri, Context $context): JsonResponse
    {
        $response = $this->neosClient->get($uri, [
            'headers' => [
                'x-sw-language-id' => $context->getLanguageId(),
            ]
        ]);

        $responseData = $response->getBody()->getContents();
        $response = json_decode($responseData, true);
        return new JsonResponse($response);
    }

    private function postRequest(string $uri, array $payload, UnencryptedToken $token, Context $context): JsonResponse
    {
        $response = $this->neosClient->post($uri, [
            'headers' => [
                'x-sw-language-id' => $context->getLanguageId(),
                'shopwareAccessToken' => $token->toString(),
                'shopwareVersion' => $this->shopwareVersion,
                'apiUrl' => EnvironmentHelper::getVariable('APP_URL'),
            ],
            'json' => $payload
        ]);

        $responseData = $response->getBody()->getContents();
        $response = json_decode($responseData, true);
        return new JsonResponse($response);
    }
}
