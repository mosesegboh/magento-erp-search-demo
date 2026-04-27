<?php

declare(strict_types=1);

namespace Portfolio\SearchEnhancer\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class SearchQueryLog extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('portfolio_search_query_log', 'log_id');
    }
}
