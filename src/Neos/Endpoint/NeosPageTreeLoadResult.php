<?php

declare(strict_types=1);

namespace nlxNeosContent\Neos\Endpoint;

use nlxNeosContent\Neos\DTO\NeosPageCollection;

/**
 * Pairs a loadMany() result with the request that produced it, so a caller checking
 * several candidates doesn't have to track that association itself via array position.
 */
final readonly class NeosPageTreeLoadResult
{
    /**
     * @param bool $failed True when $tree is a stand-in empty collection because the
     *             fetch itself failed (error/timeout), not because Neos genuinely has
     *             no pages - callers must not cache a failed result as if it were real.
     */
    public function __construct(
        public string $salesChannelId,
        public string $languageId,
        public string $domainUrl,
        public NeosPageCollection $tree,
        public bool $failed = false,
    ) {
    }
}
