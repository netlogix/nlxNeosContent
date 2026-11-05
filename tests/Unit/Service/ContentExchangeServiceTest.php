<?php

declare(strict_types=1);

namespace nlxNeosContent\Tests\Unit\Service;

use nlxNeosContent\Neos\DTO\NeosResults\NeosContentResult;
use nlxNeosContent\Service\ConfigService;
use nlxNeosContent\Service\ContentExchangeService;
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

        return new ContentExchangeService(
            $serializer,
            $this->createMock(CmsSlotsDataResolver::class),
            $client,
            $this->createMock(TagAwareCacheInterface::class),
            $this->createMock(ConfigService::class),
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
