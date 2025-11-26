<?php

declare(strict_types=1);

namespace nlxNeosContent\Serializer\CmsSection;

use nlxNeosContent\Core\Content\Cms\Aggregate\CmsBlock\NeosCmsBlockCollection;
use nlxNeosContent\Core\Content\Cms\Aggregate\CmsSection\NeosCmsSectionEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

class NeosCmsSectionEntityDenormalizer implements DenormalizerInterface, SerializerAwareInterface
{
    protected SerializerInterface $serializer;

    public function setSerializer(SerializerInterface $serializer): void
    {
        $this->serializer = $serializer;
    }

    public function denormalize(
        mixed $data,
        string $type,
        ?string $format = null,
        array $context = []
    ): NeosCmsSectionEntity {
        $cmsSection = new NeosCmsSectionEntity();
        $fieldVisibility = new FieldVisibility([]);
        $cmsSection->setId(Uuid::randomHex());
        $cmsSection->setType($data['type']);
        $cmsSection->setVersionId(Defaults::LIVE_VERSION);
        $cmsSection->setSizingMode($data['sizingMode'] ?? 'boxed');
        $cmsSection->setMobileBehavior($data['mobileBehavior'] ?? 'wrap');
        $cmsSection->setBackgroundColor($data['backgroundColor'] ?? '');
        $cmsSection->setBackgroundMediaMode($data['backgroundMediaMode'] ?? 'cover');
        $cmsSection->internalSetEntityData('cms_section', $fieldVisibility);
        $blocks = $this->serializer->denormalize(
            $data['blocks'],
            NeosCmsBlockCollection::class,
            $format,
            ['sectionId' => $cmsSection->getId()]
        );
        $cmsSection->setBlocks($blocks);

        return $cmsSection;
    }

    public function supportsDenormalization(
        mixed $data,
        string $type,
        ?string $format = null,
        array $context = []
    ): bool {
        return $type === NeosCmsSectionEntity::class;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            NeosCmsSectionEntity::class => false,
        ];
    }
}
