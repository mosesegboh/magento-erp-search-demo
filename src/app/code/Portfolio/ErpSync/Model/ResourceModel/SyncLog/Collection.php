<?php

declare(strict_types=1);

namespace Portfolio\ErpSync\Model\ResourceModel\SyncLog;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Portfolio\ErpSync\Model\ResourceModel\SyncLog as SyncLogResource;
use Portfolio\ErpSync\Model\SyncLog;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(SyncLog::class, SyncLogResource::class);
    }
}
