<?php

declare(strict_types=1);

namespace nlxNeosContent\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

class PluginInfoService
{
    public function __construct(
        private readonly EntityRepository $pluginRepository
    ) {}

    public function getVersion(string $technicalName, Context $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $technicalName));

        /** @var PluginEntity|null $plugin */
        $plugin = $this->pluginRepository->search($criteria, $context)->first();

        if (!$plugin) {
            throw new \RuntimeException("Plugin with technical name '{$technicalName}' not found.");
        }

        return $plugin->getVersion();
    }
}
