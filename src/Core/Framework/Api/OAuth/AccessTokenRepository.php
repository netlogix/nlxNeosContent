<?php

declare(strict_types=1);

namespace netlogixNeosContent\Core\Framework\Api\OAuth;

use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use Shopware\Core\Framework\Api\OAuth\AccessToken;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

#[AsDecorator(\Shopware\Core\Framework\Api\OAuth\AccessTokenRepository::class)]
class AccessTokenRepository implements AccessTokenRepositoryInterface
{
    function __construct(
        #[AutowireDecorated]
        private readonly AccessTokenRepositoryInterface $decorated
    ) {
    }

    public function getNewToken(ClientEntityInterface $clientEntity, array $scopes, $userIdentifier = null): AccessTokenEntityInterface
    {
        $swToken = $this->decorated->getNewToken($clientEntity, $scopes, $userIdentifier);
        if (!NeosScopeEntity::hasNeosScope($swToken->getScopes())) {
            return $swToken;
        }

        $token = new AccessToken(
            $swToken->getClient(),
            [new NeosScopeEntity()],
            $swToken->getUserIdentifier()
        );

        $token->setIdentifier($swToken->getIdentifier());

        return $token;
    }

    public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity): void
    {
        $this->decorated->persistNewAccessToken($accessTokenEntity);
    }

    public function revokeAccessToken($tokenId): void
    {
        $this->decorated->revokeAccessToken($tokenId);
    }

    public function isAccessTokenRevoked($tokenId): void
    {
        $this->decorated->isAccessTokenRevoked($tokenId);
    }
}
