<?php

declare(strict_types=1);

namespace nlxNeosContent\Core\Content\NeosNode;

use Shopware\Core\Content\Cms\CmsPageDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class NeosNodeDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'nlx_neos_node';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return NeosNodeEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new VersionField(),
            ((new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey())),
            (new DateField('created_at', 'createdAt')),
            (new DateField('updated_at', 'updatedAt')),
            (new BoolField('neos_connection', 'neosConnection')),
            (new FkField('cms_page_id', 'cmsPageId', CmsPageDefinition::class))->addFlags(new Required()),
            (new ReferenceVersionField(CmsPageDefinition::class, 'cms_page_version_id')),
            (new OneToOneAssociationField('cmsPage', 'cms_page_id', 'id', CmsPageDefinition::class, false)),
        ]);
    }
}
