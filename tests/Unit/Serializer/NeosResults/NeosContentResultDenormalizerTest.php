<?php

declare(strict_types=1);

namespace nlxNeosContent\Tests\Unit\Serializer\NeosResults;

use nlxNeosContent\Error\ContentReplacement\FaultyNeosSectionDataException;
use nlxNeosContent\Neos\DTO\NeosResults\NeosContentResult;
use nlxNeosContent\Serializer\NeosResults\NeosContentResultDenormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionCollection;
use Symfony\Component\Serializer\Serializer;

class NeosContentResultDenormalizerTest extends TestCase
{
    public static function invalidBodies(): iterable
    {
        yield 'html error page' => ['<html><body>Maintenance</body></html>'];
        yield 'empty body' => [''];
        yield 'json null' => ['null'];
        yield 'json scalar' => ['1'];
    }

    #[DataProvider('invalidBodies')]
    public function testInvalidBodyIsRejectedInsteadOfTreatedAsEmptyPage(string $body): void
    {
        $this->expectException(FaultyNeosSectionDataException::class);

        $this->denormalizer()->denormalize($body, NeosContentResult::class, 'json');
    }

    public function testEmptySectionListIsAValidPage(): void
    {
        $result = $this->denormalizer()->denormalize('{"head": [], "sections": []}', NeosContentResult::class, 'json');

        static::assertCount(0, $result->getSections());
    }

    private function denormalizer(): NeosContentResultDenormalizer
    {
        $sections = $this->createMock(Serializer::class);
        $sections->method('denormalize')->willReturn(new CmsSectionCollection());

        $denormalizer = new NeosContentResultDenormalizer();
        $denormalizer->setSerializer($sections);

        return $denormalizer;
    }
}
