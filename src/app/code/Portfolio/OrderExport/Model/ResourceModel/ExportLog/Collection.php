<?php

declare(strict_types=1);

namespace Portfolio\OrderExport\Model\ResourceModel\ExportLog;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Portfolio\OrderExport\Model\ExportLog;
use Portfolio\OrderExport\Model\ResourceModel\ExportLog as ExportLogResource;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(ExportLog::class, ExportLogResource::class);
    }
}
