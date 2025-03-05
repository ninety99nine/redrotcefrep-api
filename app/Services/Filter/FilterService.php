<?php

namespace App\Services\Filter;

use App\Models\Store;
use App\Models\Order;
use App\Models\Address;
use App\Enums\FilterResourceType;

class FilterService
{
    private $store;

    /**
     * Generate filters for a specific resource.
     *
     * @param Store $store
     * @return self
     */
    public function setStore(Store $store): self
    {
        $this->store = $store;
        return $this;
    }


    /**
     * Generate filters for a specific resource.
     *
     * @param FilterResourceType $filterResourceType
     * @return array
     */
    public function getFiltersByResourceType(FilterResourceType $filterResourceType): array
    {
        switch ($filterResourceType) {
            case FilterResourceType::PAYMENT_METHODS:
                return self::getPaymentMethodFilters();
            case FilterResourceType::NOTIFICATIONS:
                return self::getNotificationFilters();
            case FilterResourceType::TRANSACTIONS:
                return self::getTransactionFilters();
            case FilterResourceType::FRIEND_GROUP:
                return self::getFriendGroupFilters();
            case FilterResourceType::ADDRESSES:
                return self::getAddressFilters();
            case FilterResourceType::OCCASIONS:
                return self::getFriendFilters();
            case FilterResourceType::FRIENDS:
                return self::getOccasionFilters();
            case FilterResourceType::PRODUCTS:
                return self::getProductFilters();
            case FilterResourceType::REVIEWS:
                return self::getReviewsFilters();
            case FilterResourceType::PROMOTIONS:
                return self::getPromotionFilters();
            case FilterResourceType::STORES:
                return self::getStoreFilters();
            case FilterResourceType::ORDERS:
                return self::getOrderFilters();
            case FilterResourceType::MEDIA:
                return self::getMediaFilters();
            case FilterResourceType::USERS:
                return self::getUserFilters();
            default:
                return [];
        }
    }

    /**
     * Get filters for payment methods.
     *
     * @return array
     */
    private function getPaymentMethodFilters(): array
    {
        return [
            'created_at' => [
                'label' => 'Created Date',
                'type' => 'date',
                'options' => self::getOperatorOptions()
            ]
        ];
    }

    /**
     * Get filters for notifications.
     *
     * @return array
     */
    private function getNotificationFilters(): array
    {
        return [
            'type' => [
                'label' => 'Type',
                'type' => 'checkboxes',
                'options' => [
                    ['label' => 'Orders', 'value' => 'orders'],
                    ['label' => 'Followers', 'value' => 'followers'],
                    ['label' => 'Invitations', 'value' => 'invitations'],
                    ['label' => 'Friend Groups', 'value' => 'friend-groups'],
                ],
            ],
            'status' => [
                'label' => 'Status',
                'type' => 'checkboxes',
                'options' => [
                    ['label' => 'Read', 'value' => 'read'],
                    ['label' => 'Unread', 'value' => 'unread'],
                ],
            ],
            'created_at' => [
                'label' => 'Created Date',
                'type' => 'date',
                'options' => self::getOperatorOptions()
            ]
        ];
    }

    /**
     * Get filters for notifications.
     *
     * @return array
     */
    private function getTransactionFilters(): array
    {
        return [
            'created_at' => [
                'label' => 'Created Date',
                'type' => 'date',
                'options' => self::getOperatorOptions()
            ]
        ];
    }

    /**
     * Get filters for friend groups.
     *
     * @return array
     */
    private function getFriendGroupFilters(): array
    {
        return [
            'created_at' => [
                'label' => 'Created Date',
                'type' => 'date',
                'options' => self::getOperatorOptions()
            ]
        ];
    }

    /**
     * Get filters for addresses.
     *
     * @return array
     */
    private function getAddressFilters(): array
    {
        return [
            'type' => [
                'label' => 'Type',
                'type' => 'checkboxes',
                'options' => array_merge(
                    array_map(fn($type) => ['label' => ucfirst($type), 'value' => strtolower($type)], Address::TYPES())
                ),
            ],
            'created_at' => [
                'label' => 'Created Date',
                'type' => 'date',
                'options' => self::getOperatorOptions()
            ]
        ];
    }

    /**
     * Get filters for friends.
     *
     * @return array
     */
    private function getFriendFilters(): array
    {
        return [
            'created_at' => [
                'label' => 'Created Date',
                'type' => 'date',
                'options' => self::getOperatorOptions()
            ]
        ];
    }

    /**
     * Get filters for occasions.
     *
     * @return array
     */
    private function getOccasionFilters(): array
    {
        return [
            'created_at' => [
                'label' => 'Created Date',
                'type' => 'date',
                'options' => self::getOperatorOptions()
            ]
        ];
    }

    /**
     * Get filters for produts.
     *
     * @return array
     */
    private function getProductFilters(): array
    {
        return [
            'created_at' => [
                'label' => 'Created Date',
                'type' => 'date',
                'options' => self::getOperatorOptions()
            ]
        ];
    }

    /**
     * Get filters for reviews.
     *
     * @return array
     */
    private function getReviewsFilters(): array
    {
        return [
            'created_at' => [
                'label' => 'Created Date',
                'type' => 'date',
                'options' => self::getOperatorOptions()
            ]
        ];
    }

    /**
     * Get filters for promotions.
     *
     * @return array
     */
    private function getPromotionFilters(): array
    {
        return [
            'created_at' => [
                'label' => 'Created Date',
                'type' => 'date',
                'options' => self::getOperatorOptions()
            ]
        ];
    }

    /**
     * Get filters for media.
     *
     * @return array
     */
    private function getMediaFilters(): array
    {
        return [
            'created_at' => [
                'label' => 'Created Date',
                'type' => 'date',
                'options' => self::getOperatorOptions()
            ]
        ];
    }

    /**
     * Get filters for users.
     *
     * @return array
     */
    private function getUserFilters(): array
    {
        return [
            'role' => [
                'label' => 'Role',
                'type' => 'checkboxes',
                'options' => [
                    ['label' => 'User', 'value' => 'user'],
                    ['label' => 'Super Admin', 'value' => 'super-admin'],
                ],
            ],
            'last_seen_at' => [
                'label' => 'Last Seen Date',
                'type' => 'date',
                'options' => self::getOperatorOptions()
            ],
            'created_at' => [
                'label' => 'Created Date',
                'type' => 'date',
                'options' => self::getOperatorOptions()
            ]
        ];
    }

    /**
     * Get filters for stores.
     *
     * @return array
     */
    private function getStoreFilters(): array
    {
        return [
            'online_status' => [
                'label' => 'Status',
                'type' => 'checkboxes',
                'options' => [
                    ['label' => 'Online', 'value' => 'online'],
                    ['label' => 'Offline', 'value' => 'offline'],
                ]
            ],
            'created_at' => [
                'label' => 'Created Date',
                'type' => 'date',
                'options' => self::getOperatorOptions()
            ]
        ];
    }

    /**
     * Get filters for orders.
     *
     * @return array
     */
    private function getOrderFilters(): array
    {
        $promotions = $this->store ? $this->store->promotions : [];
        //$paymentMethods = $this->store ? $this->store->paymentMethods : [];
        $deliveryMethods = $this->store ? $this->store->deliveryMethods : [];
        $deliveryTimeslots = $this->store ? $this->store->orders()->whereNotNull('delivery_timeslot')->distinct()->pluck('delivery_timeslot')->toArray() : [];

        if(count($deliveryTimeslots)) {

            // Sort the timeslots from earliest to latest
            usort($deliveryTimeslots, function ($a, $b) {

                [$startA] = explode(" - ", $a);
                [$startB] = explode(" - ", $b);

                $timeA = strtotime($startA);
                $timeB = strtotime($startB);

                return $timeA <=> $timeB;

            });

        }

        return collect([
            [
                'label' => 'Status',
                'type' => 'checkboxes',
                'target' => 'status',
                'priority' => true,
                'options' => array_map(fn($status) => [
                    'label' => ucfirst($status),
                    'value' => strtolower($status)
                ], Order::STATUSES())
            ],
            [
                'label' => 'Payment Status',
                'target' => 'payment_status',
                'type' => 'checkboxes',
                'priority' => true,
                'options' => array_map(fn($status) => [
                    'label' => ucfirst($status),
                    'value' => strtolower($status)
                ], Order::PAYMENT_STATUSES()),
            ],
            [
                'target' => 'delivery_method_id',
                'label' => 'Delivery Methods',
                'type' => 'checkboxes',
                'priority' => true,
                'options' => collect($deliveryMethods)->map(fn($deliveryMethod) => [
                    'label' => ucfirst($deliveryMethod->name),
                    'value' => $deliveryMethod->id
                ])->toArray()
            ],
            [
                'priority' => true,
                'label' => 'Delivery Date',
                'target' => 'delivery_date',
                'type' => 'date',
                'options' => self::getOperatorOptions()
            ],
            [
                'target' => 'delivery_timeslot',
                'label' => 'Delivery Timeslot',
                'type' => 'checkboxes',
                'priority' => true,
                'options' => collect($deliveryTimeslots)->map(fn($deliveryTimeslot) => [
                    'label' => $deliveryTimeslot,
                    'value' => $deliveryTimeslot
                ])->toArray()
            ],
            [
                'label' => 'Promotions',
                'target' => 'orderPromotions->promotion_id',
                'type' => 'checkboxes',
                'priority' => true,
                'options' => collect($promotions)->map(fn($promotion) => [
                    'label' => ucfirst($promotion->name),
                    'value' => $promotion->id
                ])->toArray()
            ],
            [
                'label' => 'Grand Total',
                'target' => 'grand_total',
                'type' => 'money',
                'priority' => true,
                'options' => self::getOperatorOptions()
            ],
            [
                'label' => 'Discount Total',
                'target' => 'discount_total',
                'type' => 'money',
                'options' => self::getOperatorOptions()
            ],
            [
                'label' => 'Paid Total',
                'target' => 'paid_total',
                'type' => 'money',
                'options' => self::getOperatorOptions()
            ],
            [
                'label' => 'Pending Total',
                'target' => 'pending_total',
                'type' => 'money',
                'options' => self::getOperatorOptions()
            ],
            [
                'label' => 'Outstanding Total',
                'target' => 'outstanding_total',
                'type' => 'money',
                'options' => self::getOperatorOptions()
            ],
            [
                'label' => 'Total Unit Products',
                'target' => 'total_products',
                'type' => 'number',
                'options' => self::getOperatorOptions()
            ],
            [
                'label' => 'Total Product Units',
                'target' => 'total_product_quantities',
                'type' => 'number',
                'options' => self::getOperatorOptions()
            ],
            [
                'priority' => true,
                'label' => 'Created Date',
                'target' => 'created_at',
                'type' => 'date',
                'options' => self::getOperatorOptions()
            ],
        ])->filter(fn($filter) => count($filter['options']))->toArray();
    }

    /**
     * Get operator options.
     *
     * @return array
     */
    private function getOperatorOptions(): array
    {
        return [
            ['label' => 'Greater or Equal to', 'value' => 'gte'],
            ['label' => 'Less or Equal to', 'value' => 'lte'],
            ['label' => 'Greater than', 'value' => 'gt'],
            ['label' => 'Less than', 'value' => 'lt'],
            ['label' => 'Equal to', 'value' => 'eq'],
            ['label' => 'Not equal to', 'value' => 'neq'],
            ['label' => 'Between (Including)', 'value' => 'bt'],
            ['label' => 'Between (Excluding)', 'value' => 'bt_ex'],
        ];
    }
}
