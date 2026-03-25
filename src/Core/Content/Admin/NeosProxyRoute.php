<?php

declare(strict_types=1);

namespace nlxNeosContent\Core\Content\Admin;

use Psr\Http\Client\ClientInterface;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
class NeosProxyRoute extends AbstractNeosProxyRoute
{
    public function __construct(
        #[Autowire(service: 'nlx-neos-content.neos-client')]
        private ClientInterface $neosClient,
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

        $uri = sprintf('neos/shopware-api/%s', $requestBody['action']);
        $response = $this->neosClient->get($uri, [
            'headers' => [
                'x-sw-language-id' => $context->getLanguageId(),
            ]
        ]);

        $responseData = $response->getBody()->getContents();
        $response = json_decode($responseData, true);
        return new JsonResponse([
            'success' => true,
            'response' => $response
        ]);
    }
}
