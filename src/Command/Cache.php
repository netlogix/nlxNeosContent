<?php

declare(strict_types=1);

namespace nlxNeosContent\Command;

use nlxNeosContent\Service\CachingInvalidationService;
use Shopware\Core\Framework\Context;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[
    AsCommand(name: 'nlx-cache:clear', description: 'Clear the cache for Neos Content'),
    AsTaggedItem('console.command')
]
class Cache extends Command
{
    public function __construct(
        private readonly CachingInvalidationService $cacheInvalidationService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->cacheInvalidationService->invalidateCachesForNeosCmsPages(Context::createCLIContext());

        return Command::SUCCESS;
    }
}
