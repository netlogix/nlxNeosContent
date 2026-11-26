<?php

declare(strict_types=1);

namespace nlxNeosContent\Tests\Unit\Service;

use nlxNeosContent\Error\RequestError\NeosContentFetchException;
use nlxNeosContent\Neos\DTO\NeosResults\NeosAssetResult;
use nlxNeosContent\Neos\DTO\NeosResults\NeosContentResult;
use nlxNeosContent\Neos\DTO\NeosResults\NeosRedirectResult;
use nlxNeosContent\Service\ConfigService;
use nlxNeosContent\Service\ContentExchangeService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionEntity;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Content\Cms\DataResolver\CmsSlotsDataResolver;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
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

    public static function traversingPaths(): iterable
    {
        yield 'parent segment' => ['../../../robots.txt'];
        yield 'parent segment in the middle' => ['/blog/../../../robots.txt'];
        yield 'current segment' => ['/./contact'];
        yield 'trailing parent segment' => ['/blog/..'];
        yield 'backslash' => ['/..\\..\\robots.txt'];
        yield 'invalid UTF-8 (overlong dot)' => ["/\xC0\xAE\xC0\xAE/robots.txt"];
        yield 'NUL byte' => ["/robots.txt\0.html"];
        yield 'line feed' => ["/blog\n/contact"];
        yield 'zero-width space between dots' => ["/.\u{200B}./robots.txt"];
        yield 'bidi override' => ["/blog\u{202E}txt.stobor"];
        yield 'fullwidth full stops' => ["/\u{FF0E}\u{FF0E}/robots.txt"];
        yield 'small full stop' => ["/\u{FE52}/contact"];
        yield 'one dot leader' => ["/\u{2024}\u{2024}/robots.txt"];
        yield 'two dot leader' => ["/\u{2025}/robots.txt"];
        yield 'ellipsis' => ["/\u{2026}/robots.txt"];
        yield 'fullwidth solidus' => ["/blog\u{FF0F}..\u{FF0F}robots.txt"];
        yield 'fullwidth reverse solidus' => ["/blog\u{FF3C}robots.txt"];
        yield 'small reverse solidus' => ["/blog\u{FE68}robots.txt"];
        yield 'three dots' => ['/.../robots.txt'];
        yield 'dots with trailing space' => ['/.. /robots.txt'];
    }

    public static function nonAsciiPaths(): iterable
    {
        yield 'umlaut' => ['/über-uns', '%C3%BCber-uns'];
        yield 'look-alike slash without NFKC mapping' => ["/a\u{2215}b", '%E2%88%95'];
        yield 'dot inside a segment' => ['/robots.txt', 'robots.txt'];
        yield 'double-encoded dots' => ['/%2e%2e/robots.txt', '%252e%252e/robots.txt'];
    }

    #[DataProvider('nonAsciiPaths')]
    public function testRegularPathsAreNotRejected(string $pathInfo, string $expectedUriPart): void
    {
        $this->service(new MockResponse('{}', ['response_headers' => ['content-type' => 'application/json']]))
            ->fetchCmsSectionsFromNeosByPath($pathInfo, $this->salesChannelContext());

        static::assertStringContainsString($expectedUriPart, $this->requests[0]['url']);
    }

    #[DataProvider('traversingPaths')]
    public function testPathTraversalIsRejectedWithoutRequest(string $pathInfo): void
    {
        try {
            $this->service(new MockResponse('{}'))->fetchCmsSectionsFromNeosByPath($pathInfo, $this->salesChannelContext());
            static::fail('Expected a NotFoundHttpException');
        } catch (NotFoundHttpException) {
        }

        static::assertSame([], $this->requests);
    }

    public function testPathTraversalIsRejectedForFormSubmits(): void
    {
        try {
            $this->service(new MockResponse('{}'))
                ->submitFormToNeosByPath('/../../form', Request::create('/x', 'POST', ['name' => 'x']), $this->salesChannelContext());
            static::fail('Expected a NotFoundHttpException');
        } catch (NotFoundHttpException) {
        }

        static::assertSame([], $this->requests);
    }

    public function testPathSegmentsAreEncoded(): void
    {
        $this->service(new MockResponse('{}', ['response_headers' => ['content-type' => 'application/json']]))
            ->fetchCmsSectionsFromNeosByPath('/über uns/a?b#c/..%2F/', $this->salesChannelContext());

        static::assertSame(
            'https://neos.example/neos/shopware-api/content-by-path/%C3%BCber%20uns/a%3Fb%23c/..%252F',
            $this->requests[0]['url']
        );
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

    public function testRedirectToTheHomepageIsRelayedAsRootPath(): void
    {
        $result = $this->service(new MockResponse('', [
            'http_code' => 301,
            'response_headers' => ['location' => 'https://neos.example/neos/shopware-api/content-by-path/'],
        ]))->fetchCmsSectionsFromNeosByPath('/old-page/', $this->salesChannelContext());

        static::assertInstanceOf(NeosRedirectResult::class, $result);
        static::assertSame('/', $result->getRedirectPathInfo());
    }

    public static function neosRedirects(): iterable
    {
        yield 'relative, content-by-path' => [301, '/neos/shopware-api/content-by-path/new-page', '/new-page'];
        yield 'absolute Neos host, content-by-path' => [302, 'https://neos.example/neos/shopware-api/content-by-path/blog/new-post', '/blog/new-post'];
        yield 'plain frontend path' => [301, 'https://neos.example/de/new-page', '/de/new-page'];
        yield 'external host' => [307, 'https://other.example/landing?x=1', 'https://other.example/landing?x=1'];
    }

    #[DataProvider('neosRedirects')]
    public function testRedirectIsRelayedWithItsStatusCode(int $statusCode, string $location, string $expectedPathInfo): void
    {
        $result = $this->service(new MockResponse('', [
            'http_code' => $statusCode,
            'response_headers' => ['location' => $location],
        ]))->fetchCmsSectionsFromNeosByPath('/old-page/', $this->salesChannelContext());

        static::assertInstanceOf(NeosRedirectResult::class, $result);
        static::assertSame($expectedPathInfo, $result->getRedirectPathInfo());
        static::assertSame($statusCode, $result->getStatusCode());
    }

    public function testRedirectAfterFormSubmitIsRelayed(): void
    {
        $result = $this->service(new MockResponse('', [
            'http_code' => 303,
            'response_headers' => ['location' => '/neos/shopware-api/content-by-path/contact/thank-you'],
        ]))->submitFormToNeosByPath('/contact/', Request::create('/contact/', 'POST', ['name' => 'x']), $this->salesChannelContext());

        static::assertInstanceOf(NeosRedirectResult::class, $result);
        static::assertSame('/contact/thank-you', $result->getRedirectPathInfo());
    }

    public static function neosClientErrors(): iterable
    {
        yield 'gone (redirect into another dimension)' => [410];
        yield 'not found' => [404];
    }

    #[DataProvider('neosClientErrors')]
    public function testClientErrorIsThrownWithItsStatusCode(int $statusCode): void
    {
        $this->expectException(ClientException::class);
        $this->expectExceptionCode($statusCode);

        $this->service(new MockResponse('', ['http_code' => $statusCode]))
            ->fetchCmsSectionsFromNeosByPath('/old-page/', $this->salesChannelContext());
    }

    public function testNonJsonResponseIsPassedThroughAsAsset(): void
    {
        $result = $this->service(new MockResponse('<svg/>', [
            'response_headers' => ['content-type' => 'image/svg+xml'],
        ]))->fetchCmsSectionsFromNeosByPath('/file.svg', $this->salesChannelContext());

        static::assertInstanceOf(NeosAssetResult::class, $result);
        static::assertSame('image/svg+xml', $result->getContentType());
    }

    public function testRedirectWithoutPathIsRejected(): void
    {
        $this->expectException(NeosContentFetchException::class);

        $this->service(new MockResponse('', [
            'http_code' => 301,
            'response_headers' => ['location' => 'https://neos.example'],
        ]))->fetchCmsSectionsFromNeosByPath('/old-page/', $this->salesChannelContext());
    }

    public function testLoadSectionDataSetsTheBackgroundMediaOfSections(): void
    {
        $media = new MediaEntity();
        $media->setId('media-1');
        $withImage = $this->section('section-1', 'media-1');
        $withoutImage = $this->section('section-2', null);

        $mediaRepository = $this->createMock(EntityRepository::class);
        $mediaRepository->expects(static::once())
            ->method('search')
            ->with(static::callback(fn (Criteria $criteria) => $criteria->getIds() === ['media-1']))
            ->willReturnCallback(fn (Criteria $criteria, Context $context) => new EntitySearchResult(
                'media', 1, new MediaCollection([$media]), null, $criteria, $context
            ));

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getContext')->willReturn(Context::createDefaultContext());

        $this->service(new MockResponse('{}'), $mediaRepository)->loadSectionData(
            new CmsSectionCollection([$withImage, $withoutImage]),
            new ResolverContext($salesChannelContext, new Request())
        );

        static::assertSame($media, $withImage->getBackgroundMedia());
        static::assertNull($withoutImage->getBackgroundMedia());
    }

    public function testLoadSectionDataSkipsTheMediaSearchWithoutBackgroundImages(): void
    {
        $mediaRepository = $this->createMock(EntityRepository::class);
        $mediaRepository->expects(static::never())->method('search');

        $this->service(new MockResponse('{}'), $mediaRepository)->loadSectionData(
            new CmsSectionCollection([$this->section('section-1', null)]),
            new ResolverContext($this->createMock(SalesChannelContext::class), new Request())
        );
    }

    private function section(string $id, ?string $backgroundMediaId): CmsSectionEntity
    {
        $section = new CmsSectionEntity();
        $section->setId($id);
        $section->setBlocks(new CmsBlockCollection());
        if ($backgroundMediaId) {
            $section->setBackgroundMediaId($backgroundMediaId);
        }

        return $section;
    }

    private function service(MockResponse $response, ?EntityRepository $mediaRepository = null): ContentExchangeService
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options) use ($response) {
            $this->requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

            return $response;
        }, 'https://neos.example');

        $serializer = $this->createMock(DenormalizerInterface::class);
        $serializer->method('denormalize')->willReturn(new NeosContentResult(new CmsSectionCollection()));

        $configService = $this->createMock(ConfigService::class);
        $configService->method('getBaseUrl')->willReturn('https://neos.example');

        return new ContentExchangeService(
            $serializer,
            $this->createMock(CmsSlotsDataResolver::class),
            $client,
            $this->createMock(TagAwareCacheInterface::class),
            $configService,
            $mediaRepository,
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
