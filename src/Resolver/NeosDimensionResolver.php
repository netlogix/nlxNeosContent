<?php

namespace netlogixNeosContent\Resolver;

use netlogixNeosContent\Service\Loader\AbstractNeosDimensionLoader;
use netlogixNeosContent\Service\Loader\NeosDimensionLoader;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;

class NeosDimensionResolver
{
    protected array $dimensions = [];

    public function __construct(
        #[Autowire(service: NeosDimensionLoader::class)]
        private readonly AbstractNeosDimensionLoader $loader,
        private readonly ExtensionDispatcher $extensions,
    ) {
        $this->dimensions = $this->loader->load();
    }

    public function resolveDimensions(SalesChannelContext $context, Request $request): Struct
    {
        if ($context->hasExtension('nlxNeosDimensions')) {
            return $context->getExtension('nlxNeosDimensions');
        }

        $dimensions = $this->extensions->publish(
            name: NeosDimensionExtension::NAME,
            extension: new NeosDimensionExtension($context, $request),
            function: function () use ($context, $request) {
                $locale = $context->getLanguageInfo()->localeCode;
                foreach ($this->dimensions as $dimensionName => $presets) {
                    if ($dimensionName === 'language') {
                        $dimension = $presets[$locale][0] ?? '';
                        break;
                    }
                }

                return ['language' => $dimension ?? ''];
            },
        );

        $context->addArrayExtension('nlxNeosDimensions', $dimensions);

        return $context->getExtension('nlxNeosDimensions');
    }
}
