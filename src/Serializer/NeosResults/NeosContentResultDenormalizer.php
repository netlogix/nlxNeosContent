<?php

declare(strict_types=1);

namespace nlxNeosContent\Serializer\NeosResults;

use nlxNeosContent\Core\Content\Cms\Aggregate\CmsSection\NeosCmsSectionCollection;
use nlxNeosContent\Neos\DTO\NeosResults\NeosContentResult;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Denormalizes the top-level JSON envelope returned by Neos's content endpoints
 * (`content-by-path/{path}` and `content/{cmsPageId}/`), shaped as:
 *
 *   { "head": ["<head> tag string", ...], "sections": [ ...section objects... ] }
 *
 * A still-bare top-level array is tolerated as the legacy/transitional shape
 * (treated as `sections` with an empty `head`), so Neos and Shopware can be
 * deployed independently without either side breaking during a rollout window.
 */
class NeosContentResultDenormalizer implements DenormalizerInterface, SerializerAwareInterface
{
    protected DenormalizerInterface $serializer;

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): NeosContentResult
    {
        $decoded = json_decode($data, true);
        if (!is_array($decoded)) {
            $decoded = [];
        }

        if (array_is_list($decoded)) {
            $sectionsData = $decoded;
            $head = [];
        } else {
            $sectionsData = $decoded['sections'] ?? [];
            if (!is_array($sectionsData)) {
                $sectionsData = [];
            }

            $head = $decoded['head'] ?? [];
            $head = is_array($head)
                ? array_values(array_filter($head, static fn (mixed $entry): bool => is_string($entry)))
                : [];
        }

        $sections = $this->serializer->denormalize(
            json_encode($sectionsData),
            NeosCmsSectionCollection::class,
            $format ?? 'json',
            $context
        );

        return new NeosContentResult(sections: $sections, head: $head);
    }

    public function supportsDenormalization(
        mixed $data,
        string $type,
        ?string $format = null,
        array $context = []
    ): bool {
        return $type === NeosContentResult::class;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            NeosContentResult::class => false,
        ];
    }

    public function setSerializer(SerializerInterface $serializer): void
    {
        assert($serializer instanceof DenormalizerInterface);
        $this->serializer = $serializer;
    }
}
