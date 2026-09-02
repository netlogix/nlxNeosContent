<?php
declare(strict_types=1);

/*
 * Created by netlogix GmbH & Co. KG
 *
 * @copyright netlogix GmbH & Co. KG
 */

namespace nlxNeosContent\Cache\Warmup\DTO;

readonly class AdditionalDataPageDTO implements AdditionalDataInterface
{
    public function __construct(
        public string $pageId,
    ) {
    }
}
