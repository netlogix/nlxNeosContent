<?php

declare(strict_types=1);

namespace netlogixNeosContent\Storefront\Controller;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class HtmlContentController extends StorefrontController
{
    /**
     * This controller should be used to load static HTML content like stylesheets and scripts
     * earlier the idea was to load header and footer content but this is not needed anymore since
     * shopware v.6.6.10.x has a esi url for that
     */


    public function __construct()
    {
    }

    #[Route(path: '/html-content/style', name: 'frontend.html-content.style', methods: ['GET'])]
    public function getStyles(Request $request, SalesChannelContext $context): Response
    {
        //FIXME this can probably be improved but it works for now
        $response = $this->renderStorefront('@Storefront/storefront/layout.html.twig');

        $content = $response->getContent();
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true); // Suppress HTML5 warnings
        $dom->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $xpath = new \DOMXPath($dom);
        $styleTags = $xpath->query('//link[@rel="stylesheet"]');
        $scriptTags = $xpath->query('//script');

        $content = '';
        foreach ($styleTags as $styleTag) {
            $content .= $dom->saveHTML($styleTag);
        }
        foreach ($scriptTags as $scriptTag) {
            $content .= $dom->saveHTML($scriptTag);
        }


        return new Response($content);
    }
}
