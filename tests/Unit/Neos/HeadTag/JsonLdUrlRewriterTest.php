<?php

declare(strict_types=1);

namespace nlxNeosContent\Tests\Unit\Neos\HeadTag;

use nlxNeosContent\Neos\HeadTag\JsonLdUrlRewriter;
use nlxNeosContent\Service\ConfigService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;

class JsonLdUrlRewriterTest extends TestCase
{
    public static function neosBaseUrls(): iterable
    {
        yield 'with Neos host' => ['https://neos.example'];
        yield 'without Neos host' => [''];
    }

    #[DataProvider('neosBaseUrls')]
    public function testClosingScriptTagCannotBreakOutOfTheScriptBlock(string $neosBaseUrl): void
    {
        $payload = '{"@type":"WebPage","name":"Sale <\/script><script>alert(1)<\/script> & more"}';

        $result = $this->rewriter($neosBaseUrl)->rewrite($payload, $this->domain(), null);

        static::assertIsString($result);
        static::assertStringNotContainsString('<', $result);
        static::assertStringNotContainsString('>', $result);
        static::assertSame(
            ['@type' => 'WebPage', 'name' => 'Sale </script><script>alert(1)</script> & more'],
            json_decode($result, true)
        );
    }

    public function testRewritesNeosUrlsToTheShopwareDomain(): void
    {
        $payload = '{"url":"https:\/\/neos.example\/de\/blog\/post","image":"https:\/\/cdn.example\/a.png"}';

        $result = $this->rewriter('https://neos.example')->rewrite($payload, $this->domain(), null);

        static::assertSame(
            '{"url":"https://shop.example/blog/post","image":"https://cdn.example/a.png"}',
            $result
        );
    }

    public function testReturnsNullForInvalidJson(): void
    {
        static::assertNull($this->rewriter('https://neos.example')->rewrite('not json', $this->domain(), null));
    }

    private function rewriter(string $neosBaseUrl): JsonLdUrlRewriter
    {
        $configService = $this->createMock(ConfigService::class);
        $configService->method('getBaseUrl')->willReturn($neosBaseUrl);

        return new JsonLdUrlRewriter($configService);
    }

    private function domain(): SalesChannelDomainEntity
    {
        $domain = new SalesChannelDomainEntity();
        $domain->setUrl('https://shop.example/');

        return $domain;
    }
}
