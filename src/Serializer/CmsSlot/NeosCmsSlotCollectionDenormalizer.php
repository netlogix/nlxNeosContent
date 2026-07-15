<?php

declare(strict_types=1);

namespace nlxNeosContent\Serializer\CmsSlot;

use nlxNeosContent\Core\Content\Cms\Aggregate\CmsSlot\NeosCmsSlotCollection;
use nlxNeosContent\Core\Content\Cms\Aggregate\CmsSlot\NeosCmsSlotEntity;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

class NeosCmsSlotCollectionDenormalizer implements DenormalizerInterface, SerializerAwareInterface
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
    ): NeosCmsSlotCollection {
        $cmsSlotCollection = new NeosCmsSlotCollection();
        foreach ($data as $slotData) {
            if (!isset($slotData['type'])) {
                throw new \RuntimeException('Slot type is not set');
            }
            $slotEntity = $this->serializer->denormalize($slotData, NeosCmsSlotEntity::class, $format);
            $cmsSlotCollection->add($slotEntity);
        }

        return $cmsSlotCollection;
    }

    public function supportsDenormalization(
        mixed $data,
        string $type,
        ?string $format = null,
        array $context = []
    ): bool {
        return $type === NeosCmsSlotCollection::class;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            NeosCmsSlotCollection::class => false,
        ];
    }
}
