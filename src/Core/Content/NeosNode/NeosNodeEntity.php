<?php

declare(strict_types=1);

namespace nlxNeosContent\Core\Content\NeosNode;

use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class NeosNodeEntity extends Entity
{
    use EntityIdTrait;

    //This has to be nullable because else the api/search/cms-page endpoint breaks
    protected ?CmsPageEntity $cmsPage = null;

    protected string $cmsPageId;

    protected string $cmsPageVersionId;

    protected ?string $nodeIdentifier;

    public function getNodeIdentifier(): ?string
    {
        return $this->nodeIdentifier;
    }

    public function nodeIdentifier(?string $nodeIdentifier): void
    {
        $this->nodeIdentifier = $nodeIdentifier;
    }

    public function getCmsPage(): CmsPageEntity
    {
        return $this->cmsPage;
    }

    public function setCmsPage(CmsPageEntity $cmsPage): void
    {
        $this->cmsPage = $cmsPage;
    }

    public function getCmsPageVersionId(): string
    {
        return $this->cmsPageVersionId;
    }

    public function setCmsPageVersionId(string $cmsPageVersionId): void
    {
        $this->cmsPageVersionId = $cmsPageVersionId;
    }

    public function getCmsPageId(): string
    {
        return $this->cmsPageId;
    }

    public function setCmsPageId(string $cmsPageId): void
    {
        $this->cmsPageId = $cmsPageId;
    }

    public function getEntityClass(): string
    {
        return NeosNodeEntity::class;
    }
}
