<?php

declare(strict_types=1);

namespace nlxNeosContent\Core\Content\Admin;

use DateTimeInterface;
use Lcobucci\JWT\Configuration;
use nlxNeosContent\Service\PluginInfoService;
use nlxNeosContent\Service\TokenService;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
class NeosTokenRoute extends AbstractNeosTokenRoute
{
    public function __construct(
        private readonly TokenService $tokenService,
    ) {
    }

    public function getDecorated(): AbstractNeosTokenRoute
    {
        return $this;
    }

    #[Route(path: '/api/_action/neos/token', name: 'api.neos.token', methods: ['GET'])]
    public function load(Request $request, Context $context): Response
    {
        if (!$request->attributes->has('oauth_user_id')) {
            return new JsonResponse(['error' => 'User ID not found'], Response::HTTP_BAD_REQUEST);
        }

        $clientId = $request->attributes->get('oauth_client_id');
        $userId = $request->attributes->get('oauth_user_id');

        $expiration = new \DateTimeImmutable('+10 minutes');
        $token = $this->tokenService->generateNeosToken($userId, $clientId, $expiration, $context);

        return new JsonResponse([
            'token' => $token->toString(),
            'expires' => $expiration->format(DateTimeInterface::ATOM),
        ]);
    }
}
