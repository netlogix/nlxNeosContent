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

    public const TYPES = [
        self::TYPE_ALL,
        self::TYPE_LAYOUTS,
        self::TYPE_NAVIGATION,
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
        if ($this->type === self::TYPE_LAYOUTS && $this->data === null) {
            $context->buildViolation("The request body must contain a field 'data' when 'type' is 'layouts'.")
                ->atPath('data')
                ->addViolation();
        }
    }
}
