<?php

declare(strict_types=1);

namespace nlxNeosContent\Core\Content\Admin\Dto;


use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

readonly class CacheInvalidationDto
{
    public const TYPE_ALL = 'all';
    public const TYPE_LAYOUTS = 'layouts';
    public const TYPE_NAVIGATION = 'navigation';
    /**
     * Content of the given Neos pages changed, but nothing that shows up in the navigation.
     */
    public const TYPE_PAGES = 'pages';

    public const TYPES = [
        self::TYPE_ALL,
        self::TYPE_LAYOUTS,
        self::TYPE_NAVIGATION,
        self::TYPE_PAGES,
    ];

    function __construct(
        #[Assert\NotBlank(message: "The request body must contain a field 'type' of type string.")]
        #[Assert\Choice(choices: self::TYPES, message: "The field 'type' must be one of: {{ choices }}.")]
        private string $type,
        private ?array $data = null,
    )
    {
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getData(): ?array
    {
        return $this->data;
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context, $payload): void
    {
        if (in_array($this->type, [self::TYPE_LAYOUTS, self::TYPE_PAGES], true) && $this->data === null) {
            $context->buildViolation(sprintf("The request body must contain a field 'data' when 'type' is '%s'.", $this->type))
                ->atPath('data')
                ->addViolation();
        }
    }
}
