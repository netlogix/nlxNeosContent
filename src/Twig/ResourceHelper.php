<?php

declare(strict_types=1);

namespace nlxNeosContent\Twig;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

#[AsTaggedItem('twig.extension')]
class ResourceHelper extends AbstractExtension
{
    private array $resources = [];

    public function __construct(
        private readonly HttpClientInterface $neosClient,
        private readonly LoggerInterface $logger,
    )
    {
        try {
            $response = $this->neosClient->request('GET', '/neos/shopware-api/resources/');
        } catch (\Exception $e) {
            $this->logger->error('Could not fetch resources from Neos: ' . $e->getMessage());
            return;
        }
        $decodedBody = json_decode($response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        if (!($decodedBody['css'] ?? false)) {
            return;
        }
        $this->resources = $decodedBody;
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('getStyleUrls', [$this, 'getStyleUrls']),
            new TwigFunction('getScriptUrls', [$this, 'getScriptUrls']),
        ];
    }

    public function getStyleUrls(): array
    {
        return $this->resources['css'] ?? [];
    }

    public function getScriptUrls(): array
    {
        return $this->resources['js'] ?? [];
    }
}
