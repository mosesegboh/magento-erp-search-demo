<?php

declare(strict_types=1);

namespace Portfolio\SearchEnhancer\Model\ResourceModel\SearchQueryLog;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Portfolio\SearchEnhancer\Model\ResourceModel\SearchQueryLog as SearchQueryLogResource;
use Portfolio\SearchEnhancer\Model\SearchQueryLog;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(SearchQueryLog::class, SearchQueryLogResource::class);
    }
}
