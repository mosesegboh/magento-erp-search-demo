<?php

declare(strict_types=1);

namespace Portfolio\OrderExport\Model;

use Magento\Framework\Model\AbstractModel;
use Portfolio\OrderExport\Model\ResourceModel\ExportLog as ExportLogResource;

class ExportLog extends AbstractModel
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_PENDING = 'pending';
    public const STATUS_RETRY_SCHEDULED = 'retry_scheduled';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_DEAD_LETTERED = 'dead_lettered';

    protected function _construct(): void
    {
        $this->_init(ExportLogResource::class);
    }
}
