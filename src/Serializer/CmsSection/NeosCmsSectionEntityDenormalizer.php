<?php

declare(strict_types=1);

namespace nlxNeosContent\Serializer\CmsSection;

use nlxNeosContent\Core\Content\Cms\Aggregate\CmsBlock\NeosCmsBlockCollection;
use nlxNeosContent\Core\Content\Cms\Aggregate\CmsSection\NeosCmsSectionEntity;
use nlxNeosContent\Error\ContentReplacement\FaultyNeosSectionDataException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

class NeosCmsSectionEntityDenormalizer implements DenormalizerInterface, SerializerAwareInterface
{
    protected DenormalizerInterface $serializer;

    public function setSerializer(SerializerInterface $serializer): void
    {
        assert($serializer instanceof DenormalizerInterface);
        $this->serializer = $serializer;
    }

    public function denormalize(
        mixed $data,
        string $type,
        ?string $format = null,
        array $context = []
    ): NeosCmsSectionEntity {
        if (!isset($data['type'])) {
            throw new FaultyNeosSectionDataException(
                message: sprintf(
                    'Expected section data to contain a "type", got: %s',
                    is_array($data) ? 'keys [' . implode(', ', array_keys($data)) . ']' : get_debug_type($data)
                ),
                code: 1793104878
            );
        }

        $cmsSection = new NeosCmsSectionEntity();
        $fieldVisibility = new FieldVisibility([]);
        $cmsSection->setId(Uuid::randomHex());
        $cmsSection->setType($data['type']);
        $cmsSection->setVersionId(Defaults::LIVE_VERSION);
        $cmsSection->setSizingMode($data['sizingMode'] ?? 'boxed');
        $cmsSection->setMobileBehavior($data['mobileBehavior'] ?? 'wrap');
        $cmsSection->setBackgroundColor($data['backgroundColor'] ?? '');
        $cmsSection->setCssClass($data['cssClass'] ?? '');
        $cmsSection->setBackgroundMediaMode($data['backgroundMediaMode'] ?? 'cover');
        $cmsSection->internalSetEntityData('cms_section', $fieldVisibility);
        $blocks = $this->serializer->denormalize(
            $data['blocks'] ?? [],
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
