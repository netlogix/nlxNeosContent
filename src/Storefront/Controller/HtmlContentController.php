<?php

declare(strict_types=1);

namespace nlxNeosContent\Storefront\Controller;

use DOMElement;
use League\Flysystem\FilesystemOperator;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class HtmlContentController extends StorefrontController
{
    /**
     * This controller must only be used to load styles, js and other resources for neos backend rendering.
     * The sole purpose is to be able to provide those resources to neos to allow the best possible
     * in-place- (what you see is what you get) editing experience
     */

    public function __construct(
        private readonly FilesystemOperator $publicFilesystem,
    ) {
    }

    #[Route(path: '/html-content/resources', name: 'frontend.html-content.resources', methods: ['GET'])]
    public function getStyles(Request $request, SalesChannelContext $context): Response
    {
        $response = $this->renderStorefront('@Storefront/storefront/layout.html.twig', ['neosBackendView' => 'true']);

        $content = $response->getContent();
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true); // Suppress HTML5 warnings
        $dom->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $xpath = new \DOMXPath($dom);
        $styleTags = $xpath->query('//link[@rel="stylesheet"]');
        $scriptTags = $xpath->query('//script');

        $content = [];
        /** @var DOMElement $styleTag */
        foreach ($styleTags as $styleTag) {
            $content[$styleTag->getLineNo()] = $dom->saveHTML($styleTag);
        }
        /** @var DOMElement $scriptTag */
        foreach ($scriptTags as $scriptTag) {
            $content[$scriptTag->getLineNo()] = $dom->saveHTML($scriptTag);
        }

        // Remove urls that lead to neos resources
        $content = array_filter($content, function ($value) {
            return !str_contains($value, '_Resources/Static/Packages');
        });

        array_walk($content, function (&$value) {
            $value = preg_replace_callback(
                '/((https?:\/\/[^\/"\']+)(\/(theme|media)[^"\']+))/i',
                function ($matches) {
                    $path = $matches[3];
                    return $this->generateUrl(
                            'frontend.html-content.resource',
                            ['path' => ''],
                            UrlGeneratorInterface::ABSOLUTE_URL

                        ) . ltrim($path, '/');
                },
                $value
            );
        });

        return new Response(
            json_encode($content, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
            headers: [
                'Content-Type' => 'application/json',
            ]
        );
    }

    #[Route(path: '/html-content/resource/{path}', name: 'frontend.html-content.resource',
        requirements: [
            'path' => '.*'
        ],
        methods: ['GET'])]
    public function getResource(Request $request): Response
    {
        $path = $request->get('path');
        if (!$path) {
            return new Response(sprintf('Could not find resources for path: "%s"', $path), Response::HTTP_NOT_FOUND);
        }

        $path = parse_url($path, PHP_URL_PATH);
        $response = new StreamedResponse(function () use ($path) {
            $resource = $this->publicFilesystem->readStream($path);
            while (!feof($resource)) {
                echo fread($resource, 8192);
                flush();
            }
            flush();
        }, headers: [
            'Content-Type' => $this->publicFilesystem->mimeType($path),
        ]);
        return $response;
    }
}
