<?php

declare(strict_types=1);

namespace nlxNeosContent\Serializer\CmsBlock;

use nlxNeosContent\Core\Content\Cms\Aggregate\CmsBlock\NeosCmsBlockCollection;
use nlxNeosContent\Core\Content\Cms\Aggregate\CmsBlock\NeosCmsBlockEntity;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

class NeosCmsBlockCollectionDenormalizer implements DenormalizerInterface, SerializerAwareInterface
{
    protected SerializerInterface $serializer;

    public function setSerializer(SerializerInterface $serializer): void
    {
        $this->serializer = $serializer;
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): NeosCmsBlockCollection
    {
        $cmsBlockCollection = new NeosCmsBlockCollection();
        $position = 0;
        foreach ($data as $blockData) {
            $cmsBlock = $this->serializer->denormalize($blockData, NeosCmsBlockEntity::class, $format, $context);
            $cmsBlock->setPosition($position);
            $cmsBlockCollection->add($cmsBlock);
            $position++;
        }

        return $cmsBlockCollection;
    }

    public function supportsDenormalization(
        mixed $data,
        string $type,
        ?string $format = null,
        array $context = []
    ): bool {
        return $type === NeosCmsBlockCollection::class;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            NeosCmsBlockCollection::class => false,
        ];
    }
}
