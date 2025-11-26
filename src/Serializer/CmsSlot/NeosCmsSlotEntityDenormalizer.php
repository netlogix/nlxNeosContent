<?php

declare(strict_types=1);

namespace nlxNeosContent\Serializer\CmsSlot;

use nlxNeosContent\Core\Content\Cms\Aggregate\CmsSlot\NeosCmsSlotEntity;
use nlxNeosContent\Core\Content\Cms\Aggregate\FieldConfig\NeosFieldConfigCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

class NeosCmsSlotEntityDenormalizer implements DenormalizerInterface, SerializerAwareInterface
{
    protected SerializerInterface $serializer;

    public function setSerializer(SerializerInterface $serializer): void
    {
        $this->serializer = $serializer;
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): NeosCmsSlotEntity
    {
        $cmsSlotEntity = new NeosCmsSlotEntity();
        $fieldVisibility = new FieldVisibility([]);
        $data['_class'] = CmsSlotEntity::class;
        assert($cmsSlotEntity instanceof CmsSlotEntity);
        $cmsSlotEntity->setType($data['type']);
        $cmsSlotEntity->setVersionId(Defaults::LIVE_VERSION);
        $cmsSlotEntity->setId($data['id'] ?? Uuid::randomHex());
        $cmsSlotEntity->setSlot($data['slot'] ?? 'content');
        $cmsSlotEntity->setConfig($data['config'] ?? []);
        $cmsSlotEntity->setTranslated(["config" => $data['config'] ?? []]);
        $cmsSlotEntity->setBlockId($context['blockId'] ?? '');
        $cmsSlotEntity->internalSetEntityData('cms_slot', $fieldVisibility);

        if (count($data['fieldConfig']) > 0) {
            $fieldConfigs = $this->serializer->denormalize($data['fieldConfig'], NeosFieldConfigCollection::class, $format);
            $cmsSlotEntity->setFieldConfig($fieldConfigs);
        }

        return $cmsSlotEntity;
    }

    public function supportsDenormalization(
        mixed $data,
        string $type,
        ?string $format = null,
        array $context = []
    ): bool {
        return $type === NeosCmsSlotEntity::class;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            NeosCmsSlotEntity::class => false,
        ];
    }
}
