<?php

declare(strict_types=1);

namespace nlxNeosContent\Listener;

use nlxNeosContent\Service\ConfigService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

#[AsEventListener(method: 'onKernelRequest', priority: -10000)]
#[AsEventListener(method: 'onKernelResponse', priority: -10000)]
readonly class CorsListener
{
    function __construct(private ConfigService $configService)
    {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        $method = $request->getRealMethod();

        if ($method !== 'OPTIONS') {
            return;
        }

        if (!$this->checkOrigin($request)) {
            return;
        }

        $response = new Response();
        $event->setResponse($response);
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!$this->checkOrigin($request)) {
            return;
        }

        $response = $event->getResponse();
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET,POST,PUT,PATCH,DELETE');
        $response->headers->set('Access-Control-Allow-Headers', '*');
        $response->headers->set('Access-Control-Expose-Headers', '*');
    }

    function checkOrigin(Request $request): bool
    {
        if (!$this->configService->isEnabled()) {
            return false;
        }
        $origin = $request->headers->get('origin');

        return $origin === $this->configService->getBaseUrl();
    }
}
