<?php

declare(strict_types=1);

namespace nlxNeosContent\Core\Content\Admin\Dto;

use Symfony\Component\Validator\Constraints as Assert;

readonly class NeosLayoutRequestDto
{
    public function __construct(
        #[Assert\NotBlank]
        public string $title,
        #[Assert\NotBlank]
        public string $pageType,
    ) {
    }
}
