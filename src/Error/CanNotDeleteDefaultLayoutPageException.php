<?php

declare(strict_types=1);

namespace nlxNeosContent\Error;

use Shopware\Core\Content\Cms\CmsPageEntity;

class CanNotDeleteDefaultLayoutPageException extends \Exception
{
    public function __construct(CmsPageEntity $cmsPageEntity, int $code = 1753085155, ?\Throwable $previous = null)
    {
        parent::__construct(sprintf('Could not deleted CmsPage "%s" because it is set as a default template', $cmsPageEntity->getName()), $code, $previous);
    }
}
