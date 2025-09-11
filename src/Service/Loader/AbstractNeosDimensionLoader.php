<?php

namespace netlogixNeosContent\Service\Loader;

abstract class AbstractNeosDimensionLoader
{
    public function getDecorated(): AbstractNeosDimensionLoader
    {
        return $this;
    }

    abstract public function load(): array;
}
