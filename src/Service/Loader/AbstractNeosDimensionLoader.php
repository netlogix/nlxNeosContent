<?php

namespace nlxNeosContent\Service\Loader;

/**
 * @deprecated use languageId and salesChannelId as request headers instead
 */
abstract class AbstractNeosDimensionLoader
{
    public function getDecorated(): AbstractNeosDimensionLoader
    {
        return $this;
    }

    abstract public function load(): array;
}
