<?php

declare(strict_types=1);

namespace Portfolio\OrderExport\Model;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;

class OrderPayloadBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(OrderInterface $order): array
    {
        return [
            'magentoOrderId' => (int)$order->getEntityId(),
            'incrementId' => (string)$order->getIncrementId(),
            'status' => (string)$order->getStatus(),
            'state' => (string)$order->getState(),
            'currency' => (string)$order->getOrderCurrencyCode(),
            'grandTotal' => (float)$order->getGrandTotal(),
            'subtotal' => (float)$order->getSubtotal(),
            'shippingAmount' => (float)$order->getShippingAmount(),
            'taxAmount' => (float)$order->getTaxAmount(),
            'discountAmount' => (float)$order->getDiscountAmount(),
            'customer' => [
                'email' => (string)$order->getCustomerEmail(),
                'firstname' => (string)$order->getCustomerFirstname(),
                'lastname' => (string)$order->getCustomerLastname(),
            ],
            'billingAddress' => $this->mapAddress($order->getBillingAddress()),
            'shippingAddress' => $this->mapAddress($order->getShippingAddress()),
            'items' => $this->mapItems($order->getItems()),
        ];
    }

    /**
     * @param iterable<OrderItemInterface> $items
     * @return array<int, array<string, mixed>>
     */
    private function mapItems(iterable $items): array
    {
        $payload = [];

        foreach ($items as $item) {
            if ((int)$item->getParentItemId() > 0) {
                continue;
            }

            $payload[] = [
                'sku' => (string)$item->getSku(),
                'name' => (string)$item->getName(),
                'qty' => (float)$item->getQtyOrdered(),
                'price' => (float)$item->getPrice(),
                'rowTotal' => (float)$item->getRowTotal(),
            ];
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapAddress(mixed $address): ?array
    {
        if ($address === null) {
            return null;
        }

        return [
            'firstname' => (string)$address->getFirstname(),
            'lastname' => (string)$address->getLastname(),
            'street' => $address->getStreet(),
            'city' => (string)$address->getCity(),
            'region' => (string)$address->getRegion(),
            'postcode' => (string)$address->getPostcode(),
            'countryId' => (string)$address->getCountryId(),
            'telephone' => (string)$address->getTelephone(),
        ];
    }
}
