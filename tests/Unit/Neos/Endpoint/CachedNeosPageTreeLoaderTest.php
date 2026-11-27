<?php

declare(strict_types=1);

namespace nlxNeosContent\Tests\Unit\Neos\Endpoint;

use nlxNeosContent\Neos\DTO\NeosPageCollection;
use nlxNeosContent\Neos\Endpoint\AbstractNeosPageTreeLoader;
use nlxNeosContent\Neos\Endpoint\CachedNeosPageTreeLoader;
use nlxNeosContent\Neos\Endpoint\NeosPageTreeLoadResult;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Clock\MockClock;

class CachedNeosPageTreeLoaderTest extends TestCase
{
    private MockClock $clock;

    private \ArrayObject $calls;

    protected function setUp(): void
    {
        $this->clock = new MockClock();
        $this->calls = new \ArrayObject();
    }

    public function testFailedLoadIsCachedBriefly(): void
    {
        $loader = $this->loader(fn () => throw new \RuntimeException('Neos is down'));

        static::assertCount(0, $loader->load('sales-channel', 'language'));
        $loader->load('sales-channel', 'language');
        static::assertCount(1, $this->calls, 'An unreachable Neos must not be asked again on every request.');

        $this->clock->sleep(11);
        $loader->load('sales-channel', 'language');
        static::assertCount(2, $this->calls);
    }

    public function testSuccessfulLoadIsCachedForADay(): void
    {
        $loader = $this->loader(fn () => new NeosPageCollection());

        $loader->load('sales-channel', 'language');
        $this->clock->sleep(86399);
        $loader->load('sales-channel', 'language');
        static::assertCount(1, $this->calls);

        $this->clock->sleep(2);
        $loader->load('sales-channel', 'language');
        static::assertCount(2, $this->calls);
    }

    public function testFailedLoadManyIsCachedBriefly(): void
    {
        $loader = $this->loader(fn () => throw new \RuntimeException('Neos is down'));

        $results = $loader->loadMany([['sales-channel', 'language']]);
        static::assertTrue($results[0]->failed);
        $loader->loadMany([['sales-channel', 'language']]);
        static::assertCount(1, $this->calls);

        $this->clock->sleep(11);
        $loader->loadMany([['sales-channel', 'language']]);
        static::assertCount(2, $this->calls);
    }

    private function loader(\Closure $fetch): CachedNeosPageTreeLoader
    {
        return new CachedNeosPageTreeLoader(
            new FakeNeosPageTreeLoader($fetch, $this->calls),
            new TagAwareAdapter(new ArrayAdapter(clock: $this->clock)),
            new NullLogger(),
        );
    }
}

// Named, because anonymous readonly classes need PHP 8.3.
readonly class FakeNeosPageTreeLoader extends AbstractNeosPageTreeLoader
{
    public function __construct(private \Closure $fetch, private \ArrayObject $calls)
    {
    }

    function load(string $salesChannelId, string $languageId): NeosPageCollection
    {
        $this->calls->append([$salesChannelId, $languageId]);

        return ($this->fetch)();
    }

    function loadMany(array $requests): array
    {
        return array_map(
            fn (array $request) => new NeosPageTreeLoadResult($request[0], $request[1], $this->load(...$request)),
            $requests
        );
    }
}
