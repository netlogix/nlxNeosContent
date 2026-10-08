<?php

declare(strict_types=1);

namespace nlxNeosContent\Tests\Service;

use nlxNeosContent\Neos\DTO\NeosResults\NeosContentResult;
use nlxNeosContent\Service\ConfigService;
use nlxNeosContent\Service\ContentExchangeService;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionCollection;
use Shopware\Core\Content\Cms\DataResolver\CmsSlotsDataResolver;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class ContentExchangeServiceTest extends TestCase
{
    /**
     * @var array<array{method: string, url: string, options: array<string, mixed>}>
     */
    private array $requests = [];

    public function testForwardsQueryStringAndPublicRequestUri(): void
    {
        $request = Request::create('https://shop.example/neos-cms/contact?ContactID=5&utm_source=mail');
        $request->attributes->set(RequestTransformer::ORIGINAL_REQUEST_URI, '/de/contact/?ContactID=5&utm_source=mail');

        $this->service(new MockResponse('{}', ['response_headers' => ['content-type' => 'application/json']]))
            ->fetchCmsSectionsFromNeosByPath('/contact/', $this->salesChannelContext(), $request);

        $sent = $this->requests[0];
        static::assertSame('https://neos.example/neos/shopware-api/content-by-path/contact?ContactID=5&utm_source=mail', $sent['url']);
        static::assertContains(
            'x-sw-request-uri: https://shop.example/de/contact/?ContactID=5&utm_source=mail',
            $sent['options']['headers']
        );
    }

    public function testSendsNoRequestUriWithoutStorefrontRequest(): void
    {
        $this->service(new MockResponse('{}', ['response_headers' => ['content-type' => 'application/json']]))
            ->fetchCmsSectionsFromNeosByPath('/contact/', $this->salesChannelContext());

        $sent = $this->requests[0];
        static::assertSame('https://neos.example/neos/shopware-api/content-by-path/contact', $sent['url']);
        foreach ($sent['options']['headers'] as $header) {
            static::assertStringStartsNotWith('x-sw-request-uri', $header);
        }
    }

    public function testNoStoreResponseIsNotCacheable(): void
    {
        $result = $this->service(new MockResponse('{}', [
            'response_headers' => ['content-type' => 'application/json', 'cache-control' => 'private, no-store'],
        ]))->fetchCmsSectionsFromNeosByPath('/contact/', $this->salesChannelContext());

        static::assertInstanceOf(NeosContentResult::class, $result);
        static::assertFalse($result->isCacheable());
    }

    public function testResponseWithoutNoStoreIsCacheable(): void
    {
        $result = $this->service(new MockResponse('{}', [
            'response_headers' => ['content-type' => 'application/json', 'cache-control' => 'no-cache'],
        ]))->fetchCmsSectionsFromNeosByPath('/contact/', $this->salesChannelContext());

        static::assertInstanceOf(NeosContentResult::class, $result);
        static::assertTrue($result->isCacheable());
    }

    private function service(MockResponse $response): ContentExchangeService
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options) use ($response) {
            $this->requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

            return $response;
        }, 'https://neos.example');

        $serializer = $this->createMock(DenormalizerInterface::class);
        $serializer->method('denormalize')->willReturn(new NeosContentResult(new CmsSectionCollection()));

        return new ContentExchangeService(
            $serializer,
            $this->createMock(CmsSlotsDataResolver::class),
            $client,
            $this->createMock(TagAwareCacheInterface::class),
            $this->createMock(ConfigService::class),
        );
    }

    private function salesChannelContext(): SalesChannelContext
    {
        $domain = new SalesChannelDomainEntity();
        $domain->setId('domain-id');
        $domain->setUrl('https://shop.example/de');

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setDomains(new SalesChannelDomainCollection([$domain]));
        $salesChannel->setAccessKey('access-key');

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannel')->willReturn($salesChannel);
        $context->method('getDomainId')->willReturn('domain-id');
        $context->method('getLanguageId')->willReturn('language-id');
        $context->method('getSalesChannelId')->willReturn('sales-channel-id');

        return $context;
    }
}
