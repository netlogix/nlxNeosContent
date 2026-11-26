<?php

declare(strict_types=1);

namespace nlxNeosContent\Tests\Unit\Core\Content\Admin\ApiRoutes;

use Lcobucci\JWT\UnencryptedToken;
use nlxNeosContent\Core\Content\Admin\ApiRoutes\NeosProxy\NeosProxyRoute;
use nlxNeosContent\Core\Content\Admin\Dto\NeosLayoutRequestDto;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class NeosProxyRouteTest extends TestCase
{
    /**
     * @var array<array{method: string, url: string, options: array<string, mixed>}>
     */
    private array $requests = [];

    public function testPageTreeIsLoadedFromTheFixedNeosPath(): void
    {
        $response = $this->route(new MockResponse('{"pages":[{"label":"Home"}]}'))
            ->loadPageTree(Context::createDefaultContext());

        static::assertSame(200, $response->getStatusCode());
        static::assertSame(['pages' => [['label' => 'Home']]], json_decode((string) $response->getContent(), true));
        static::assertSame('GET', $this->requests[0]['method']);
        static::assertSame('https://neos.example/neos/shopware-api/pagetree', $this->requests[0]['url']);
        static::assertContains('x-sw-language-id: ' . Context::createDefaultContext()->getLanguageId(), $this->requests[0]['options']['headers']);
    }

    public function testLayoutIsCreatedWithOnlyTitleAndPageType(): void
    {
        $token = $this->createMock(UnencryptedToken::class);
        $token->method('toString')->willReturn('neos-jwt');

        $response = $this->route(new MockResponse('{"success":true,"cmsPageId":"abc"}'))
            ->createLayout(new NeosLayoutRequestDto('Shoes', 'product_list'), $token, Context::createDefaultContext());

        static::assertSame(['success' => true, 'cmsPageId' => 'abc'], json_decode((string) $response->getContent(), true));
        static::assertSame('POST', $this->requests[0]['method']);
        static::assertSame('https://neos.example/neos/shopware-api/createPage', $this->requests[0]['url']);
        static::assertSame('{"data":{"title":"Shoes","pageType":"product_list"}}', $this->requests[0]['options']['body']);
        static::assertContains('shopwareaccesstoken: neos-jwt', array_map('strtolower', $this->requests[0]['options']['headers']));
    }

    public static function neosErrors(): iterable
    {
        yield 'client error with JSON keeps status and body' => [new MockResponse('{"message":"Title is required"}', ['http_code' => 400]), 400, ['message' => 'Title is required']];
        yield 'client error without JSON keeps status' => [new MockResponse('<html/>', ['http_code' => 404]), 404, ['message' => 'Neos rejected the request']];
        yield 'unauthorized becomes forbidden' => [new MockResponse('', ['http_code' => 401]), 403, ['message' => 'Neos rejected the request']];
        yield 'server error' => [new MockResponse('{"message":"boom"}', ['http_code' => 500]), 502, ['message' => 'Unexpected response from Neos']];
        yield 'no JSON' => [new MockResponse('<html/>'), 502, ['message' => 'Unexpected response from Neos']];
        yield 'unreachable' => [new MockResponse('', ['error' => 'Connection refused']), 502, ['message' => 'Neos is not reachable']];
    }

    /**
     * @param array<string, string> $expectedBody
     */
    #[DataProvider('neosErrors')]
    public function testNeosErrorsAreMapped(MockResponse $neosResponse, int $expectedStatus, array $expectedBody): void
    {
        $response = $this->route($neosResponse)->loadPageTree(Context::createDefaultContext());

        static::assertSame($expectedStatus, $response->getStatusCode());
        static::assertSame($expectedBody, json_decode((string) $response->getContent(), true));
    }

    private function route(MockResponse $response): NeosProxyRoute
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options) use ($response) {
            $this->requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

            return $response;
        }, 'https://neos.example');

        return new NeosProxyRoute($client, '6.7.0.0');
    }
}
