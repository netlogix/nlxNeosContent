<?php

declare(strict_types=1);

namespace nlxNeosContent\Core\Content\Cms\DataResolver;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\HtmlCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\HtmlStruct;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Content\Property\PropertyGroupDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;

/**
 * Replaces placeholders for the product's options of a property group:
 * - "{{ product.propertyValue.<propertyGroupId> }}" with the names of the options, e.g. "Red, Blue"
 * - "{{ product.propertyPosition.<propertyGroupId> }}" with the position of the option within the sorted options
 *   of the group, as a percentage (e.g. 2nd of 3 options = 67)
 */
#[AsDecorator(HtmlCmsElementResolver::class)]
class ProductPropertyHtmlCmsElementResolver extends AbstractCmsElementResolver
{
    private const PLACEHOLDER_PATTERN = '/{{\s*product\.(propertyValue|propertyPosition)\.([0-9a-f]{32})\s*}}/';

    public function __construct(
        private readonly AbstractCmsElementResolver $decorated,
    ) {
    }

    public function getType(): string
    {
        return $this->decorated->getType();
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        $criteriaCollection = $this->decorated->collect($slot, $resolverContext);

        $groupIds = $this->findGroupIds($slot);
        if ($groupIds === [] || !$resolverContext instanceof EntityResolverContext) {
            return $criteriaCollection;
        }

        $criteria = new Criteria($groupIds);
        $criteria->addAssociation('options');

        $criteriaCollection ??= new CriteriaCollection();
        $criteriaCollection->add(self::resultKey($slot), PropertyGroupDefinition::class, $criteria);

        return $criteriaCollection;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $this->decorated->enrich($slot, $resolverContext, $result);

        $html = $slot->getData();
        $content = $html instanceof HtmlStruct ? $html->getContent() : null;
        if ($content === null || !$resolverContext instanceof EntityResolverContext) {
            return;
        }

        $groups = $result->get(self::resultKey($slot))?->getEntities();
        if (!$groups instanceof PropertyGroupCollection) {
            return;
        }
        $groups->sortByConfig($resolverContext->getSalesChannelContext()->getLanguageInfo()->localeCode);

        $product = $resolverContext->getEntity();
        $propertyIds = $product instanceof ProductEntity ? ($product->getPropertyIds() ?? []) : [];

        $html->setContent((string) preg_replace_callback(
            self::PLACEHOLDER_PATTERN,
            fn (array $matches) => $matches[1] === 'propertyValue'
                ? self::optionNames($groups, $matches[2], $propertyIds)
                : (string) self::positionPercentage($groups, $matches[2], $propertyIds),
            $content
        ));
    }

    /**
     * @param list<string> $propertyIds
     */
    private static function optionNames(PropertyGroupCollection $groups, string $groupId, array $propertyIds): string
    {
        $names = [];
        foreach ($groups->get($groupId)?->getOptions() ?? [] as $option) {
            if (\in_array($option->getId(), $propertyIds, true)) {
                $names[] = htmlspecialchars((string) ($option->getTranslation('name') ?? $option->getName()));
            }
        }

        return implode(', ', $names);
    }

    /**
     * @param list<string> $propertyIds
     */
    private static function positionPercentage(PropertyGroupCollection $groups, string $groupId, array $propertyIds): int
    {
        $optionIds = array_values($groups->get($groupId)?->getOptions()?->getIds() ?? []);
        foreach ($optionIds as $index => $optionId) {
            if (\in_array($optionId, $propertyIds, true)) {
                return (int) round(($index + 1) / \count($optionIds) * 100);
            }
        }

        return 0;
    }

    /**
     * @return list<string>
     */
    private function findGroupIds(CmsSlotEntity $slot): array
    {
        $content = $slot->getFieldConfig()->get('content');
        if ($content === null || !$content->isStatic()) {
            return [];
        }

        preg_match_all(self::PLACEHOLDER_PATTERN, $content->getStringValue(), $matches);

        return array_values(array_unique($matches[2]));
    }

    private static function resultKey(CmsSlotEntity $slot): string
    {
        return 'nlx_neos_content_product_property_' . $slot->getUniqueIdentifier();
    }
}
