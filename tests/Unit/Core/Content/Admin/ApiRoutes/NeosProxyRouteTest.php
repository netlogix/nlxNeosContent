<?php

declare(strict_types=1);

namespace nlxNeosContent\Tests\Unit\Core\Content\Admin\ApiRoutes;

use Lcobucci\JWT\UnencryptedToken;
use nlxNeosContent\Core\Content\Admin\ApiRoutes\NeosProxy\NeosProxyRoute;
use nlxNeosContent\Core\Content\Admin\Dto\NeosLayoutRequestDto;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
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

    /**
     * @var list<array{level: string, message: string, context: array<string, mixed>}>
     */
    private array $logs = [];

    public static function neosErrors(): iterable
    {
        yield 'client error with JSON' => [new MockResponse('{"message":"Title is required"}', ['http_code' => 400]), 400, LogLevel::WARNING, 'Neos rejected the request'];
        yield 'client error without JSON' => [new MockResponse('<html/>', ['http_code' => 404]), 404, LogLevel::WARNING, 'Neos rejected the request'];
        yield 'unauthorized becomes forbidden' => [new MockResponse('', ['http_code' => 401]), 403, LogLevel::WARNING, 'Neos rejected the request'];
        yield 'server error' => [new MockResponse('{"message":"boom"}', ['http_code' => 500]), 502, LogLevel::ERROR, 'Unexpected response from Neos'];
        yield 'no JSON' => [new MockResponse('<html/>'), 502, LogLevel::ERROR, 'Unexpected response from Neos'];
        yield 'unreachable' => [new MockResponse('', ['error' => 'Connection refused']), 502, LogLevel::ERROR, 'Neos is not reachable'];
    }

    #[DataProvider('neosErrors')]
    public function testNeosErrorsAreLoggedAndAnsweredWithACode(MockResponse $neosResponse, int $expectedStatus, string $expectedLevel, string $expectedReason): void
    {
        $response = $this->route($neosResponse)->loadPageTree(Context::createDefaultContext());

        $body = json_decode((string) $response->getContent(), true);
        static::assertSame($expectedStatus, $response->getStatusCode());
        static::assertSame(['message', 'code'], array_keys($body));
        static::assertSame('The request to Neos failed.', $body['message']);
        static::assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $body['code']);

        static::assertCount(1, $this->logs);
        static::assertSame($expectedLevel, $this->logs[0]['level']);
        static::assertSame(sprintf('Neos request failed [%s]: %s', $body['code'], $expectedReason), $this->logs[0]['message']);
        static::assertSame('neos/shopware-api/pagetree', $this->logs[0]['context']['neosPath']);
    }

    public function testFlowErrorPageIsLoggedWithReferenceCodeAndWithoutToken(): void
    {
        $token = $this->createMock(UnencryptedToken::class);
        $token->method('toString')->willReturn('neos-jwt');
        $errorPage = '<html><body><h1>Internal Server Error</h1><p>When contacting the maintainer of this application please mention the following reference code:<br /><br />20261126101010a1b2c3</p></body></html>';

        $this->route(new MockResponse($errorPage, ['http_code' => 500]))
            ->createLayout(new NeosLayoutRequestDto('Shoes', 'product_list'), $token, Context::createDefaultContext());

        $context = $this->logs[0]['context'];
        static::assertSame(500, $context['statusCode']);
        static::assertSame('20261126101010a1b2c3', $context['neosReferenceCode']);
        static::assertStringStartsWith('Internal Server Error When contacting', $context['responseExcerpt']);
        static::assertStringNotContainsString('neos-jwt', json_encode($context, JSON_THROW_ON_ERROR));
    }

    private function route(MockResponse $response): NeosProxyRoute
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options) use ($response) {
            $this->requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

            return $response;
        }, 'https://neos.example');

        $logger = new class($this->logs) extends AbstractLogger {
            /**
             * @param list<array{level: string, message: string, context: array<string, mixed>}> $logs
             */
            public function __construct(private array &$logs)
            {
            }

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->logs[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
            }
        };

        return new NeosProxyRoute($client, '6.7.0.0', $logger);
    }
}
