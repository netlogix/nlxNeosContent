<?php

declare(strict_types=1);

namespace nlxNeosContent\Tests\Unit\Serializer\CmsSection;

use nlxNeosContent\Core\Content\Cms\Aggregate\CmsBlock\NeosCmsBlockCollection;
use nlxNeosContent\Core\Content\Cms\Aggregate\CmsSection\NeosCmsSectionEntity;
use nlxNeosContent\Serializer\CmsSection\NeosCmsSectionEntityDenormalizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class NeosCmsSectionEntityDenormalizerTest extends TestCase
{
    public function testCssClassIsTakenFromNeos(): void
    {
        $section = $this->denormalize(['cssClass' => 'my-section another-class']);

        static::assertSame('my-section another-class', $section->getCssClass());
    }

    public function testCssClassIsEmptyWithoutOneFromNeos(): void
    {
        static::assertSame('', $this->denormalize([])->getCssClass());
        static::assertSame('', $this->denormalize(['cssClass' => null])->getCssClass());
    }

    private function denormalize(array $data): NeosCmsSectionEntity
    {
        $serializer = $this->createMockForIntersectionOfInterfaces([SerializerInterface::class, DenormalizerInterface::class]);
        $serializer->method('denormalize')->willReturn(new NeosCmsBlockCollection());

        $denormalizer = new NeosCmsSectionEntityDenormalizer();
        $denormalizer->setSerializer($serializer);

        return $denormalizer->denormalize(
            ['type' => 'default', 'blocks' => []] + $data,
            NeosCmsSectionEntity::class
        );
    }
}
