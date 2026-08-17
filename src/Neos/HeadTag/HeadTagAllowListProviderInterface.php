<?php

declare(strict_types=1);

namespace nlxNeosContent\Neos\HeadTag;

interface HeadTagAllowListProviderInterface
{
    /**
     * @return HeadTagRuleInterface[]
     */
    public function getRules(): array;
}
