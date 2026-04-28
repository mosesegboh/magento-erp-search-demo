<?php

declare(strict_types=1);

namespace Portfolio\OrderExport\Model;

use Magento\Framework\Model\AbstractModel;
use Portfolio\OrderExport\Model\ResourceModel\ExportLog as ExportLogResource;

class ExportLog extends AbstractModel
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_PENDING = 'pending';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';

    protected function _construct(): void
    {
        $this->_init(ExportLogResource::class);
    }
}
