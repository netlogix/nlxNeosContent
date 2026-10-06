<?php

declare(strict_types=1);

namespace nlxNeosContent\Service;

use nlxNeosContent\Neos\DTO\NeosPageCollection;
use nlxNeosContent\Neos\DTO\NeosPageDTO;
use Shopware\Core\Content\Seo\SeoUrlGenerator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Environment;
use Twig\TemplateWrapper;

/**
 * Renders the public storefront path of every Neos page from a Shopware-style SEO URL template,
 * using the same Twig environment and slugify escaping as Shopware's own SEO URL templates.
 */
class NeosPageSeoUrlTemplateRenderer
{
    public const DEFAULT_TEMPLATE = '{{ page.path|raw }}';

    public function __construct(
        #[Autowire(service: 'shopware.seo_url.twig')]
        private readonly Environment $twig,
    ) {
    }

    /**
     * @throws \Twig\Error\Error for syntax errors or variables missing on any page
     */
    public function apply(NeosPageCollection $pages, string $template): NeosPageCollection
    {
        $twigTemplate = $this->twig->createTemplate(
            sprintf("{%% autoescape '%s' %%}%s{%% endautoescape %%}", SeoUrlGenerator::ESCAPE_SLUGIFY, $template)
        );

        return $this->applyToPages($pages, $twigTemplate, []);
    }

    /**
     * @param list<string> $parentLabels
     */
    private function applyToPages(NeosPageCollection $pages, TemplateWrapper $template, array $parentLabels): NeosPageCollection
    {
        $result = [];
        foreach ($pages as $page) {
            $breadcrumb = [...$parentLabels, $page->label];

            $result[] = new NeosPageDTO(
                identifier: $page->identifier,
                label: $page->label,
                path: $page->path,
                children: $this->applyToPages($page->children, $template, $breadcrumb),
                hiddenInIndex: $page->hiddenInIndex,
                customFields: $page->customFields,
                seoPath: $this->render($page, $template, $breadcrumb),
            );
        }

        return new NeosPageCollection(...$result);
    }

    /**
     * @param list<string> $breadcrumb
     */
    private function render(NeosPageDTO $page, TemplateWrapper $template, array $breadcrumb): string
    {
        // The home page and shortcuts to external targets have no path of their own to rewrite.
        if (trim($page->path, '/') === '' || NeosPageDTO::isExternalUrl($page->path)) {
            return $page->path;
        }

        $seoPath = trim($template->render([
            'page' => [
                'path' => trim($page->path, '/'),
                'label' => $page->label,
                'identifier' => $page->identifier,
                'customFields' => $page->customFields,
                'breadcrumb' => $breadcrumb,
            ],
        ]));

        return trim($seoPath, '/') === '' ? $page->path : $seoPath;
    }
}
