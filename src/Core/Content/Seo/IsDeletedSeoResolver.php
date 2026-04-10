<?php

namespace nlxNeosContent\Core\Content\Seo;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Seo\AbstractSeoResolver;
use Shopware\Core\Content\Seo\SeoResolver;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

#[AsDecorator(SeoResolver::class, priority: 100)]
class IsDeletedSeoResolver extends AbstractSeoResolver
{
    function __construct(
        #[AutowireDecorated]
        private readonly AbstractSeoResolver $decorated,
        private readonly Connection $connection
    ) {
    }

    public function getDecorated(): AbstractSeoResolver
    {
        return $this->decorated;
    }

    public function resolve(string $languageId, string $salesChannelId, string $pathInfo): array
    {
        $seoPathInfo = trim($pathInfo, '/');

        $query = (new QueryBuilder($this->connection))
            ->select('id')
            ->from('seo_url')
            ->where('language_id = :language_id')
            ->andWhere('(sales_channel_id = :sales_channel_id OR sales_channel_id IS NULL)')
            ->andWhere('(seo_path_info = :seoPath OR seo_path_info = :seoPathWithSlash)')
            ->andWhere('is_deleted = 0')
            ->setParameter('language_id', Uuid::fromHexToBytes($languageId))
            ->setParameter('sales_channel_id', Uuid::fromHexToBytes($salesChannelId))
            ->setParameter('seoPath', $seoPathInfo)
            ->setParameter('seoPathWithSlash', $seoPathInfo . '/')
            ->setMaxResults(1);

        if ($query->executeQuery()->rowCount() > 1) {
            return $this->getDecorated()->resolve($languageId, $salesChannelId, $pathInfo);
        }

        return ['pathInfo' => $seoPathInfo, 'isCanonical' => false];
    }
}
