<?php

declare(strict_types=1);

namespace netlogixNeosContent\Twig;

use Psr\Http\Client\ClientInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

#[AsTaggedItem('twig.extension')]
class ResourceHelper extends AbstractExtension
{
    private array $resources = [];

    public function __construct(
        private readonly ClientInterface $neosClient,
    )
    {
        try {
            $response = $this->neosClient->get('/shopware-api/resources/');
        } catch (\Exception $e) {
            return; // FIXME handle error, log it or flash Message, but without catching it it breaks shopware completely
        }
        $this->resources = json_decode($response->getBody()->getContents(), true);
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
