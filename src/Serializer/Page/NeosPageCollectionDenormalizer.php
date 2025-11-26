<?php

declare(strict_types=1);

namespace nlxNeosContent\Serializer\Page;

use nlxNeosContent\Neos\DTO\NeosPageCollection;
use nlxNeosContent\Neos\DTO\NeosPageDTO;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

class NeosPageCollectionDenormalizer implements DenormalizerInterface, SerializerAwareInterface
{

    public SerializerInterface $serializer;

    public function setSerializer(SerializerInterface $serializer): void
    {
        $this->serializer = $serializer;
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        $pages = $this->serializer->denormalize($data, NeosPageDTO::class.'[]', $format, $context);
        return new $type(...$pages);
    }

    public function supportsDenormalization(
        mixed $data,
        string $type,
        ?string $format = null,
        array $context = []
    ): bool {
        return $type === NeosPageCollection::class;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            NeosPageCollection::class => true
        ];
    }
}
