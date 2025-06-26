<?php

declare(strict_types=1);

namespace nlxNeosContent\Twig;

use GuzzleHttp\Client;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Twig\Extension\AbstractExtension;

#[AsTaggedItem('twig.extension')]
class ResourceHelper extends AbstractExtension
{
    private array $resources = [];

    public function __construct(
        private readonly SystemConfigService $systemConfigService,
    )
    {
        $baseUrl = $this->systemConfigService->get('NlxNeosContent.config.neosBaseUri');
        $client = new Client();
        try {
            $response = $client->get($baseUrl . '/shopware-api/resources/');
        } catch (\Exception $e) {
            return; // FIXME handle error, log it or flash Message, but without catching it it breaks shopware completely
        }
        $this->resources = json_decode($response->getBody()->getContents(), true);
    }

    public function getFunctions()
    {
        return [
            new \Twig\TwigFunction('getStyleUrls', [$this, 'getStyleUrls']),
            new \Twig\TwigFunction('getScriptUrls', [$this, 'getScriptUrls']),
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
