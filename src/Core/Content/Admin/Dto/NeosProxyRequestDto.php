<?php

declare(strict_types=1);

namespace nlxNeosContent\Core\Content\Admin\Dto;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

readonly class NeosProxyRequestDto
{
    function __construct(
        private string $action,
        #[Assert\AtLeastOneOf([
            new Assert\IsNull(),
            new Assert\Choice(choices: ['GET', 'POST'], message: 'Method can only be GET or POST'),
        ])]
        private ?string $method = null,
        private ?array $payload = null,
    ) {
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getMethod(): string
    {
        return $this->method ?? 'GET';
    }

    public function getPayload(): ?array
    {
        return $this->payload;
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context, $payload): void
    {
        if ($this->getMethod() === 'POST' && $this->payload === null) {
            $context->buildViolation('If method is POST, payload cannot be null.')
                ->atPath('payload')
                ->addViolation();
        }
    }
}
