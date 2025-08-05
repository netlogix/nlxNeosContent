<?php

declare(strict_types=1);

namespace netlogixNeosContent\Core\Framework\Api\OAuth;

use League\OAuth2\Server\Entities\ScopeEntityInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('shopware.oauth.scope')]
class NeosScopeEntity implements ScopeEntityInterface
{
    final public const IDENTIFIER = 'neos';

    /**
     * @return string
     */
    public function getIdentifier()
    {
        return self::IDENTIFIER;
    }

    public function jsonSerialize(): mixed
    {
        return self::IDENTIFIER;
    }

    public static function hasNeosScope(array $scopes): bool
    {
        foreach ($scopes as $scope) {
            if ($scope instanceof NeosScopeEntity) {
                return true;
            }
        }

        return false;
    }
}
