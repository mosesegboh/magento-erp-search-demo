<?php

declare(strict_types=1);

namespace Portfolio\OrderExport\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class ExportLog extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('portfolio_order_export_log', 'log_id');
    }
}
