<?php

declare(strict_types=1);

namespace nlxNeosContent\Serializer\CmsSection;

use nlxNeosContent\Core\Content\Cms\Aggregate\CmsSection\NeosCmsSectionCollection;
use nlxNeosContent\Core\Content\Cms\Aggregate\CmsSection\NeosCmsSectionEntity;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

class NeosCmsSectionCollectionDenormalizer implements DenormalizerInterface, SerializerAwareInterface
{
    protected SerializerInterface $serializer;

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): NeosCmsSectionCollection
    {
        $cmsSectionCollection = new NeosCmsSectionCollection();
        $position = 0;
        foreach (json_decode($data, true) as $sectionData) {
            /** @var NeosCmsSectionEntity $cmsSection */
            $cmsSection = $this->serializer->denormalize($sectionData, NeosCmsSectionEntity::class, $format, $context);
            $cmsSection->setPosition($position);
            $cmsSectionCollection->add($cmsSection);
            $position++;
        }

        return $cmsSectionCollection;
    }

    public function supportsDenormalization(
        mixed $data,
        string $type,
        ?string $format = null,
        array $context = []
    ): bool {
        return $type === NeosCmsSectionCollection::class;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            NeosCmsSectionCollection::class => false,
        ];
    }

    public function setSerializer(SerializerInterface $serializer): void
    {
        $this->serializer = $serializer;
    }
}
