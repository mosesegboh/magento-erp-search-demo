<?php

declare(strict_types=1);

namespace Portfolio\ErpSync\Model\Dto;

use Magento\Framework\Exception\LocalizedException;

class ErpProductData
{
    private const DEFAULT_STATUS = 'enabled';
    private const DEFAULT_VISIBILITY = 'catalog_search';

    /**
     * @param string[] $categories
     * @param string[] $tags
     */
    private function __construct(
        private readonly string $productSku,
        private readonly string $name,
        private readonly float $price,
        private readonly float $stockQuantity,
        private readonly bool $isInStock,
        private readonly string $status,
        private readonly string $visibility,
        private readonly string $brand,
        private readonly string $sourceUpdatedAt,
        private readonly array $categories,
        private readonly array $tags
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @throws LocalizedException
     */
    public static function fromPayload(array $payload): self
    {
        $productSku = trim((string)($payload['sku'] ?? ''));

        if ($productSku === '') {
            throw new LocalizedException(__('ERP product payload is missing sku.'));
        }

        $name = trim((string)($payload['name'] ?? ''));
        $stockQuantity = max(self::normalizeFloat($payload['stockQty'] ?? 0), 0.0);
        $attributes = $payload['attributes'] ?? [];
        $tags = is_array($attributes) ? self::normalizeStringList($attributes['tags'] ?? []) : [];

        return new self(
            $productSku,
            $name !== '' ? $name : $productSku,
            max(self::normalizeFloat($payload['price'] ?? 0), 0.0),
            $stockQuantity,
            self::normalizeBoolean($payload['isInStock'] ?? null, $stockQuantity > 0),
            self::normalizeIdentifier((string)($payload['status'] ?? self::DEFAULT_STATUS), self::DEFAULT_STATUS),
            self::normalizeIdentifier((string)($payload['visibility'] ?? self::DEFAULT_VISIBILITY), self::DEFAULT_VISIBILITY),
            trim((string)($payload['brand'] ?? '')),
            trim((string)($payload['updatedAt'] ?? '')),
            self::normalizeStringList($payload['categories'] ?? []),
            $tags
        );
    }

    public function getProductSku(): string
    {
        return $this->productSku;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function getStockQuantity(): float
    {
        return $this->stockQuantity;
    }

    public function isInStock(): bool
    {
        return $this->isInStock;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getVisibility(): string
    {
        return $this->visibility;
    }

    public function getBrand(): string
    {
        return $this->brand;
    }

    public function getSourceUpdatedAt(): string
    {
        return $this->sourceUpdatedAt;
    }

    /**
     * @return string[]
     */
    public function getCategories(): array
    {
        return $this->categories;
    }

    /**
     * @return string[]
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    private static function normalizeFloat(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float)$value;
        }

        if (is_string($value) && is_numeric(trim($value))) {
            return (float)trim($value);
        }

        return 0.0;
    }

    private static function normalizeBoolean(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (float)$value > 0.0;
        }

        if (is_string($value)) {
            $normalizedValue = strtolower(trim($value));
            $truthyValues = ['1', 'true', 'yes', 'y', 'in_stock'];
            $falseyValues = ['0', 'false', 'no', 'n', 'out_of_stock'];

            if (in_array($normalizedValue, $truthyValues, true)) {
                return true;
            }

            if (in_array($normalizedValue, $falseyValues, true)) {
                return false;
            }
        }

        return $default;
    }

    private static function normalizeIdentifier(string $value, string $default): string
    {
        $normalizedValue = strtolower(trim($value));
        $normalizedValue = (string)preg_replace('/[^a-z0-9_]+/', '_', $normalizedValue);

        return trim($normalizedValue, '_') ?: $default;
    }

    /**
     * @return string[]
     */
    private static function normalizeStringList(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        $normalizedValues = [];

        foreach ($values as $value) {
            if (!is_scalar($value)) {
                continue;
            }

            $normalizedValue = trim((string)$value);

            if ($normalizedValue !== '') {
                $normalizedValues[] = $normalizedValue;
            }
        }

        return array_values(array_unique($normalizedValues));
    }
}
