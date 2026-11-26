<?php

declare(strict_types=1);

namespace nlxNeosContent\Core\Content\Admin\ApiRoutes\NeosProxy;

use Lcobucci\JWT\UnencryptedToken;
use nlxNeosContent\Core\Content\Admin\Dto\NeosLayoutRequestDto;
use nlxNeosContent\Core\Content\Admin\ValueResolver\NeosTokenValueResolver;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Attribute\ValueResolver;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Admin API routes that forward a fixed request to Neos. The Neos paths are hardcoded, so callers
 * can't reach anything else on the Neos host.
 */
#[Route(defaults: ['_routeScope' => ['api']])]
class NeosProxyRoute extends AbstractNeosProxyRoute
{
    private const PAGE_TREE_PATH = 'neos/shopware-api/pagetree';

    private const CREATE_PAGE_PATH = 'neos/shopware-api/createPage';

    private const LOGGED_BODY_LENGTH = 1000;

    public function __construct(
        #[Autowire(service: 'nlx-neos-content.neos-client')]
        private readonly HttpClientInterface $neosClient,
        #[Autowire(param: 'kernel.shopware_version')]
        private readonly string $shopwareVersion,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getDecorated(): AbstractNeosProxyRoute
    {
        return $this;
    }

    // category:read, because the category tree loads it for users without a Neos role, too.
    #[Route(path: '/api/_action/neos/page-tree', name: 'api.neos.page-tree', defaults: ['_acl' => ['category:read']], methods: ['GET'])]
    public function loadPageTree(Context $context): Response
    {
        return $this->relay('GET', self::PAGE_TREE_PATH, [
            'headers' => [
                'x-sw-language-id' => $context->getLanguageId(),
            ],
        ]);
    }

    #[Route(path: '/api/_action/neos/layout', name: 'api.neos.layout', defaults: ['_acl' => ['neos:edit']], methods: ['POST'])]
    public function createLayout(
        #[MapRequestPayload(acceptFormat: 'json')]
        NeosLayoutRequestDto $layoutRequest,
        #[ValueResolver(resolver: NeosTokenValueResolver::RESOLVER_NAME)]
        UnencryptedToken $token,
        Context $context
    ): Response {
        return $this->relay('POST', self::CREATE_PAGE_PATH, [
            'headers' => [
                'x-sw-language-id' => $context->getLanguageId(),
                'shopwareAccessToken' => $token->toString(),
                'shopwareVersion' => $this->shopwareVersion,
                'apiUrl' => EnvironmentHelper::getVariable('APP_URL'),
            ],
            'json' => [
                'data' => [
                    'title' => $layoutRequest->title,
                    'pageType' => $layoutRequest->pageType,
                ],
            ],
        ]);
    }

    /**
     * Passes a successful JSON response through. Any other outcome is logged with the details from
     * Neos, while the administration only gets a generic message and the code of the log entry.
     * Client errors keep their status (401 becomes 403), everything else becomes 502.
     *
     * @param array<string, mixed> $options
     */
    private function relay(string $method, string $neosPath, array $options): JsonResponse
    {
        // Never log the request options, they contain the user's Neos token.
        $context = ['method' => $method, 'neosPath' => $neosPath];

        try {
            $response = $this->neosClient->request($method, $neosPath, $options);
            $statusCode = $response->getStatusCode();
            $content = $response->getContent(false);
        } catch (ExceptionInterface $e) {
            return $this->fail(Response::HTTP_BAD_GATEWAY, LogLevel::ERROR, 'Neos is not reachable', $context + ['exception' => $e]);
        }

        $data = json_decode($content, true);
        if ($statusCode >= 200 && $statusCode < 300 && is_array($data)) {
            return new JsonResponse($data);
        }

        $context += [
            'statusCode' => $statusCode,
            'neosReferenceCode' => $this->extractReferenceCode($content),
            'responseExcerpt' => $this->excerpt($content),
        ];

        if ($statusCode >= 400 && $statusCode < 500) {
            // A 401 would make the administration drop the user's own session.
            $status = $statusCode === Response::HTTP_UNAUTHORIZED ? Response::HTTP_FORBIDDEN : $statusCode;

            return $this->fail($status, LogLevel::WARNING, 'Neos rejected the request', $context);
        }

        return $this->fail(Response::HTTP_BAD_GATEWAY, LogLevel::ERROR, 'Unexpected response from Neos', $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function fail(int $status, string $level, string $reason, array $context): JsonResponse
    {
        $code = bin2hex(random_bytes(4));
        $this->logger->log($level, sprintf('Neos request failed [%s]: %s', $code, $reason), $context);

        return new JsonResponse(['message' => 'The request to Neos failed.', 'code' => $code], $status);
    }

    /**
     * Flow's error page names a reference code that leads to the matching entry in the Neos exception log.
     */
    private function extractReferenceCode(string $content): ?string
    {
        return preg_match('/reference code:(?:\s|<br\s*\/?>)*([A-Za-z0-9]+)/i', $content, $matches) === 1 ? $matches[1] : null;
    }

    private function excerpt(string $content): string
    {
        // The space keeps words from adjacent elements apart once the tags are gone.
        $text = trim((string) preg_replace('/\s+/', ' ', strip_tags(str_replace('<', ' <', $content))));

        return mb_substr($text, 0, self::LOGGED_BODY_LENGTH);
    }
}
