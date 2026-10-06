<?php

declare(strict_types=1);

namespace nlxNeosContent\Tests\Service;

use Cocur\Slugify\Slugify;
use nlxNeosContent\Neos\DTO\NeosPageCollection;
use nlxNeosContent\Neos\DTO\NeosPageDTO;
use nlxNeosContent\Service\NeosPageSeoUrlTemplateRenderer;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandler;
use Shopware\Core\Content\Seo\SeoUrlTwigFactory;
use Twig\Error\Error;
use Twig\Error\SyntaxError;

class NeosPageSeoUrlTemplateRendererTest extends TestCase
{
    public function testDefaultTemplateKeepsNeosPaths(): void
    {
        static::assertSame(
            ['/', 'about-us', 'about-us/team', 'https://example.com/partner'],
            $this->seoPaths($this->renderer()->apply($this->tree(), NeosPageSeoUrlTemplateRenderer::DEFAULT_TEMPLATE))
        );
    }

    public function testBreadcrumbIsSlugifiedAndRootAndExternalPagesAreKept(): void
    {
        $template = 'content/{% for part in page.breadcrumb %}{{ part }}/{% endfor %}';

        static::assertSame(
            ['/', 'content/about-us/', 'content/about-us/our-team/', 'https://example.com/partner'],
            $this->seoPaths($this->renderer()->apply($this->tree(), $template))
        );
    }

    public function testTrailingSlashIsKeptInUrl(): void
    {
        $pages = $this->renderer()->apply($this->tree(), '{{ page.path|raw }}/');

        static::assertSame(
            SeoUrlPlaceholderHandler::DOMAIN_PLACEHOLDER . '/about-us/#',
            $pages[1]->getUrl()
        );
    }

    public function testEmptyRenderFallsBackToNeosPath(): void
    {
        static::assertSame(
            ['/', '/about-us', 'meet-us', 'https://example.com/partner'],
            $this->seoPaths($this->renderer()->apply($this->tree(), "{{ page.customFields.slug ?? '' }}"))
        );
    }

    public function testSyntaxErrorThrows(): void
    {
        $this->expectException(SyntaxError::class);
        $this->renderer()->apply($this->tree(), '{{ page.path ');
    }

    public function testUnknownVariableThrows(): void
    {
        $this->expectException(Error::class);
        $this->renderer()->apply($this->tree(), '{{ page.unknown }}');
    }

    private function renderer(): NeosPageSeoUrlTemplateRenderer
    {
        return new NeosPageSeoUrlTemplateRenderer((new SeoUrlTwigFactory())->createTwigEnvironment(new Slugify(), [], ''));
    }

    private function tree(): NeosPageCollection
    {
        return new NeosPageCollection(
            new NeosPageDTO('home', 'Home', '/', new NeosPageCollection()),
            new NeosPageDTO('about', 'About us', '/about-us', new NeosPageCollection(
                new NeosPageDTO('team', 'Our Team', '/about-us/team', new NeosPageCollection(), customFields: ['slug' => 'meet-us']),
            )),
            new NeosPageDTO('partner', 'Partner', 'https://example.com/partner', new NeosPageCollection()),
        );
    }

    /**
     * @return list<string>
     */
    private function seoPaths(NeosPageCollection $pages): array
    {
        $seoPaths = [];
        foreach ($pages as $page) {
            $seoPaths[] = $page->seoPath;
            array_push($seoPaths, ...$this->seoPaths($page->children));
        }

        return $seoPaths;
    }
}
