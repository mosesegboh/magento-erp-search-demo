<?php

declare(strict_types=1);

namespace Portfolio\ErpSync\Api;

interface QueueProductSyncInterface
{
    /**
     * Process an asynchronous ERP product sync request.
     *
     * @param string $since ISO-8601 timestamp filter, or empty string for all updates.
     * @param int $pageSize Optional ERP API page-size override.
     * @param bool $dryRun Whether to fetch and log products without writing catalog changes.
     * @param int $logId Existing sync log ID created when the message was published.
     * @return void
     */
    public function execute(string $since = '', int $pageSize = 0, bool $dryRun = false, int $logId = 0): void;
}
