<?php

declare(strict_types=1);

namespace Portfolio\OrderExport\Api;

interface QueueOrderExportInterface
{
    /**
     * Process an asynchronous ERP order export request.
     *
     * @param int $orderId Magento sales order entity ID.
     * @param bool $force Whether to export even if a successful export log already exists.
     * @param int $logId Existing export log ID created when the message was published.
     * @return void
     */
    public function execute(int $orderId, bool $force = false, int $logId = 0): void;
}
