<?php

declare(strict_types=1);

namespace nlxNeosContent\Service;

use DateTimeImmutable;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\UnencryptedToken;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

readonly class TokenService
{
    public function __construct(
        #[Autowire(service: 'shopware.jwt_config')]
        private Configuration $configuration,
        private PluginInfoService $pluginInfoService
    )
    {}

    public function generateNeosToken(string $userId, string $clientId, DateTimeImmutable $expiration, Context $context): UnencryptedToken
    {
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
}
