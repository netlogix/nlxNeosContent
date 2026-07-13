<?php

declare(strict_types=1);

namespace nlxNeosContent\Command;

use nlxNeosContent\Core\Content\Admin\Dto\CacheInvalidationDto;
use nlxNeosContent\Service\CachingInvalidationService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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
        $this->addArgument(
            'type',
            InputArgument::OPTIONAL,
            sprintf('The cache to invalidate. One of: %s.', implode(', ', CacheInvalidationDto::TYPES)),
            CacheInvalidationDto::TYPE_ALL
        );

        $this->addOption(
            'id',
            null,
            InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
            "One or more layout (CMS page) UUIDs to invalidate, e.g. --id=<UUID> --id=<UUID>. Only applies to type '" . CacheInvalidationDto::TYPE_LAYOUTS . "' or '" . CacheInvalidationDto::TYPE_ALL . "'. If omitted, all layouts are invalidated."
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $type = $input->getArgument('type');
        $ids = $input->getOption('id');

        if (!in_array($type, CacheInvalidationDto::TYPES, true)) {
            $output->writeln(sprintf(
                "<error>Invalid type '%s'. Allowed values: %s.</error>",
                $type,
                implode(', ', CacheInvalidationDto::TYPES)
            ));

            return Command::FAILURE;
        }

        if ($ids !== [] && $type === CacheInvalidationDto::TYPE_NAVIGATION) {
            $output->writeln(sprintf(
                "<error>The --id option cannot be used with type '%s'.</error>",
                CacheInvalidationDto::TYPE_NAVIGATION
            ));

            return Command::FAILURE;
        }

        foreach ($ids as $id) {
            if (!Uuid::isValid($id)) {
                $output->writeln(sprintf("<error>Invalid UUID '%s' given for --id.</error>", $id));

                return Command::FAILURE;
            }
        }

        $context = Context::createCLIContext();

        try {
            if ($type === CacheInvalidationDto::TYPE_ALL || $type === CacheInvalidationDto::TYPE_LAYOUTS) {
                $this->cacheInvalidationService->invalidateNeosCmsLayoutCaches($ids, $context);
                $output->writeln('<info>Layout caches invalidated successfully.</info>');
            }

            if ($type === CacheInvalidationDto::TYPE_ALL || $type === CacheInvalidationDto::TYPE_NAVIGATION) {
                $this->cacheInvalidationService->invalidateNavigationCaches();
                $output->writeln('<info>Navigation caches invalidated successfully.</info>');
            }
        } catch (\Throwable $e) {
            $output->writeln(sprintf('<error>Failed to invalidate caches: %s</error>', $e->getMessage()));

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
