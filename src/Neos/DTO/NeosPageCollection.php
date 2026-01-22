<?php

declare(strict_types=1);

namespace nlxNeosContent\Neos\DTO;

/**
 * @extends \ArrayObject<NeosPageDTO>
 */
class NeosPageCollection extends \ArrayObject
{
    function __construct(
        NeosPageDTO ...$items
    ) {
        parent::__construct($items);
    }
}
