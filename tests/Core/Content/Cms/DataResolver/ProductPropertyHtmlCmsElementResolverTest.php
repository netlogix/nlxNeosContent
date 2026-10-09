<?php

declare(strict_types=1);

namespace nlxNeosContent\Tests\Core\Content\Cms\DataResolver;

use nlxNeosContent\Core\Content\Cms\DataResolver\ProductPropertyHtmlCmsElementResolver;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\HtmlCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\HtmlStruct;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Content\Property\PropertyGroupDefinition;
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\Context\LanguageInfo;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class ProductPropertyHtmlCmsElementResolverTest extends TestCase
{
    private const GROUP_ID = '0190000000000000000000000000000a';

    public function testPlaceholderIsReplacedWithThePositionOfTheProductsOption(): void
    {
        static::assertSame(
            '<nlx-gauge data-value="50" data-name="Shirt"></nlx-gauge>',
            $this->render(
                '<nlx-gauge data-value="{{ product.propertyPosition.' . self::GROUP_ID . ' }}" data-name="{{ product.name }}"></nlx-gauge>',
                ['medium']
            )
        );
    }

    public function testOptionsAreSortedByPosition(): void
    {
        static::assertSame('75', $this->render('{{product.propertyPosition.' . self::GROUP_ID . '}}', ['high']));
        static::assertSame('25', $this->render('{{product.propertyPosition.' . self::GROUP_ID . '}}', ['low']));
    }

    public function testPropertyValueIsReplacedWithTheNamesOfTheProductsOptions(): void
    {
        static::assertSame(
            '<p>Low, High</p>',
            $this->render('<p>{{ product.propertyValue.' . self::GROUP_ID . ' }}</p>', ['high', 'low'])
        );
    }

    public function testOptionNamesAreEscaped(): void
    {
        static::assertSame('&lt;b&gt;', $this->render('{{ product.propertyValue.' . self::GROUP_ID . ' }}', ['markup']));
    }

    public function testAProductWithoutAnOptionOfTheGroupResolvesToZero(): void
    {
        static::assertSame('0', $this->render('{{ product.propertyPosition.' . self::GROUP_ID . ' }}', []));
    }

    public function testPagesWithoutAnEntityKeepTheContent(): void
    {
        $content = '{{ product.propertyPosition.' . self::GROUP_ID . ' }}';
        $resolver = new ProductPropertyHtmlCmsElementResolver(new HtmlCmsElementResolver());
        $slot = $this->slot($content);
        $context = new ResolverContext($this->salesChannelContext(), new Request());

        static::assertNull($resolver->collect($slot, $context));

        $resolver->enrich($slot, $context, new ElementDataCollection());
        static::assertSame($content, $this->content($slot));
    }

    /**
     * @param list<string> $propertyIds
     */
    private function render(string $content, array $propertyIds): string
    {
        $resolver = new ProductPropertyHtmlCmsElementResolver(new HtmlCmsElementResolver());
        $slot = $this->slot($content);

        $product = new ProductEntity();
        $product->setId('0190000000000000000000000000000b');
        $product->setName('Shirt');
        $product->setPropertyIds($propertyIds);
        $context = new EntityResolverContext($this->salesChannelContext(), new Request(), new ProductDefinition(), $product);

        $criteria = $resolver->collect($slot, $context);
        static::assertNotNull($criteria);
        $key = array_key_first($criteria->all()[PropertyGroupDefinition::class]);
        static::assertSame([self::GROUP_ID], $criteria->all()[PropertyGroupDefinition::class][$key]->getIds());

        $result = new ElementDataCollection();
        $result->add($key, new EntitySearchResult('property_group', 1, $this->propertyGroups(), null, new Criteria(), Context::createDefaultContext()));
        $resolver->enrich($slot, $context, $result);

        return $this->content($slot);
    }

    private function propertyGroups(): PropertyGroupCollection
    {
        $group = new PropertyGroupEntity();
        $group->setId(self::GROUP_ID);
        $group->setSortingType(PropertyGroupDefinition::SORTING_TYPE_POSITION);
        $group->setOptions(new PropertyGroupOptionCollection([
            $this->option('high', 'High', 3),
            $this->option('low', 'Low', 1),
            $this->option('medium', 'Medium', 2),
            $this->option('markup', '<b>', 4),
        ]));

        return new PropertyGroupCollection([$group]);
    }

    private function option(string $id, string $name, int $position): PropertyGroupOptionEntity
    {
        $option = new PropertyGroupOptionEntity();
        $option->setId($id);
        $option->setName($name);
        $option->setPosition($position);

        return $option;
    }

    private function slot(string $content): CmsSlotEntity
    {
        $slot = new CmsSlotEntity();
        $slot->setId('0190000000000000000000000000000c');
        $slot->setFieldConfig(new FieldConfigCollection([new FieldConfig('content', FieldConfig::SOURCE_STATIC, $content)]));

        return $slot;
    }

    private function content(CmsSlotEntity $slot): ?string
    {
        $html = $slot->getData();
        static::assertInstanceOf(HtmlStruct::class, $html);

        return $html->getContent();
    }

    private function salesChannelContext(): SalesChannelContext
    {
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getLanguageInfo')->willReturn(new LanguageInfo('English', 'en-GB'));

        return $salesChannelContext;
    }
}
