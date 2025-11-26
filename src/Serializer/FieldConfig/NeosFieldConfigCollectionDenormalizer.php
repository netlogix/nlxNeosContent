<?php

declare(strict_types=1);

namespace nlxNeosContent\Serializer\FieldConfig;

use nlxNeosContent\Core\Content\Cms\Aggregate\FieldConfig\NeosFieldConfig;
use nlxNeosContent\Core\Content\Cms\Aggregate\FieldConfig\NeosFieldConfigCollection;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

class NeosFieldConfigCollectionDenormalizer implements DenormalizerInterface, SerializerAwareInterface
{
    protected SerializerInterface $serializer;

    public function setSerializer(SerializerInterface $serializer): void
    {
        $this->serializer = $serializer;
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): NeosFieldConfigCollection
    {
        $fieldConfigCollection = new NeosFieldConfigCollection();
        foreach ($data as $fieldConfigData) {
            $fieldConfig = $this->serializer->deserialize(
                json_encode($fieldConfigData),
                NeosFieldConfig::class,
                'json'
            );
            $fieldConfigCollection->add($fieldConfig);
        }

        return $fieldConfigCollection;
    }

    public function supportsDenormalization(
        mixed $data,
        string $type,
        ?string $format = null,
        array $context = []
    ): bool {
        return $type === NeosFieldConfigCollection::class;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            NeosFieldConfigCollection::class => false,
        ];
    }
}
