<?php

declare(strict_types=1);

namespace nlxNeosContent\Command;

use nlxNeosContent\Cache\Warmup\CacheWarmerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

#[AsCommand(name: 'nlx-cache:warmup', description: 'Warm up the cache for Neos Content')]
class CacheWarmup extends Command
{
    /**
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     * @param iterable<CacheWarmerInterface> $cacheWarmers
     */
    public function __construct(
        private readonly EntityRepository $salesChannelRepository,
        #[Autowire(service: SalesChannelContextFactory::class)] private readonly AbstractSalesChannelContextFactory $salesChannelContextFactory,
        #[AutowireIterator('nlx_neos_content.cache_warmer')] private readonly iterable $cacheWarmers
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'sales-channel-id',
            null,
            InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
            'One or more sales channel UUIDs to warm up, e.g. --sales-channel-id=<UUID> --sales-channel-id=<UUID>. If omitted, all storefront sales channels are warmed up.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $salesChannelIds = $input->getOption('sales-channel-id');

        foreach ($salesChannelIds as $salesChannelId) {
            if (!Uuid::isValid($salesChannelId)) {
                $output->writeln(sprintf("<error>Invalid UUID '%s' given for --sales-channel-id.</error>", $salesChannelId));

                return Command::FAILURE;
            }
        }

        $context = Context::createCLIContext();
        $criteria = new Criteria($salesChannelIds ?: null);
        $criteria->addAssociation('domains');
        $criteria->addFilter(new NotEqualsFilter('domains.id', null));
        $criteria->addAssociation('type');
        $criteria->addFilter(new EqualsFilter('type.id', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));

        $salesChannels = $this->salesChannelRepository->search($criteria, $context)->getEntities();

        if (\count($salesChannels) === 0) {
            $output->writeln(sprintf(
                '<comment>No storefront sales channels matched%s. Nothing was warmed up.</comment>',
                $salesChannelIds !== [] ? ' --sales-channel-id=' . implode(', --sales-channel-id=', $salesChannelIds) : ''
            ));

            return Command::SUCCESS;
        }

        $hasFailures = false;

        foreach ($salesChannels as $salesChannel) {
            $languageIds = $salesChannel->getDomains()?->map(
                static fn (SalesChannelDomainEntity $domain) => $domain->getLanguageId()
            ) ?? [];
            $languageIds = array_unique($languageIds);

            foreach ($languageIds as $languageId) {
                $salesChannelContext = $this->salesChannelContextFactory->create(
                    '',
                    $salesChannel->getId(),
                    [SalesChannelContextService::LANGUAGE_ID => $languageId]
                );

                $output->writeln(sprintf(
                    'Warming up caches for sales channel %s (%s) and language %s...',
                    $salesChannel->getId(),
                    $salesChannel->getName() ?? '',
                    $languageId
                ));

                foreach ($this->cacheWarmers as $cacheWarmer) {
                    try {
                        $cacheWarmer->warmUp($salesChannelContext);
                    } catch (\Throwable $e) {
                        $hasFailures = true;
                        $output->writeln(sprintf(
                            '<error>Failed to warm up %s for sales channel %s / language %s: %s</error>',
                            $cacheWarmer::class,
                            $salesChannel->getId(),
                            $languageId,
                            $e->getMessage()
                        ));
                    }
                }
            }
        }

        if ($hasFailures) {
            $output->writeln('<error>Cache warmup finished with errors.</error>');

            return Command::FAILURE;
        }

        $output->writeln('<info>Cache warmup finished.</info>');

        return Command::SUCCESS;
    }
}
