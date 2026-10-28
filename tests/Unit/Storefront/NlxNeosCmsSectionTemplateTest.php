<?php

declare(strict_types=1);

namespace nlxNeosContent\Tests\Unit\Storefront;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionEntity;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Adapter\Twig\Extension\NodeExtension;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Adapter\Twig\TemplateScopeDetector;
use Shopware\Storefront\Framework\Twig\Extension\UrlEncodingTwigFilter;
use Shopware\Storefront\Storefront;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class NlxNeosCmsSectionTemplateTest extends TestCase
{
    private const TEMPLATE = '@Storefront/storefront/page/content/nlx-neos-cms-section.html.twig';
    private const SHOPWARE_TEMPLATE = '@Storefront/storefront/page/content/detail.html.twig';

    public function testRendersTheSectionWithoutTheSectionsWrapper(): void
    {
        $html = $this->render($this->section('default', 'my-section'));

        static::assertStringNotContainsString('cms-sections', $html);
        static::assertMatchesRegularExpression(
            '#^<div class="cms-section my-section pos-1 cms-section-default"\s+style="">\s*<section>default</section>\s*</div>$#',
            $html
        );
    }

    public function testIncludesTheTemplateOfTheSectionType(): void
    {
        static::assertStringContainsString('<aside>sidebar</aside>', $this->render($this->section('sidebar')));
    }

    public function testAddsBackgroundColorAndVisibility(): void
    {
        $section = $this->section('default');
        $section->setBackgroundColor('#ff0000');
        $section->setVisibility(['mobile' => false, 'tablet' => true, 'desktop' => false]);

        $html = $this->render($section);

        static::assertStringContainsString('class="cms-section hidden-desktop hidden-mobile bg-color  pos-1 cms-section-default"', $html);
        static::assertStringContainsString('style="background-color: #ff0000;"', $html);
    }

    /**
     * Fails when Shopware changes how detail.html.twig renders a section, so the copy in the template can follow.
     */
    #[DataProvider('sections')]
    public function testMatchesTheSectionOfShopwaresDetailTemplate(CmsSectionEntity $section): void
    {
        $cmsPage = new CmsPageEntity();
        $cmsPage->setSections(new CmsSectionCollection([$section]));

        static::assertSame(
            $this->normalizeWhitespace($this->render($section, self::SHOPWARE_TEMPLATE, ['cmsPage' => $cmsPage])),
            $this->normalizeWhitespace('<div class="cms-sections">' . $this->render($section) . '</div>')
        );
    }

    public static function sections(): iterable
    {
        yield 'plain' => [self::section('default', 'my-section')];

        $section = self::section('sidebar');
        $section->setBackgroundColor('#ff0000');
        $section->setVisibility(['mobile' => false, 'tablet' => false, 'desktop' => true]);
        yield 'background color and visibility' => [$section];

        $media = new MediaEntity();
        $media->setPath('media/image.jpg');
        $media->setUrl('https://shop.example/media/image.jpg');
        $section = self::section('default');
        $section->setBackgroundMedia($media);
        $section->setBackgroundMediaMode('cover');
        yield 'background image' => [$section];
    }

    private static function section(string $type, string $cssClass = ''): CmsSectionEntity
    {
        $section = new CmsSectionEntity();
        $section->setId('section');
        $section->setType($type);
        $section->setCssClass($cssClass);
        $section->setPosition(1);

        return $section;
    }

    private function render(CmsSectionEntity $section, string $template = self::TEMPLATE, array $context = []): string
    {
        $twig = new Environment(new ArrayLoader([
            self::TEMPLATE => file_get_contents(
                __DIR__ . '/../../../src/Resources/views/storefront/page/content/nlx-neos-cms-section.html.twig'
            ),
            self::SHOPWARE_TEMPLATE => file_get_contents(
                \dirname((new \ReflectionClass(Storefront::class))->getFileName())
                . '/Resources/views/storefront/page/content/detail.html.twig'
            ),
            '@Storefront/storefront/section/cms-section-default.html.twig' => '<section>{{ section.type }}</section>',
            '@Storefront/storefront/section/cms-section-sidebar.html.twig' => '<aside>{{ section.type }}</aside>',
        ]));

        $finder = $this->createMock(TemplateFinder::class);
        $finder->method('find')->willReturnArgument(0);
        $twig->addExtension(new NodeExtension($finder, $this->createMock(TemplateScopeDetector::class)));
        $twig->addExtension(new UrlEncodingTwigFilter());

        return trim($twig->render($template, ['section' => $section, ...$context]));
    }

    private function normalizeWhitespace(string $html): string
    {
        return trim(preg_replace(['#\s+#', '#>\s+<#'], [' ', '><'], $html));
    }
}
