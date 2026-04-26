<?php

declare(strict_types=1);

namespace Portfolio\ErpSync\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class SyncLog extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('portfolio_erp_sync_log', 'log_id');
    }
}
