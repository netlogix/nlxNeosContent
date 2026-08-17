<?php

declare(strict_types=1);

namespace nlxNeosContent\Neos\HeadTag;

use nlxNeosContent\Service\ConfigService;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;

final readonly class JsonLdUrlRewriter
{
    public function __construct(
        private ConfigService $configService,
    ) {
    }

    public function rewrite(
        string $jsonLdPayload,
        SalesChannelDomainEntity $currentDomain,
        ?string $salesChannelId
    ): ?string {
        $decoded = json_decode($jsonLdPayload, true);
        if (!is_array($decoded)) {
            return null;
        }

        $neosHost = parse_url($this->configService->getBaseUrl($salesChannelId), PHP_URL_HOST);
        if ($neosHost === null) {
            return $jsonLdPayload;
        }

        $rewritten = $this->rewriteUrlsRecursively($decoded, $neosHost, rtrim($currentDomain->getUrl(), '/'));

        $encoded = json_encode($rewritten, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $encoded === false ? null : $encoded;
    }

    private function rewriteUrlsRecursively(mixed $data, string $neosHost, string $shopwareDomainUrl): mixed
    {
        if (is_array($data)) {
            return array_map(
                fn (mixed $value): mixed => $this->rewriteUrlsRecursively($value, $neosHost, $shopwareDomainUrl),
                $data
            );
        }

        if (is_string($data) && $this->isNeosUrl($data, $neosHost)) {
            return $this->toShopwareUrl($data, $shopwareDomainUrl);
        }

        return $data;
    }

    private function isNeosUrl(string $value, string $neosHost): bool
    {
        $host = parse_url($value, PHP_URL_HOST);

        return $host !== null && strcasecmp($host, $neosHost) === 0;
    }

    private function toShopwareUrl(string $neosUrl, string $shopwareDomainUrl): string
    {
        $path = (string) (parse_url($neosUrl, PHP_URL_PATH) ?? '');
        $segments = array_values(array_filter(
            explode('/', $path),
            static fn (string $segment): bool => $segment !== ''
        ));

        array_shift($segments);

        return $shopwareDomainUrl . '/' . implode('/', $segments);
    }
}
