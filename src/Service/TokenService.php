<?php

declare(strict_types=1);

namespace nlxNeosContent\Service;

use DateTimeImmutable;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\UnencryptedToken;
use Shopware\Core\Framework\Context;
use Shopware\Core\PlatformRequest;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;

readonly class TokenService
{
    public function __construct(
        #[Autowire(service: 'shopware.jwt_config')]
        private Configuration $configuration,
        private PluginInfoService $pluginInfoService
    ) {
    }

    public function generateNeosToken(
        string $userId,
        string $clientId,
        DateTimeImmutable $expiration,
        Context $context
    ): UnencryptedToken {
        $builder = $this->configuration
            ->builder()
            ->identifiedBy('neos-token-' . $userId . '-' . $clientId)
            ->issuedAt(new DateTimeImmutable())
            ->canOnlyBeUsedAfter(new DateTimeImmutable())
            ->expiresAt($expiration);

        try {
            $pluginVersion = $this->pluginInfoService->getVersion('nlxNeosContent', $context);
        } catch (\RuntimeException $e) {
            throw new \RuntimeException('Could not retrieve plugin version: ' . $e->getMessage());
        }

        $builder = $builder->permittedFor($clientId);
        $builder = $builder->withClaim('scopes', ['neos']);
        $builder = $builder->withClaim('plugin_version', $pluginVersion);
        $builder = $builder->relatedTo($userId);

        return $builder->getToken($this->configuration->signer(), $this->configuration->signingKey());
    }

    public function generateFromRequest(
        Request $request,
        DateTimeImmutable $expiration,
        ?Context $context = null
    ): UnencryptedToken {
        $attributes = $request->attributes;
        $clientId = $attributes->get(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID);
        $userId = $attributes->get(PlatformRequest::ATTRIBUTE_OAUTH_USER_ID);
        $context ??= $attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);

        assert($context instanceof Context);

        return $this->generateNeosToken(
            $userId,
            $clientId,
            $expiration,
            $context
        );
    }
}
