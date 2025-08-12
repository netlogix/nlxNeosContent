<?php

declare(strict_types=1);

namespace netlogixNeosContent\Core\Content\Admin;

use DateTimeInterface;
use Lcobucci\JWT\Configuration;
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
        #[Autowire(service: 'shopware.jwt_config')]
        private readonly Configuration $configuration
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

        $builder = $this->configuration
            ->builder()
            ->identifiedBy('neos-token-' . $userId . '-' . $clientId)
            ->issuedAt(new \DateTimeImmutable())
            ->canOnlyBeUsedAfter(new \DateTimeImmutable())
            ->expiresAt($expiration);

        $builder = $builder->permittedFor($clientId);
        $builder = $builder->withClaim('scopes', ['neos']);
        $builder = $builder->relatedTo($userId);

        return new JsonResponse([
            'token' => $builder->getToken($this->configuration->signer(), $this->configuration->signingKey())->toString(),
            'expires' => $expiration->format(DateTimeInterface::ATOM),
        ]);
    }
}
