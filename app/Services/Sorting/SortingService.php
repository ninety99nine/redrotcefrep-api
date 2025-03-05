<?php

namespace App\Services\Sorting;

use App\Enums\SortResourceType;

class SortingService
{
    /**
     * Get sorting options for a specific resource type.
     *
     * @param SortResourceType $sortResourceType
     * @return array
     */
    public function getSortingOptionsByResourceType(SortResourceType $sortResourceType): array
    {
        switch ($sortResourceType) {
            case SortResourceType::PAYMENT_METHODS:
                return self::getPaymentMethodSorting();
            case SortResourceType::NOTIFICATIONS:
                return self::getNotificationSorting();
            case SortResourceType::TRANSACTIONS:
                return self::getTransactionSorting();
            case SortResourceType::ORDERS:
                return self::getOrderSorting();
            case SortResourceType::PRODUCTS:
                return self::getProductSorting();
            case SortResourceType::STORES:
                return self::getStoreSorting();
            case SortResourceType::USERS:
                return self::getUserSorting();
            default:
                return [];
        }
    }

    /**
     * Sorting options for payment methods.
     *
     * @return array
     */
    private function getPaymentMethodSorting(): array
    {
        return [
            'created_at' => 'Created Date',
            'updated_at' => 'Updated Date',
        ];
    }

    /**
     * Sorting options for notifications.
     *
     * @return array
     */
    private function getNotificationSorting(): array
    {
        return [
            'created_at' => 'Created Date',
            'status' => 'Status',
        ];
    }

    /**
     * Sorting options for transactions.
     *
     * @return array
     */
    private function getTransactionSorting(): array
    {
        return [
            'created_at' => 'Created Date',
            'amount' => 'Amount',
        ];
    }

    /**
     * Sorting options for orders.
     *
     * @return array
     */
    private function getOrderSorting(): array
    {
        return collect([
            [
                'priority' => true,
                'label' => 'Created Date',
                'target' => 'created_at',
                'priority' => true,
                'options' => $this->getSortEarliestAndOldestOptions()
            ],
            [
                'label' => 'Delivery Date',
                'target' => 'delivery_date',
                'priority' => true,
                'options' => $this->getSortEarliestAndOldestOptions()
            ],
            [
                'label' => 'Delivery Timeslot',
                'target' => 'delivery_timeslot',
                'priority' => true,
                'options' => $this->getSortEarliestAndOldestOptions()
            ],
            [
                'label' => 'Grand Total',
                'target' => 'grand_total',
                'priority' => true,
                'options' => $this->getSortHighestAndLowestOptions()
            ],
            [
                'label' => 'Discount Total',
                'target' => 'discount_total',
                'priority' => false,
                'options' => $this->getSortHighestAndLowestOptions()
            ],
            [
                'label' => 'Paid Total',
                'target' => 'paid_total',
                'priority' => false,
                'options' => $this->getSortHighestAndLowestOptions()
            ],
            [
                'label' => 'Pending Total',
                'target' => 'pending_total',
                'priority' => false,
                'options' => $this->getSortHighestAndLowestOptions()
            ],
            [
                'label' => 'Outstanding Total',
                'target' => 'outstanding_total',
                'priority' => false,
                'options' => $this->getSortHighestAndLowestOptions()
            ],
            [
                'label' => 'Total Unit Products',
                'target' => 'total_products',
                'priority' => false,
                'options' => $this->getSortHighestAndLowestOptions()
            ],
            [
                'label' => 'Total Product Units',
                'target' => 'total_product_quantities',
                'priority' => false,
                'options' => $this->getSortHighestAndLowestOptions()
            ],
        ])->filter(fn($filter) => count($filter['options']))->toArray();
    }

    /**
     * Sorting options for products.
     *
     * @return array
     */
    private function getProductSorting(): array
    {
        return [
            'name' => 'Name',
            'price' => 'Price',
            'created_at' => 'Created Date',
        ];
    }

    /**
     * Sorting options for stores.
     *
     * @return array
     */
    private function getStoreSorting(): array
    {
        return [
            'name' => 'Name',
            'created_at' => 'Created Date',
        ];
    }

    /**
     * Sorting options for users.
     *
     * @return array
     */
    private function getUserSorting(): array
    {
        return [
            'name' => 'Name',
            'email' => 'Email',
            'created_at' => 'Created Date',
        ];
    }

    /**
     * Get sort highest and lowest options.
     *
     * @return array
     */
    private function getSortHighestAndLowestOptions(): array
    {
        return [
            ['label' => 'Highest first', 'value' => 'desc'],
            ['label' => 'Lowest first', 'value' => 'asc'],
        ];
    }

    /**
     * Get sort earliest and latest options.
     *
     * @return array
     */
    private function getSortEarliestAndOldestOptions(): array
    {
        return [
            ['label' => 'Earliest first', 'value' => 'desc'],
            ['label' => 'Oldest first', 'value' => 'asc']
        ];
    }
}
