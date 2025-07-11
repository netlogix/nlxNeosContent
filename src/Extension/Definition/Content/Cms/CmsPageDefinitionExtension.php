<?php

declare(strict_types=1);

namespace netlogixNeosContent\Extension\Definition\Content\Cms;

use netlogixNeosContent\Core\Content\NeosNode\NeosNodeDefinition;
use Shopware\Core\Content\Cms\CmsPageDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

class CmsPageDefinitionExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            new OneToOneAssociationField('nlxNeosNode', 'id', 'cms_page_id', NeosNodeDefinition::class, true)
        );
    }

    public function getDefinitionClass(): string
    {
        return CmsPageDefinition::class;
    }
}
