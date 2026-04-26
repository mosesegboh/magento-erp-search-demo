<?php

declare(strict_types=1);

namespace Portfolio\ErpSync\Api;

interface ErpClientInterface
{
    /**
     * @return array<string, mixed>
     */
    public function getProductUpdates(int $page, int $pageSize, ?string $since = null): array;

    /**
     * @return array<string, mixed>
     */
    public function getProduct(string $sku): array;

    /**
     * @return array<string, mixed>
     */
    public function getInventory(string $sku): array;
}
