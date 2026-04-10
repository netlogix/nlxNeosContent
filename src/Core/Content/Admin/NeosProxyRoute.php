<?php

declare(strict_types=1);

namespace nlxNeosContent\Core\Content\Admin;

use Lcobucci\JWT\UnencryptedToken;
use nlxNeosContent\Service\TokenService;
use Psr\Http\Client\ClientInterface;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
class NeosProxyRoute extends AbstractNeosProxyRoute
{
    public function __construct(
        #[Autowire(service: 'nlx-neos-content.neos-client')]
        private ClientInterface $neosClient,
        private readonly TokenService $tokenService,
        private readonly KernelInterface $kernel
    ) {
    }

    public function getDecorated(): AbstractNeosProxyRoute
    {
        return $this;
    }

    //This is a generic proxy route for Neos requests. It expects a JSON body with an "action" field that specifies the Neos endpoint to call.
    #[Route(path: '/api/_action/neos/request', name: 'api.neos.request', methods: ['POST'])]
    public function load(Request $request, Context $context): Response
    {
        $requestBody = json_decode(json: $request->getContent(), associative: true) ?? [];
        if (!isset($requestBody['action'])) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Missing "action" in request body.',
                'data' => $requestBody,
            ], Response::HTTP_BAD_REQUEST);
        }
        if (!isset($requestBody['method'])) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Missing "method" in request body.',
                'data' => $requestBody,
            ], Response::HTTP_BAD_REQUEST);
        }

        $uri = sprintf('neos/shopware-api/%s', $requestBody['action']);
        $method = $requestBody['method'];
        if ($method === 'GET') {
            return $this->getRequest($uri, $context);
        } else {
            if (!isset($requestBody['payload'])) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Missing "payload" in request body for non-GET request.',
                    'data' => $requestBody,
                ], Response::HTTP_BAD_REQUEST);
            }

            if (!$request->attributes->has('oauth_user_id')) {
                return new JsonResponse(['error' => 'User ID not found'], Response::HTTP_BAD_REQUEST);
            }

            $clientId = $request->attributes->get('oauth_client_id');
            $userId = $request->attributes->get('oauth_user_id');
            $expiration = new \DateTimeImmutable('+1 minutes');
            $token = $this->tokenService->generateNeosToken($userId, $clientId, $expiration, $context);

            $payload = [
                "data" => $requestBody['payload']
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
        $shopwareVersion = $this->kernel->getContainer()->getParameter('kernel.shopware_version');
        $response = $this->neosClient->post($uri, [
            'headers' => [
                'x-sw-language-id' => $context->getLanguageId(),
                'shopwareAccessToken' => $token->toString(),
                'shopwareVersion' => $shopwareVersion,
                'apiUrl' => EnvironmentHelper::getVariable('APP_URL'),
            ],
            'json' => $payload
        ]);

        $responseData = $response->getBody()->getContents();
        $response = json_decode($responseData, true);
        return new JsonResponse($response);
    }
}
