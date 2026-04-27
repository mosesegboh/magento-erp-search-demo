<?php

declare(strict_types=1);

namespace Portfolio\SearchEnhancer\Model;

use Magento\Framework\Model\AbstractModel;
use Portfolio\SearchEnhancer\Model\ResourceModel\SearchQueryLog as SearchQueryLogResource;

class SearchQueryLog extends AbstractModel
{
    protected function _construct(): void
    {
        $this->_init(SearchQueryLogResource::class);
    }
}
