<?php

namespace netlogixNeosContent\Resolver;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class NeosDimensionExtension extends Extension
{
    public const NAME = 'neos-dimensions';

    public function __construct(
        SalesChannelContext $context,
        Request $request
    )
    {
    }
}
