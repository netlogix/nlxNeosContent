<?php

declare(strict_types=1);

namespace nlxNeosContent\Error\Routing;

use nlxNeosContent\Error\NeosExceptionInterface;
use Shopware\Core\Framework\HttpException;
use Symfony\Component\HttpFoundation\Response;

class NeosRoutingException extends HttpException implements NeosExceptionInterface
{
    public const REDIRECT_URL_NOT_PARSABLE = 'NLX_NEOS_CONTENT__REDIRECT_URL_NOT_PARSABLE';
    public const SALES_CHANNEL_DOMAINS_NOT_LOADED = 'NLX_NEOS_CONTENT__SALES_CHANNEL_DOMAINS_NOT_LOADED';
    public const DOMAIN_NOT_FOUND_FOR_LANGUAGE = 'NLX_NEOS_CONTENT__DOMAIN_NOT_FOUND_FOR_LANGUAGE';

    public static function redirectUrlNotParsable(?string $url): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::REDIRECT_URL_NOT_PARSABLE,
            'Could not parse redirect URL "{{ url }}" returned by the context switch.',
            ['url' => $url]
        );
    }

    public static function salesChannelDomainsNotLoaded(string $salesChannelId, string $languageId): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::SALES_CHANNEL_DOMAINS_NOT_LOADED,
            'Sales channel "{{ salesChannelId }}" has no domains loaded; cannot resolve URL for language "{{ languageId }}".',
            ['salesChannelId' => $salesChannelId, 'languageId' => $languageId]
        );
    }

    public static function domainNotFoundForLanguage(string $languageId, string $salesChannelId): self
    {
        return new self(
            Response::HTTP_PRECONDITION_FAILED,
            self::DOMAIN_NOT_FOUND_FOR_LANGUAGE,
            'No domain configured for language "{{ languageId }}" in sales channel "{{ salesChannelId }}".',
            ['languageId' => $languageId, 'salesChannelId' => $salesChannelId]
        );
    }
}
