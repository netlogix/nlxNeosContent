<?php

declare(strict_types=1);

namespace nlxNeosContent\Storefront\Controller;

use nlxNeosContent\Error\Routing\NeosRoutingException;
use nlxNeosContent\Service\NeosPageTreeService;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\SalesChannel\AbstractContextSwitchRoute;
use Shopware\Core\System\SalesChannel\SalesChannel\ContextSwitchRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\ContextController;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

#[AsDecorator(ContextController::class)]
class ContextControllerDecorator extends ContextController
{
    public function __construct(
        #[Autowire(service: ContextSwitchRoute::class)]
        private readonly AbstractContextSwitchRoute $contextSwitchRoute,
        private readonly RequestStack $requestStack,
        private readonly RouterInterface $router,
        #[AutowireDecorated]
        private readonly ContextController $inner,
        private readonly NeosPageTreeService $neosPageTreeService,
        #[Autowire(service: SalesChannelContextService::class)]
        private readonly SalesChannelContextServiceInterface $salesChannelContextService,
    ) {
        parent::__construct(
            $this->contextSwitchRoute,
            $this->requestStack,
            $this->router,
        );
    }

    public function switchLanguage(Request $request, SalesChannelContext $context): RedirectResponse
    {

        if(array_key_exists('neos', $request->request->all('redirectParameters')) && $request->request->all('redirectParameters')['neos'] === "1")
        {
            if (!array_key_exists('navigationId', $request->request->all('redirectParameters'))) {
                throw new \InvalidArgumentException('navigationId is required for neos redirects');
            }

            $languageId = $request->request->get('languageId');
            if (!$languageId) {
                throw RoutingException::missingRequestParameter('languageId');
            }

            if (!\is_string($languageId) || !Uuid::isValid($languageId)) {
                throw RoutingException::invalidRequestParameter('languageId');
            }

            $newContext = $this->salesChannelContextService->get(
                new SalesChannelContextServiceParameters(
                    salesChannelId: $context->getSalesChannelId(),
                    token: $context->getToken(),
                    languageId: $languageId,
                    currencyId: $context->getCurrencyId(),
                    domainId: $context->getDomainId(),
                    originalContext: $context->getContext(),
                    customerId: $context->getCustomer()?->getId(),
                    imitatingUserId: $context->getImitatingUserId(),
                )
            );

            $nodeIdentifier = $request->request->all('redirectParameters')['navigationId'];
            $pathInfo = $this->neosPageTreeService->findPathInfoForIdentifierAndContext($nodeIdentifier, $newContext);

            try {
                $newTokenResponse = $this->contextSwitchRoute->switchContext(
                    new RequestDataBag([SalesChannelContextService::LANGUAGE_ID => $languageId]),
                    $context
                );
            } catch (ConstraintViolationException) {
                throw RoutingException::languageNotFound($languageId);
            }

            if ($newTokenResponse->getRedirectUrl() === null) {
                $url = $this->getDomainUrlByLanguageId($newContext, $languageId);
                return new RedirectResponse(rtrim($url, '/') . '/' . ltrim($pathInfo, '/'), Response::HTTP_FOUND);
            }

            $parsedUrl = parse_url($newTokenResponse->getRedirectUrl());

            if (!$parsedUrl) {
                throw NeosRoutingException::redirectUrlNotParsable($newTokenResponse->getRedirectUrl());
            }

            $redirectRequest = Request::create($newTokenResponse->getRedirectUrl());

            return new RedirectResponse(rtrim($redirectRequest->getUri(), '/') . '/' . ltrim($pathInfo, '/'), Response::HTTP_FOUND);

        }
        return $this->inner->switchLanguage($request, $context);
    }

    private function getDomainUrlByLanguageId(SalesChannelContext $context, string $languageId): string
    {
        $domains = $context->getSalesChannel()->getDomains();

        if ($domains === null) {
            throw NeosRoutingException::salesChannelDomainsNotLoaded($context->getSalesChannelId(), $languageId);
        }

        $langDomain = $domains->filterByProperty('languageId', $languageId)->first();

        if ($langDomain === null) {
            throw NeosRoutingException::domainNotFoundForLanguage($languageId, $context->getSalesChannelId());
        }

        return $langDomain->getUrl();
    }
}
