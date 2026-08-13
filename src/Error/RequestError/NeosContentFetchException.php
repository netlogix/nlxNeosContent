<?php

declare(strict_types=1);

namespace nlxNeosContent\Error\RequestError;

use nlxNeosContent\Error\NeosExceptionInterface;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class NeosContentFetchException extends \Exception implements NeosExceptionInterface
{
    public function __construct(string $message = 'Neos Content could not be fetched.', int $code = 1752651981, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public static function forCmsPage(
        CmsPageEntity $cmsPage,
        SalesChannelContext $salesChannelContext,
        string $languageId,
        \Throwable $previous,
        int $code
    ): self {
        return new self(
            sprintf(
                'Failed to fetch content from Neos for node CmsPage "%s" for sales channel "%s" and language "%s" with Errormessage: %s',
                $cmsPage->getName(),
                $salesChannelContext->getSalesChannelId(),
                $languageId,
                $previous->getMessage()
            ),
            $code,
            $previous
        );
    }
}
