<?php

declare(strict_types=1);

namespace nlxNeosContent\Serializer\CmsBlock;

use nlxNeosContent\Core\Content\Cms\Aggregate\CmsBlock\NeosCmsBlockEntity;
use nlxNeosContent\Core\Content\Cms\Aggregate\CmsSlot\NeosCmsSlotCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

class NeosCmsBlockEntityDenormalizer implements DenormalizerInterface, SerializerAwareInterface
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
    ): NeosCmsBlockEntity {
        if (!isset($data['type'])) {
            throw new \RuntimeException('Block type is not set');
        }

        $cmsBlock = new NeosCmsBlockEntity();
        $fieldVisibility = new FieldVisibility([]);
        $blockId = $data['id'] ?? Uuid::randomHex();

        $cmsBlock->setType($data['type']);
        $cmsBlock->setVersionId(DEFAULTS::LIVE_VERSION);
        $cmsBlock->setSectionId($context['sectionId'] ?? '');
        $cmsBlock->setSectionPosition($data['sectionPosition'] ?? 'main');
        $cmsBlock->internalSetEntityData('cms_block', $fieldVisibility);
        $cmsBlock->setId($blockId);
        if (array_key_exists('hiddenEditorRendering', $data)) {
            $cmsBlock->setCustomFields(['hiddenEditorRendering' => $data['hiddenEditorRendering']]);
        }
        $cmsBlock->setCssClass($data['cssClass'] ?? '');

        $slots = $this->serializer->denormalize(
            $data['slots'],
            NeosCmsSlotCollection::class,
            $format,
            ['blockId' => $blockId]
        );
        $cmsBlock->setSlots($slots);
        $cmsBlock->setCreatedAt(new \DateTime());
        $cmsBlock->setVisibility([
            'mobile' => true,
            'desktop' => true,
            'tablet' => true
        ]);
        $cmsBlock->setLocked(true);
        $cmsBlock->setCmsSectionVersionId(DEFAULTS::LIVE_VERSION);

        return $cmsBlock;
    }

    public function supportsDenormalization(
        mixed $data,
        string $type,
        ?string $format = null,
        array $context = []
    ): bool {
        return $type === NeosCmsBlockEntity::class;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            NeosCmsBlockEntity::class => false,
        ];
    }
}
