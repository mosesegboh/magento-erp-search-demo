<?php

declare(strict_types=1);

namespace Portfolio\ErpSync\Model;

use Magento\Framework\Model\AbstractModel;
use Portfolio\ErpSync\Model\ResourceModel\SyncLog as SyncLogResource;

class SyncLog extends AbstractModel
{
    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';

    protected function _construct(): void
    {
        $this->_init(SyncLogResource::class);
    }
}
