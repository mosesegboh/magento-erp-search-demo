<?php

declare(strict_types=1);

namespace Portfolio\OrderExport\Api;

interface OrderExportClientInterface
{
    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function exportOrder(array $payload, string $idempotencyKey): array;
}
