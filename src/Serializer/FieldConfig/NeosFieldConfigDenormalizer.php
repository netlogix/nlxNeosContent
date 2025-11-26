<?php

declare(strict_types=1);

namespace nlxNeosContent\Serializer\FieldConfig;

use nlxNeosContent\Core\Content\Cms\Aggregate\FieldConfig\NeosFieldConfig;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

class NeosFieldConfigDenormalizer implements DenormalizerInterface, SerializerAwareInterface
{
    protected SerializerInterface $serializer;

    public function setSerializer(SerializerInterface $serializer): void
    {
        $this->serializer = $serializer;
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): NeosFieldConfig
    {
        return new NeosFieldConfig(
            $data['name'],
            $data['source'],
            $data['value']
        );
    }

    public function supportsDenormalization(
        mixed $data,
        string $type,
        ?string $format = null,
        array $context = []
    ): bool {
        return $type === NeosFieldConfig::class;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            NeosFieldConfig::class => false,
        ];
    }
}
