<?php

declare(strict_types=1);

namespace nlxNeosContent\Neos\Endpoint;

use nlxNeosContent\Neos\DTO\NeosPageCollection;

readonly abstract class AbstractNeosPageTreeLoader
{
    abstract function getDecorated(): AbstractNeosPageTreeLoader;
    abstract function load(string $salesChannelId, string $languageId): NeosPageCollection;
}
