<?php

declare(strict_types=1);

namespace nlxNeosContent\Listener;

use nlxNeosContent\Error\NeosExceptionInterface;
use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

#[AsEventListener]
readonly class NeosExceptionListener
{
    function __invoke(ExceptionEvent $event):void
    {
        $scopes = $event->getRequest()->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, []);

        if (!\in_array(StorefrontRouteScope::ID, $scopes, true)) {
            return;
        }

        if(!$event->getThrowable() instanceof  NeosExceptionInterface) {
            return;
        }

        throw new ServiceUnavailableHttpException(
            30,
            'Neos CMS is currently unavailable.',
            $event->getThrowable()
        );
    }
}
