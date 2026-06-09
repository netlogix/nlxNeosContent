<?php

declare(strict_types=1);

namespace nlxNeosContent\HttpClient;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/**
 * This classes sole purpose is to prevent errors in the program flow,
 * while this plugin is beeing installed and there is no base_uri set yet
 */
class MissingUrlClient implements HttpClientInterface
{
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        throw new \Error('', 1780404593);
    }

    public function stream(iterable|ResponseInterface $responses, ?float $timeout = null): ResponseStreamInterface
    {
        throw new \Error('', 1780404587);
    }

    public function withOptions(array $options): static
    {
        return $this;
    }
}
