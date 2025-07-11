<?php

declare(strict_types=1);

namespace netlogixNeosContent\Command;

use netlogixNeosContent\Service\CachingInvalidationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
        $this->setDescription('Does something very special.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->cacheInvalidationService->invalidateCachesForNeosCmsPages();

        return Command::SUCCESS;
    }
}
