<?php

declare(strict_types=1);

namespace nlxNeosContent\Neos\Endpoint;

use nlxNeosContent\Neos\DTO\NeosPageCollection;

readonly abstract class AbstractNeosPageTreeLoader
{
    public function getDecorated(): AbstractNeosPageTreeLoader {
        return $this;
    }
    abstract function load(string $salesChannelId, string $languageId, string $domainUrl): NeosPageCollection;

    /**
     * @param list<array{string, string, string}> $requests salesChannelId, languageId, domainUrl triples
     * @return list<NeosPageTreeLoadResult> one result per request, order not guaranteed, never throws per-request
     */
    abstract function loadMany(array $requests): array;
}
