<?php

namespace App\Services\ShoppingCart;

use Carbon\Carbon;
use App\Models\Store;
use App\Models\Product;
use App\Models\Address;
use App\Enums\RateType;
use App\Enums\CacheName;
use App\Enums\TaxMethod;
use App\Enums\DistanceUnit;
use App\Models\OrderProduct;
use App\Helpers\CacheManager;
use App\Models\OrderPromotion;
use App\Traits\Base\BaseTrait;
use App\Enums\StockQuantityType;
use Illuminate\Support\Collection;
use App\Enums\DeliveryMethodFeeType;
use Illuminate\Support\Facades\Http;
use App\Services\Money\MoneyService;
use App\Enums\AllowedQuantityPerOrder;
use App\Enums\DeliveryMethodScheduleType;

class ShoppingCartService
{
    use BaseTrait;

    public $store;
    public $fees = [];
    public $vat = null;
    public $subtotal = 0;
    public $feeTotal = 0;
    public $cartFees = [];
    public $vatRate = null;
    public $discounts = [];
    public $grandTotal = 0;
    public $currency = null;
    public $association = null;
    public $discountTotal = 0;
    public $cartProducts = [];
    public $tipFlatRate = null;
    public $adjustmentTotal = 0;
    public $cartPromotions = [];
    public $shoppingCart = null;
    public $storePromotions = [];
    public $deliveryDate = null;
    public $existingCart = null;
    public $freeDelivery = false;
    public $isTeamMember = false;
    public $deliveryMethod = null;
    public $promotionCode = null;
    public $promotionName = null;
    public $deliveryWeight = null;
    public $deliveryAddress = null;
    public $deliveryDistance = null;
    public $deliveryDuration = null;
    public $promotionMessage = null;
    public $deliveryTimeslot = null;
    public $pinLocationOnMap = null;
    public $deliveryMethodTips = [];
    public $promotionApplied = false;
    public $tipPercentageRate = null;
    public $scheduleIsRequired = null;
    public $scheduleIsComplete = null;
    public $isExistingCustomer = null;
    public $subtotalAfterDiscount = 0;
    public $existingOrderProducts = [];
    public $specifiedOrderProducts = [];
    public $existingOrderPromotions = [];
    public $specifiedOrderPromotions = [];
    public $canApplyPromotionCode = false;
    public $deliveryMethodAvailable = null;
    public $scheduleIncompleteReasons = [];
    public $availableDeliveryTimeSlots = [];
    public $detectedOrderProductChanges = [];
    public $deliveryAddressIsRequired = null;
    public $deliveryAddressIsComplete = false;
    public $detectedOrderPromotionChanges = [];
    public $deliveryMethodUnavailabilityReasons = [];
    public $totalSpecifiedUnCancelledOrderProducts = 0;
    public $totalSpecifiedUncancelledOrderProductQuantities = 0;

    /**
     *  Start shopping cart inspection
     *
     *  @param Store $store
     *  @return self
     */
    public function startInspection(Store $store): self
    {
        $this->setStore($store);
        $this->setStorePromotions();
        $this->setStoreCurrency();
        $this->setAssociation();
        $this->setIsTeamMember();

        $this->setCartProducts();
        $this->setCartPromotions();
        $this->setCartFees();
        $this->setPromotionCode();
        $this->setCartTipRate();
        $this->setAdjustment();
        $this->setAddress();

        $this->setExistingCustomerStatus();
        $this->setExistingShoppingCartFromCache();

        $this->setSpecifiedOrderProducts();
        $this->calculateOrderProductTotals();
        $totalSpecifiedOrderProducts = $this->countSpecifiedOrderProducts();
        $totalSpecifiedCancelledOrderProducts = $this->countSpecifiedCancelledOrderProducts();
        $this->totalSpecifiedUnCancelledOrderProducts = $this->countSpecifiedUnCancelledOrderProducts();
        $totalSpecifiedOrderProductQuantities = $this->countSpecifiedOrderProductQuantities();
        $totalSpecifiedCancelledOrderProductQuantities = $this->countSpecifiedCancelledOrderProductQuantities();
        $this->totalSpecifiedUncancelledOrderProductQuantities = $this->countSpecifiedUncancelledOrderProductQuantities();

        $this->setSpecifiedOrderPromotions();

        $totalSpecifiedOrderPromotions = $this->countSpecifiedOrderPromotions();
        $totalSpecifiedCancelledOrderPromotions = $this->countSpecifiedCancelledOrderPromotions();
        $totalSpecifiedUnCancelledOrderPromotions = $this->countSpecifiedUnCancelledOrderPromotions();

        $this->setDeliveryDate();
        $this->setDeliveryMethod();
        $this->setDeliveryTimeslot();
        $this->handleDeliveryMethod();

        $this->setCanApplyPromotionCode();
        $this->setPromotionDiscountAppliedByCode();
        $this->applyOrderPromotionDiscounts();
        $this->calculateTaxTotals();
        $this->calculateFeeTotals();
        $this->calculateGrandTotal();

        $vatRate = $this->convertToPercentageFormat($this->vatRate);
        $vat = MoneyService::convertToMoneyFormat($this->vat, $this->currency);
        $subtotal = MoneyService::convertToMoneyFormat($this->subtotal, $this->currency);
        $feeTotal = MoneyService::convertToMoneyFormat($this->feeTotal, $this->currency);
        $grandTotal = MoneyService::convertToMoneyFormat($this->grandTotal, $this->currency);
        $discountTotal = MoneyService::convertToMoneyFormat($this->discountTotal, $this->currency);
        $adjustmentTotal = MoneyService::convertToMoneyFormat($this->adjustmentTotal, $this->currency);
        $subtotalAfterDiscount = MoneyService::convertToMoneyFormat($this->subtotalAfterDiscount, $this->currency);

        $miniCart = [
            'orderProducts' => $this->specifiedOrderProducts,
            'orderPromotions' => $this->specifiedOrderPromotions
        ];

        //  Cache the shopping cart for exactly 10 minutes
        $this->getShoppingCartCacheManager()->put($miniCart, now()->addMinutes(10));

        $this->shoppingCart = [
            'totals' => [
                'subtotal' => $subtotal,
                'discounts' => $this->discounts,
                'discount_total' => $discountTotal,
                'subtotal_after_discount' => $subtotalAfterDiscount,
                'vat' => [
                    'method' => $this->store->tax_method,
                    'rate' => $vatRate,
                    'amount' => $vat,
                ],
                'fees' => $this->fees,
                'fee_total' => $feeTotal,
                'adjustment_total' => $adjustmentTotal,
                'grand_total' => $grandTotal,
            ],
            'totals_summary' => [
                'order_products' => [
                    'total_products' => $totalSpecifiedOrderProducts,
                    'total_cancelled_products' => $totalSpecifiedCancelledOrderProducts,
                    'total_uncancelled_products' => $this->totalSpecifiedUnCancelledOrderProducts,
                    'total_product_quantities' => $totalSpecifiedOrderProductQuantities,
                    'total_cancelled_product_quantities' => $totalSpecifiedCancelledOrderProductQuantities,
                    'total_uncancelled_product_quantities' => $this->totalSpecifiedUncancelledOrderProductQuantities,
                ],
                'order_promotions' => [
                    'total_promotions' => $totalSpecifiedOrderPromotions,
                    'total_cancelled_promotions' => $totalSpecifiedCancelledOrderPromotions,
                    'total_uncancelled_promotions' => $totalSpecifiedUnCancelledOrderPromotions,
                ],
            ],
            'can_apply_promotion_code' => $this->canApplyPromotionCode,
            'promotion_code' => [
                'code' => $this->promotionCode,
                'name' => $this->promotionName,
                'applied' => $this->promotionApplied,
                'message' => $this->promotionMessage,
            ],
            'delivery' => [
                'method' => $this->deliveryMethod ? [
                    'id' => $this->deliveryMethod->id,
                    'name' => $this->deliveryMethod->name,
                    'is_available' => $this->deliveryMethodAvailable,
                    'unavailability_reasons' => $this->deliveryMethodUnavailabilityReasons,
                    'tips' => $this->deliveryMethodTips,
                ] : null,
                'weight' => $this->deliveryWeight,
                'distance' => $this->deliveryDistance,
                'duration' => $this->deliveryDuration,
                'date' => $this->deliveryDate,
                'timeslot' => $this->deliveryTimeslot,
                'free_delivery' => $this->freeDelivery,
            ],
            'schedule' => [
                'is_required' => $this->scheduleIsRequired,
                'is_complete' => $this->scheduleIsComplete,
                'incomplete_reasons' => $this->scheduleIncompleteReasons
            ],
            'delivery_address' => [
                'is_required' => $this->deliveryAddressIsRequired,
                'is_complete' => $this->deliveryAddressIsComplete,
                'pin_location_on_map' => $this->pinLocationOnMap,
                'incomplete_reasons' => $this->scheduleIncompleteReasons,
            ],
            'changes' => [
                'detected_order_product_changes' => $this->detectedOrderProductChanges,
                'detected_order_promotion_changes' => $this->detectedOrderPromotionChanges
            ],
            'checkout' => [
                'can_checkout' => $this->canCheckout(),
            ],
            'order_products' => $this->getTransformedOrderProducts(),
            'order_promotions' => $this->getTransformedOrderPromotions()
        ];

        return $this;
    }

    /**
     *  Set store.
     *
     *  @return void
     */
    public function setStore($store): void
    {
        $this->store = $store;
    }

    /**
     *  Set store promotions.
     *
     *  @return void
     */
    public function setStorePromotions(): void
    {
        $this->storePromotions = $this->store->promotions;
    }

    /**
     *  Set store currency.
     *
     *  @return void
     */
    public function setStoreCurrency(): void
    {
        $this->currency = $this->store->currency;
    }

    /**
     *  Set association.
     *
     *  @return void
     */
    public function setAssociation(): void
    {
        $this->association = request()->has('association') ? $this->separateWordsThenLowercase(request()->input('association')) : null;
    }

    /**
     *  Set association.
     *
     *  @return void
     */
    public function setIsTeamMember(): void
    {
        $this->isTeamMember = $this->association === "team member";
    }

    /**
     *  Set cart products.
     *
     *  @return void
     */
    public function setCartProducts(): void
    {
        $this->cartProducts = is_string($cartProducts = request()->input('cart_products')) ? json_decode($cartProducts) : $cartProducts;
    }

    /**
     *  Set cart promotions.
     *
     *  @return void
     */
    public function setCartPromotions(): void
    {
        $this->cartPromotions = is_string($cartPromotions = request()->input('cart_promotions')) ? json_decode($cartPromotions) : $cartPromotions;
    }

    /**
     *  Set cart fees.
     *
     *  @return void
     */
    public function setCartFees(): void
    {
        $this->cartFees = is_string($cartFees = request()->input('cart_fees')) ? json_decode($cartFees) : $cartFees;
    }

    /**
     *  Set promotion code.
     *
     *  @return void
     */
    public function setPromotionCode(): void
    {
        $this->promotionCode = request()->has('promotion_code') ? request()->input('promotion_code') : null;
    }

    /**
     *  Set cart tip rate.
     *
     *  @return void
     */
    public function setCartTipRate(): void
    {
        if(request()->has('tip_flat_rate')) {
            $this->tipFlatRate = request()->input('tip_flat_rate');
        }else if(request()->has('tip_percentage_rate')) {
            $this->tipPercentageRate = request()->input('tip_percentage_rate');
        }
    }

    /**
     * Set adjustment.
     *
     * @return void
     */
    private function setAdjustment(): void
    {
        if($this->isTeamMember) {
            $this->adjustmentTotal = request()->has('adjustment') ? (float) request()->input('adjustment') : 0;
        }
    }

    /**
     *  Set delivery address.
     *
     *  @return void
     */
    public function setAddress(): void
    {
        if(request()->has('delivery_address') && request()->filled('delivery_address')) {

            $attributes = request()->input('delivery_address');

            $deliveryAddress = new Address();
            $deliveryAddress->fill($attributes);

            $this->deliveryAddress = $deliveryAddress;
            $this->deliveryAddressIsComplete = true;

        }
    }

    /**
     *  Set delivery date.
     *
     *  @return void
     */
    public function setDeliveryDate(): void
    {
        $this->deliveryDate = request()->has('delivery_date') ? request()->input('delivery_date') : null;
    }

    /**
     *  Set delivery timeslot.
     *
     *  @return void
     */
    public function setDeliveryTimeslot(): void
    {
        $this->deliveryTimeslot = request()->has('delivery_timeslot') ? request()->input('delivery_timeslot') : null;
    }

    /**
     *  Set delivery method.
     *
     *  @return void
     */
    public function setDeliveryMethod(): void
    {
        $this->deliveryMethod = request()->has('delivery_method_id') ? $this->store->deliveryMethods()->active()->find(request()->input('delivery_method_id')) : null;
    }

    /**
     *  Get shopping cart.
     *
     *  @return array
     */
    public function getShoppingCart(): array
    {
        return $this->shoppingCart;
    }

    /**
     *  Set existing customer status.
     *
     *  @return void
     */
    public function setExistingCustomerStatus(): void
    {
        $customerArray = request()->input('customer');
        $customerEmail = $customerArray['email'] ?? null;
        $customerMobileNumber = $customerArray['mobile_number'] ?? null;

        if($customerMobileNumber){

            $this->isExistingCustomer = $this->getIsCustomerStatusCacheManager()->remember(now()->addMinutes(5), function () use ($customerMobileNumber){
                return $this->store->customers()->searchMobileNumber($customerMobileNumber)->exists();
            });

        }

        if(!$this->isExistingCustomer && $customerEmail){

            $this->isExistingCustomer = $this->getIsCustomerStatusCacheManager()->remember(now()->addMinutes(5), function () use ($customerEmail){
                return $this->store->customers()->searchEmail($customerEmail)->exists();
            });

        }
    }

    /**
     *  Get shopping cart cache manager.
     *
     *  @return CacheManager
     */
    public function getShoppingCartCacheManager(): CacheManager
    {
        return (new CacheManager(CacheName::SHOPPING_CART))->append($this->store->id)->append(request()->auth_user_exists ? request()->auth_user->id : request()->input('guest_id'));
    }

    /**
     *  Get the "is customer status" cache manager
     *
     *  @return CacheManager
     */
    public function getIsCustomerStatusCacheManager()
    {
        return (new CacheManager(CacheName::IS_CUSTOMER_STATUS))->append($this->store->id)->append(request()->auth_user_exists ? request()->auth_user->id : request()->input('guest_id'));
    }

    /**
     *  Set existing shopping cart from cache.
     *
     *  @return void
     */
    public function setExistingShoppingCartFromCache(): void
    {
        //  Check if the shopping cart exists in memory (cached)
        if($this->getShoppingCartCacheManager()->has()) {

            //  Get the shopping cart stored in memory (cached)
            $this->existingCart = $this->getShoppingCartCacheManager()->get();

            //  If we have an existing cached cart
            if($this->existingCart) {

                //  Get the existing order products of the cached cart
                $this->existingOrderProducts = $this->existingCart['orderProducts'];

                //  Get the existing order promotions of the cached cart
                $this->existingOrderPromotions = $this->existingCart['orderPromotions'];

            }

        }
    }

    /**
     *  Forget the cache values stored in memory
     *
     *  @return $this
     */
    public function forgetCache()
    {
        //  Forget the shopping cart
        $this->getShoppingCartCacheManager()->forget();

        //  Forget the customer existence status
        $this->getIsCustomerStatusCacheManager()->forget();

        //  Return the current shopping cart service instance
        return $this;
    }

    /**
     *  Set specified order products based on cart products.
     *
     *  @return void
     */
    public function setSpecifiedOrderProducts(): void
    {
        $cartProductIds = $this->cartProductIds();
        $relatedProducts = $this->getRelatedProducts($cartProductIds);
        $this->specifiedOrderProducts = $this->mapCartProductsToOrderProducts($relatedProducts);
    }

    /**
     *  Get cart product IDs.
     *
     *  @return array
     */
    public function cartProductIds(): array
    {
        return collect($this->cartProducts)->pluck('id')->filter()->unique()->toArray();
    }

    /**
     *  Get related products.
     *
     *  @param array $cartProductIds
     *  @return Collection
     */
    public function getRelatedProducts(array $cartProductIds): Collection
    {
        return $cartProductIds
            ? Product::forStore($this->store->id)
                ->whereIn('id', $cartProductIds)
                ->doesNotSupportVariations()
                ->get()->keyBy('id')
            : collect();
    }

    /**
     *  Map cart products to order products.
     *
     *  @return array
     */
    protected function mapCartProductsToOrderProducts($relatedProducts): array
    {
        return collect($this->cartProducts)->map(function($cartProduct) use ($relatedProducts) {
            return $this->mapCartProductToOrderProduct($cartProduct, $relatedProducts);
        })->filter()->all();
    }

    /**
     *  Map a cart product to an order product.
     *
     *  @param array $cartProduct
     *  @param Collection $relatedProducts
     *  @return OrderProduct|null
     */
    protected function mapCartProductToOrderProduct(array $cartProduct, $relatedProducts): ?OrderProduct
    {
        $productId = $cartProduct['id'] ?? null;

        // If no ID and not a team member, ignore the product
        if(!$productId && !$this->isTeamMember) return null;

        // Retrieve the related product from the pre-fetched collection
        $relatedProduct = $productId ? $relatedProducts->get($productId) : null;

        // If product ID is provided but not found, create a mock OrderProduct if the user is a team member
        if($productId && !$relatedProduct && !$this->isTeamMember) return null;

        $existingOrderProduct = $relatedProduct ? collect($this->existingOrderProducts)->firstWhere('product_id', $relatedProduct->id) : null;

        $orderProduct = $this->prepareOrderProduct($relatedProduct, $cartProduct);
        if($relatedProduct) $orderProduct = $this->detectChangesAgainstRelatedProduct($orderProduct, $relatedProduct, $existingOrderProduct);
        if($existingOrderProduct) $orderProduct = $this->detectChangesAgainstExistingOrderProduct($orderProduct, $existingOrderProduct);

        return $orderProduct;
    }

    /**
     * Prepare order product.
     *
     * @param Product|null $relatedProduct
     * @param array $cartProduct
     * @return OrderProduct
     */
    private function prepareOrderProduct($relatedProduct, array $cartProduct): OrderProduct
    {
        $originalQuantity = $cartProduct['quantity'];
        [$quantity, $hasStock, $hasLimitedStock, $hasExceededMaximumAllowedQuantityPerOrder] = $this->calculateOrderProductQuantity($relatedProduct, $originalQuantity);

        $baseAttributes = [
            'unit_regular_price' => 0,
            'unit_sale_price' => 0,
            'unit_cost_price' => 0,
            'is_free' => false
        ];

        $relatedProductAttributes = $relatedProduct ? $relatedProduct->getAttributes() : [];

        $cartProductAttributes = $this->isTeamMember ? $orderProductAttributes = array_merge(
            collect($cartProduct)->only([
                'name', 'description', 'unit_weight', 'is_free',
                'unit_regular_price', 'unit_sale_price', 'unit_cost_price'
            ])->toArray()
        ) : [];

        $orderProductAttributes = array_merge(
            $baseAttributes,
            $relatedProductAttributes,
            [
                'id' => null,
                'quantity' => $quantity,
                'is_cancelled' => false,
                'detected_changes' => [],
                'cancellation_reasons' => [],
                'store_id' => $this->store->id,
                'currency' => $this->store->currency,
                'product_id' => $relatedProduct?->id,
                'has_limited_stock' => $hasLimitedStock,
                'original_quantity' => $originalQuantity,
                'has_exceeded_maximum_allowed_quantity_per_order' => $hasExceededMaximumAllowedQuantityPerOrder,
            ],
            $cartProductAttributes
        );

        $orderProductAttributes = array_merge($orderProductAttributes, [
            'on_sale' => $this->determineIfOrderProductOnSale($orderProductAttributes),
            'unit_price' => $this->calculateOrderProductUnitPrice($orderProductAttributes),

            'unit_sale_discount' => $this->calculateOrderProductUnitSaleDiscount($orderProductAttributes),
            'unit_sale_discount_percentage' => $this->calculateOrderProductUnitSaleDiscountPercentage($orderProductAttributes),

            'unit_profit' => $this->calculateOrderProductUnitProfit($orderProductAttributes),
            'unit_profit_percentage' => $this->calculateOrderProductUnitProfitPercentage($orderProductAttributes),

            'unit_loss' => $this->calculateOrderProductUnitLoss($orderProductAttributes),
            'unit_loss_percentage' => $this->calculateOrderProductUnitLossPercentage($orderProductAttributes),

            'has_price' => $this->determineIfOrderProductHasPrice($orderProductAttributes)
        ]);

        $orderProductAttributes = array_merge($orderProductAttributes, [
            'subtotal' => $this->calculateOrderProductSubtotal($orderProductAttributes, $quantity),
            'grand_total' => $this->calculateOrderProductGrandTotal($orderProductAttributes, $quantity),
            'sale_discount_total' => $this->calculateOrderProductSaleDiscountTotal($orderProductAttributes, $quantity)
        ]);

        $orderProduct = new OrderProduct($orderProductAttributes);

        return $orderProduct;
    }

    /**
     *  Determine if the order product is on sale.
     *
     *  @param array $orderProductAttributes
     *  @return bool
     */
    protected function determineIfOrderProductOnSale(array $orderProductAttributes): bool
    {
        return !$orderProductAttributes['is_free']
               && ($orderProductAttributes['unit_sale_price'] != 0)
               && ($orderProductAttributes['unit_regular_price'] != 0)
               && ($orderProductAttributes['unit_sale_price'] < $orderProductAttributes['unit_regular_price']);
    }

    /**
     *  Calculate the order product unit price.
     *
     *  @param array $orderProductAttributes
     *  @return float
     */
    public function calculateOrderProductUnitPrice(array $orderProductAttributes): float
    {
        if($orderProductAttributes['is_free']) return 0;
        return $this->determineIfOrderProductOnSale($orderProductAttributes) ? $orderProductAttributes['unit_sale_price'] : $orderProductAttributes['unit_regular_price'];
    }

    /**
     *  Calculate the order product unit sale discount.
     *
     *  @param array $orderProductAttributes
     *  @return float
     */
    public function calculateOrderProductUnitSaleDiscount(array $orderProductAttributes): float
    {
        if(!$this->determineIfOrderProductOnSale($orderProductAttributes)) return 0;
        return ($difference = ($orderProductAttributes['unit_regular_price'] - $orderProductAttributes['unit_sale_price'])) >= 0 ? $difference : 0;
    }

    /**
     *  Calculate the order product unit sale discount percentage.
     *
     *  @param array $orderProductAttributes
     *  @return float
     */
    public function calculateOrderProductUnitSaleDiscountPercentage(array $orderProductAttributes): float
    {
        if( ($unitSaleDiscount = $this->calculateOrderProductUnitSaleDiscount($orderProductAttributes)) == 0 ) return 0;

        $percentage = ($unitSaleDiscount / $orderProductAttributes['unit_regular_price']) * 100;

        return round($percentage);
    }

    /**
     *  Calculate the order product unit profit.
     *
     *  @param array $orderProductAttributes
     *  @return float
     */
    public function calculateOrderProductUnitProfit(array $orderProductAttributes): float
    {
        $unitPrice = $this->calculateOrderProductUnitPrice($orderProductAttributes);
        return ($difference = ($unitPrice - $orderProductAttributes['unit_cost_price'])) >= 0 ? $difference : 0;
    }

    /**
     *  Calculate the order product unit percentage profit.
     *
     *  @param array $orderProductAttributes
     *  @return float
     */
    public function calculateOrderProductUnitProfitPercentage(array $orderProductAttributes): float
    {
        //  If it costs us nothing then we make a full profit
        if( $orderProductAttributes['unit_cost_price'] == 0 ) return 100;

        $unitProfit = $this->calculateOrderProductUnitProfit($orderProductAttributes);

        $percentage = ($unitProfit / $orderProductAttributes['unit_cost_price']) * 100;

        return round($percentage);
    }

    /**
     *  Calculate the order product unit loss.
     *
     *  @param array $orderProductAttributes
     *  @return float
     */
    public function calculateOrderProductUnitLoss(array $orderProductAttributes): float
    {
        $unitPrice = $this->calculateOrderProductUnitPrice($orderProductAttributes);
        return ($difference = ($unitPrice - $orderProductAttributes['unit_cost_price'])) < 0 ? -$difference : 0;
    }

    /**
     *  Calculate the order product unit loss percentage.
     *
     *  @param array $orderProductAttributes
     *  @return float
     */
    public function calculateOrderProductUnitLossPercentage(array $orderProductAttributes): float
    {
        //  If it costs us nothing then we cannot make a loss
        if( $orderProductAttributes['unit_cost_price'] == 0 ) return 0;

        $unitLoss = $this->calculateOrderProductUnitLoss($orderProductAttributes);

        $percentage = ($unitLoss / $orderProductAttributes['unit_cost_price']) * 100;

        return round($percentage);
    }

    /**
     *  Determine if the order product has a price.
     *
     *  @param array $orderProductAttributes
     *  @return float
     */
    public function determineIfOrderProductHasPrice(array $orderProductAttributes): float
    {
        $unitPrice = $this->calculateOrderProductUnitPrice($orderProductAttributes);
        return !$orderProductAttributes['is_free'] && $unitPrice > 0;
    }

    /**
     *  Calculate the quantity of an order product.
     *
     *  @param Product|null $relatedProduct The related product.
     *  @param int $originalQuantity The original quantity from the cart.
     *  @return array
     */
    protected function calculateOrderProductQuantity(Product|null $relatedProduct, int $originalQuantity): array
    {
        $hasStock = true;
        $hasLimitedStock = false;
        $quantity = $originalQuantity;
        $hasExceededMaximumAllowedQuantityPerOrder = false;

        if($relatedProduct) {

            $hasStock = $relatedProduct->has_stock;

            if($relatedProduct->stock_quantity_type == StockQuantityType::LIMITED->value){

                $stockQuantity = $relatedProduct->stock_quantity;
                $hasLimitedStock = $stockQuantity > 0 && $stockQuantity < $quantity;

                if($hasStock && $hasLimitedStock){
                    $quantity = $stockQuantity; // Limited stock, reduce quantity
                }

            }

            if($relatedProduct->allowed_quantity_per_order == AllowedQuantityPerOrder::LIMITED->value){
                $maximumAllowedQuantityPerOrder = $relatedProduct->maximum_allowed_quantity_per_order;
                if($hasStock) $quantity = min($quantity, $maximumAllowedQuantityPerOrder);
                $hasExceededMaximumAllowedQuantityPerOrder = true;
            }

        }

        return [$quantity, $hasStock, $hasLimitedStock, $hasExceededMaximumAllowedQuantityPerOrder];
    }

    /**
     *  Calculate the subtotal of an order product.
     *
     *  @param array $orderProductAttributes
     *  @param int $quantity
     *  @return float
     */
    protected function calculateOrderProductSubtotal(array $orderProductAttributes, int $quantity): float
    {
        return $orderProductAttributes['unit_regular_price'] * $quantity;
    }

    /**
     *  Calculate the grand total of an order product.
     *
     *  @param array $orderProductAttributes
     *  @param int $quantity
     *  @return float
     */
    protected function calculateOrderProductGrandTotal(array $orderProductAttributes, int $quantity): float
    {
        return $orderProductAttributes['unit_price'] * $quantity;
    }

    /**
     *  Calculate the total sale discount for an order product.
     *
     *  @param array $orderProductAttributes
     *  @param int $quantity
     *  @return float
     */
    protected function calculateOrderProductSaleDiscountTotal(array $orderProductAttributes, int $quantity): float
    {
        return $orderProductAttributes['unit_sale_discount'] * $quantity;
    }

    /**
     * Detect changes against the related product.
     *
     * @param OrderProduct $orderProduct
     * @param Product $relatedProduct
     * @param OrderProduct|null $existingOrderProduct
     * @return OrderProduct
     */
    protected function detectChangesAgainstRelatedProduct(OrderProduct $orderProduct, Product $relatedProduct, OrderProduct|null $existingOrderProduct): OrderProduct
    {
        if($this->hasNoStock($relatedProduct)){
            $this->handleNoStock($orderProduct, $existingOrderProduct);
        }else{
            if($this->hasLimitedStockAtLowest($orderProduct, $relatedProduct)){
                $this->handleLimitedStock($orderProduct, $existingOrderProduct);
            }
            if($this->hasExceededMaximumAllowedQuantityAtLowest($orderProduct, $relatedProduct)){
                $this->handleExceededMaximumAllowedQuantityPerOrder($orderProduct, $existingOrderProduct);
            }
        }

        return $orderProduct;
    }

    /**
     * Handle no stock condition.
     *
     * @param OrderProduct $orderProduct
     * @param OrderProduct|null $existingOrderProduct
     * @return void
     */
    protected function handleNoStock(OrderProduct $orderProduct, OrderProduct|null $existingOrderProduct): void
    {
        $message = $orderProduct->quantity . 'x(' . $orderProduct->name . ') cancelled because it sold out';
        $this->recordOrderProductDetectedChangeAndCancel('no_stock', $message, $orderProduct, $existingOrderProduct);
    }

    /**
     * Handle limited stock condition.
     *
     * @param OrderProduct $orderProduct
     * @param OrderProduct|null $existingOrderProduct
     * @return void
     */
    protected function handleLimitedStock(OrderProduct $orderProduct, OrderProduct|null $existingOrderProduct): void
    {
        $message = $orderProduct->original_quantity . 'x(' . $orderProduct->name . ') reduced to (' . $orderProduct->quantity . ') because of limited stock';
        $this->recordOrderProductDetectedChange('limited_stock', $message, $orderProduct, $existingOrderProduct);
    }

    /**
     * Handle exceeded maximum allowed quantity per order.
     *
     * @param OrderProduct $orderProduct
     * @param OrderProduct|null $existingOrderProduct
     * @return void
     */
    protected function handleExceededMaximumAllowedQuantityPerOrder(OrderProduct $orderProduct, OrderProduct|null $existingOrderProduct): void
    {
        $message = $orderProduct->original_quantity . 'x(' . $orderProduct->name . ') reduced to (' . $orderProduct->quantity . ') because of maximum allowed quantity exceeded';
        $this->recordOrderProductDetectedChange('has_exceeded_maximum_allowed_quantity_per_order', $message, $orderProduct, $existingOrderProduct);
    }

    /**
     * Check if no stock condition is met.
     *
     * @param Product $relatedProduct
     * @return bool
     */
    protected function hasNoStock($relatedProduct): bool
    {
        return !$relatedProduct->has_stock;
    }

    /**
     * Check if limited stock condition is met.
     *
     * @param OrderProduct $orderProduct
     * @param Product $relatedProduct
     * @return bool
     */
    protected function hasLimitedStockAtLowest(OrderProduct $orderProduct, Product $relatedProduct): bool
    {
        $stockQuantity = $relatedProduct->stock_quantity;
        $maximumAllowedQuantityPerOrder = $relatedProduct->maximum_allowed_quantity_per_order;
        return $orderProduct->has_limited_stock && $stockQuantity < $maximumAllowedQuantityPerOrder;
    }

    /**
     * Check if maximum allowed quantity has been exceeded.
     *
     * @param OrderProduct $orderProduct
     * @param Product $relatedProduct
     * @return bool
     */
    protected function hasExceededMaximumAllowedQuantityAtLowest(OrderProduct $orderProduct, Product $relatedProduct): bool
    {
        $stockQuantity = $relatedProduct->stock_quantity;
        $maximumAllowedQuantityPerOrder = $relatedProduct->maximum_allowed_quantity_per_order;
        return $orderProduct->has_exceeded_maximum_allowed_quantity_per_order && $maximumAllowedQuantityPerOrder < $stockQuantity;
    }

    /**
     * Detect changes against the existing order product.
     *
     * @param OrderProduct $orderProduct
     * @param OrderProduct $existingOrderProduct
     * @return OrderProduct
     */
    protected function detectChangesAgainstExistingOrderProduct(OrderProduct $orderProduct, OrderProduct $existingOrderProduct): OrderProduct
    {
        $this->handleExceededToNotExceededMaximumAllowedQuantityPerOrderChanges($orderProduct, $existingOrderProduct);
        $this->handleNoStockToEnoughStockChanges($orderProduct, $existingOrderProduct);
        $this->handleNoStockToLimitedStockChanges($orderProduct, $existingOrderProduct);
        $this->handleLimitedStockToEnoughStock($orderProduct, $existingOrderProduct);
        $this->handleVisibleToNotVisibleChanges($orderProduct, $existingOrderProduct);
        $this->handleNotVisibleToVisibleChanges($orderProduct, $existingOrderProduct);
        $this->handleFreeToNotFreeChanges($orderProduct, $existingOrderProduct);
        $this->handleNotFreeToFreeChanges($orderProduct, $existingOrderProduct);
        $this->handlePriceChanges($orderProduct, $existingOrderProduct);
        $this->handleNameChanges($orderProduct, $existingOrderProduct);

        return $orderProduct;
    }

    /**
     * Handle change from exceeded to not exceeded maximum allowed quantity per order.
     *
     * @param OrderProduct $orderProduct
     * @param OrderProduct $existingOrderProduct
     * @return void
     */
    protected function handleExceededToNotExceededMaximumAllowedQuantityPerOrderChanges(OrderProduct $orderProduct, OrderProduct $existingOrderProduct): void
    {
        if($this->hasChangedFromExceededToNotExceededMaximumQuantity($orderProduct, $existingOrderProduct)){
            $message = $orderProduct->quantity.'x('.$orderProduct->name.') added because larger quantities are now permitted for this item';
            $this->recordOrderProductDetectedChange('exceeded_to_not_has_exceeded_maximum_allowed_quantity_per_order', $message, $orderProduct, $existingOrderProduct);
        }
    }

    /**
     * Handle change from no stock to enough stock.
     *
     * @param OrderProduct $orderProduct
     * @param OrderProduct $existingOrderProduct
     * @return void
     */
    protected function handleNoStockToEnoughStockChanges(OrderProduct $orderProduct, OrderProduct $existingOrderProduct): void
    {
        if($this->hasChangedFromNoStockToEnoughStock($orderProduct, $existingOrderProduct)){
            $message = $orderProduct->quantity.'x('.$orderProduct->name.') added because of new stock';
            $this->recordOrderProductDetectedChange('no_stock_to_enough_stock', $message, $orderProduct, $existingOrderProduct);
        }
    }

    /**
     * Handle change from no stock to limited stock.
     *
     * @param OrderProduct $orderProduct
     * @param OrderProduct $existingOrderProduct
     * @return void
     */
    protected function handleNoStockToLimitedStockChanges(OrderProduct $orderProduct, OrderProduct $existingOrderProduct): void
    {
        if($this->hasChangedFromNoStockToLimitedStock($orderProduct, $existingOrderProduct)){
            $message = $orderProduct->quantity.'x('.$orderProduct->name.') added because of new stock';
            $this->recordOrderProductDetectedChange('no_stock_to_limited_stock', $message, $orderProduct, $existingOrderProduct);
        }
    }

    /**
     * Handle change from limited stock to enough stock.
     *
     * @param OrderProduct $orderProduct
     * @param OrderProduct $existingOrderProduct
     * @return void
     */
    protected function handleLimitedStockToEnoughStock(OrderProduct $orderProduct, OrderProduct $existingOrderProduct): void
    {
        if($this->hasChangedFromLimitedStockToEnoughStock($orderProduct, $existingOrderProduct)){
            $message = $orderProduct->quantity.'x('.$orderProduct->name.') added because of new stock';
            $this->recordOrderProductDetectedChange('limited_stock_to_enough_stock', $message, $orderProduct, $existingOrderProduct);
        }
    }

    /**
     * Handle free to not free change.
     *
     * @param OrderProduct $orderProduct
     * @param OrderProduct $existingOrderProduct
     * @return void
     */
    protected function handleFreeToNotFreeChanges(OrderProduct $orderProduct, OrderProduct $existingOrderProduct): void
    {
        if($this->hasChangedFromFreeToNotFree($orderProduct, $existingOrderProduct)){
            $orderProductUnitPrice = $orderProduct->unit_price->amountWithCurrency;
            $message = $orderProduct->quantity.'x('.$orderProduct->name.') added with new price '.$orderProductUnitPrice.' each';
            $this->recordOrderProductDetectedChange('free_to_not_free', $message, $orderProduct, $existingOrderProduct);
        }
    }

    /**
     * Handle not free to free change.
     *
     * @param OrderProduct $orderProduct
     * @param OrderProduct $existingOrderProduct
     * @return void
     */
    protected function handleNotFreeToFreeChanges(OrderProduct $orderProduct, OrderProduct $existingOrderProduct): void
    {
        if($this->hasChangedFromNotFreeToFree($orderProduct, $existingOrderProduct)){
            $message = $orderProduct->quantity.'x('.$orderProduct->name.') is now free';
            $this->recordOrderProductDetectedChange('not_free_to_free', $message, $orderProduct, $existingOrderProduct);
        }
    }

    /**
     * Handle visible to not visible change.
     *
     * @param OrderProduct $orderProduct
     * @param OrderProduct $existingOrderProduct
     * @return void
     */
    protected function handleVisibleToNotVisibleChanges(OrderProduct $orderProduct, OrderProduct $existingOrderProduct): void
    {
        if($this->hasChangedFromVisibleToNotVisible($orderProduct, $existingOrderProduct)){
            $message = $orderProduct->quantity.'x('.$orderProduct->name.') cancelled because it was removed from the shelf';
            $this->recordOrderProductDetectedChangeAndCancel('not_visible', $message, $orderProduct, $existingOrderProduct);
        }
    }

    /**
     * Handle not visible to visible change.
     *
     * @param OrderProduct $orderProduct
     * @param OrderProduct $existingOrderProduct
     * @return void
     */
    protected function handleNotVisibleToVisibleChanges(OrderProduct $orderProduct, OrderProduct $existingOrderProduct): void
    {
        if($this->hasChangedFromNotVisibleToVisible($orderProduct, $existingOrderProduct)){
            $message = $orderProduct->quantity.'x('.$orderProduct->name.') added because it was placed on the shelf';
            $this->recordOrderProductDetectedChange('visible', $message, $orderProduct, $existingOrderProduct);
        }
    }

    /**
     * Handle old price to new price change.
     *
     * @param OrderProduct $orderProduct
     * @param OrderProduct $existingOrderProduct
     * @return void
     */
    protected function handlePriceChanges(OrderProduct $orderProduct, OrderProduct $existingOrderProduct): void
    {
        if($this->hasPriceChanged($orderProduct, $existingOrderProduct)){

            $inflation = $orderProduct->unit_price > $existingOrderProduct->unit_price ? 'increased' : 'reduced';
            $message = $orderProduct->quantity.'x('.$orderProduct->name.') price '.$inflation.' from '.$existingOrderProduct->unit_price->amountWithCurrency .' to '.$orderProduct->unit_price->amountWithCurrency.' each';

            //  Sale price changes - Was not on sale but the sale started
            if(!$existingOrderProduct->on_sale && $orderProduct->on_sale){

                $message .= ' (On sale)';

                if($inflation == 'increased'){
                    $changeType = 'old_price_to_new_price_increase_with_sale';
                }else{
                    $changeType = 'old_price_to_new_price_decrease_with_sale';
                }

            //  Sale price changes - Was on sale but the sale ended
            }elseif($existingOrderProduct->on_sale && !$orderProduct->on_sale){

                $message .= ' (Sale ended)';

                if($inflation == 'increased'){
                    $changeType = 'old_price_to_new_price_increase_without_sale';
                }else{
                    $changeType = 'old_price_to_new_price_decrease_without_sale';
                }

            //  Regular price changes
            }else{

                if($inflation == 'increased'){
                    $changeType = 'old_price_to_new_price_increase';
                }else{
                    $changeType = 'old_price_to_new_price_decrease';
                }

            }

            $this->recordOrderProductDetectedChange($changeType, $message, $orderProduct, $existingOrderProduct);

        }
    }

    /**
     * Handle name changed event.
     *
     * @param OrderProduct $orderProduct
     * @param OrderProduct $existingOrderProduct
     * @return void
     */
    protected function handleNameChanges(OrderProduct $orderProduct, OrderProduct $existingOrderProduct): void
    {
        if($this->hasNameChanged($orderProduct, $existingOrderProduct)){
            $message = 'Product name has changed';
            $this->recordOrderProductDetectedChange('name_changed', $message, $orderProduct, $existingOrderProduct);
        }
    }

    /**
     * Check if the quantity has changed from exceeding the maximum allowed to not exceeding it.
     *
     * @param OrderProduct $orderProduct The current order product.
     * @param OrderProduct $existingOrderProduct The previous order product.
     * @return bool
     */
    protected function hasChangedFromExceededToNotExceededMaximumQuantity(OrderProduct $orderProduct, OrderProduct $existingOrderProduct): bool
    {
        return $existingOrderProduct->hasDetectedChange('has_exceeded_maximum_allowed_quantity_per_order')
                   && !$orderProduct->hasDetectedChange('has_exceeded_maximum_allowed_quantity_per_order');
    }

    /**
     * Check if the stock status has changed from no stock to enough stock.
     *
     * @param OrderProduct $orderProduct The current order product.
     * @param OrderProduct $existingOrderProduct The previous order product.
     * @return bool
     */
    protected function hasChangedFromNoStockToEnoughStock(OrderProduct $orderProduct, OrderProduct $existingOrderProduct): bool
    {
        return $existingOrderProduct->hasDetectedChange('no_stock')
                   && !$orderProduct->hasDetectedChange('no_stock')
              && !$orderProduct->hasDetectedChange('limited_stock');
    }

    /**
     * Check if the stock status has changed from no stock to limited stock.
     *
     * @param OrderProduct $orderProduct The current order product.
     * @param OrderProduct $existingOrderProduct The previous order product.
     * @return bool
     */
    protected function hasChangedFromNoStockToLimitedStock(OrderProduct $orderProduct, OrderProduct $existingOrderProduct): bool
    {
        return $existingOrderProduct->hasDetectedChange('no_stock')
               && $orderProduct->hasDetectedChange('limited_stock');
    }

    /**
     * Check if the stock status has changed from limited stock to enough stock.
     *
     * @param OrderProduct $orderProduct The current order product.
     * @param OrderProduct $existingOrderProduct The previous order product.
     * @return bool
     */
    protected function hasChangedFromLimitedStockToEnoughStock(OrderProduct $orderProduct, OrderProduct $existingOrderProduct): bool
    {
        return $existingOrderProduct->hasDetectedChange('limited_stock')
                        && !$orderProduct->hasDetectedChange('no_stock')
                   && !$orderProduct->hasDetectedChange('limited_stock');
    }

    /**
     * Check if the product has changed from visible to not visible.
     *
     * @param OrderProduct $orderProduct The current order product.
     * @param OrderProduct $existingOrderProduct The previous order product.
     * @return bool
     */
    protected function hasChangedFromVisibleToNotVisible(OrderProduct $orderProduct, OrderProduct $existingOrderProduct): bool
    {
        return $existingOrderProduct->visible && !$orderProduct->visible;
    }

    /**
     * Check if the product has changed from not visible to visible.
     *
     * @param OrderProduct $orderProduct The current order product.
     * @param OrderProduct $existingOrderProduct The previous order product.
     * @return bool
     */
    protected function hasChangedFromNotVisibleToVisible(OrderProduct $orderProduct, OrderProduct $existingOrderProduct): bool
    {
        return !$existingOrderProduct->visible && $orderProduct->visible;
    }

    /**
     * Check if the product has changed from free to not free.
     *
     * @param OrderProduct $orderProduct The current order product.
     * @param OrderProduct $existingOrderProduct The previous order product.
     * @return bool
     */
    protected function hasChangedFromFreeToNotFree(OrderProduct $orderProduct, OrderProduct $existingOrderProduct): bool
    {
        return $existingOrderProduct->is_free && !$orderProduct->is_free;
    }

    /**
     * Check if the product has changed from not free to free.
     *
     * @param OrderProduct $orderProduct The current order product.
     * @param OrderProduct $existingOrderProduct The previous order product.
     * @return bool
     */
    protected function hasChangedFromNotFreeToFree(OrderProduct $orderProduct, OrderProduct $existingOrderProduct): bool
    {
        return !$existingOrderProduct->is_free && $orderProduct->is_free;
    }

    /**
     * Check if the price has changed.
     *
     * @param OrderProduct $orderProduct The current order product.
     * @param OrderProduct $existingOrderProduct The previous order product.
     * @return bool
     */
    protected function hasPriceChanged(OrderProduct $orderProduct, OrderProduct $existingOrderProduct): bool
    {
        return $existingOrderProduct->unit_price != $orderProduct->unit_price;
    }

    /**
     * Check if the name of the product has changed.
     *
     * @param OrderProduct $orderProduct The current order product.
     * @param OrderProduct $existingOrderProduct The previous order product.
     * @return bool
     */
    protected function hasNameChanged(OrderProduct $orderProduct, OrderProduct $existingOrderProduct): bool
    {
        return $orderProduct->name !== $existingOrderProduct->name;
    }

    /**
     * Record order product detected change and cancel.
     *
     * @param string $type.
     * @param string $message.
     * @param OrderProduct $orderProduct The current order product.
     * @param OrderProduct $existingOrderProduct The previous order product.
     * @return bool
     */
    protected function recordOrderProductDetectedChangeAndCancel(string $type, string $message, &$orderProduct, $existingOrderProduct)
    {
        $this->recordOrderProductDetectedChange($type, $message, $orderProduct, $existingOrderProduct);
        $orderProduct->cancelItemLine($message);
    }

    /**
     * Record order product detected change.
     *
     * @param string $type.
     * @param string $message.
     * @param OrderProduct $orderProduct The current order product.
     * @param OrderProduct $existingOrderProduct The previous order product.
     * @return bool
     */
    protected function recordOrderProductDetectedChange(string $type, string $message, &$orderProduct, $existingOrderProduct)
    {
        $orderProduct->recordDetectedChange($type, $message, $existingOrderProduct);
        $notifiedUser = ($existingOrderProduct === null) ? false : $existingOrderProduct->hasDetectedChange($type);

        if(!$notifiedUser) {
            $lastDetectedChange = $orderProduct->detected_changes[count($orderProduct->detected_changes) - 1];
            array_push($this->detectedOrderProductChanges, $lastDetectedChange);
        }
    }

    /**
     * Record order promotion detected change and cancel.
     *
     * @param string $type.
     * @param string $message.
     * @param OrderPromotion $orderPromotion The current order promotion.
     * @param OrderPromotion $existingOrderPromotion The previous order promotion.
     * @return bool
     */
    protected function recordOrderPromotionDetectedChangeAndCancel(string $type, string $message, &$orderPromotion, $existingOrderPromotion)
    {
        $this->recordOrderPromotionDetectedChange($type, $message, $orderPromotion, $existingOrderPromotion);
        $orderPromotion->cancelItemLine($message);
    }

    /**
     * Record order promotion detected change.
     *
     * @param string $type.
     * @param string $message.
     * @param OrderPromotion $orderPromotion The current order promotion.
     * @param OrderPromotion $existingOrderPromotion The previous order promotion.
     * @return bool
     */
    protected function recordOrderPromotionDetectedChange(string $type, string $message, &$orderPromotion, $existingOrderPromotion)
    {
        $orderPromotion->recordDetectedChange($type, $message, $existingOrderPromotion);
        $notifiedUser = ($existingOrderPromotion === null) ? false : $existingOrderPromotion->hasDetectedChange($type);

        if(!$notifiedUser) {
            $lastDetectedChange = $orderPromotion->detected_changes[count($orderPromotion->detected_changes) - 1];
            array_push($this->detectedOrderPromotionChanges, $lastDetectedChange);
        }
    }

    /**
     * Set specified order promotions based on store promotions and validations.
     *
     * @return void
     */
    public function setSpecifiedOrderPromotions(): void
    {
        if(count($this->storePromotions) === 0){
            $this->specifiedOrderPromotions = [];
            return;
        }

        if($this->isTeamMember) {
            $this->specifiedOrderPromotions = $this->mapTeamMemberProvidedPromotions();
        } else {
            $this->specifiedOrderPromotions = $this->mapRelatedPromotionsToOrderPromotions();
        }
    }

    /**
     *  Map team member provided promotions.
     *
     *  @return array
     */
    protected function mapTeamMemberProvidedPromotions(): array
    {
        return collect($this->cartPromotions)->map(function ($cartPromotion) {
            return $this->mapToOrderPromotionFromTeamMember($cartPromotion);
        })->filter()->all();
    }

    /**
     * Map a team member's provided promotion to an OrderPromotion.
     *
     * @param array $cartPromotion
     * @return OrderPromotion
     */
    private function mapToOrderPromotionFromTeamMember(array $cartPromotion): OrderPromotion
    {
        $promotionId = $cartPromotion['id'] ?? null;

        // Find existing promotion if an ID is provided
        $relatedPromotion = $promotionId ? $this->store->promotions()->find($promotionId) : null;

        // Base attributes from existing promotion if found
        $baseAttributes = $relatedPromotion ? $relatedPromotion->getAttributes() : [
            'offer_discount' => false,
            'offer_free_delivery' => false,
            'discount_percentage_rate' => 0,
            'discount_flat_rate' => "0.00",
            'discount_rate_type' => RateType::FLAT->value
        ];

        // Merge team member's provided attributes (overrides existing ones)
        $orderPromotionAttributes = array_merge(
            $baseAttributes,
            collect($cartPromotion)->only([
                'name', 'description', 'offer_discount', 'offer_free_delivery',
                'discount_rate_type', 'discount_percentage_rate', 'discount_flat_rate'
            ])->toArray(),
            [
                'id' => null,
                'is_cancelled' => false,
                'detected_changes' => [],
                'cancellation_reasons' => [],
                'store_id' => $this->store->id,
                'currency' => $this->store->currency,
                'promotion_id' => $relatedPromotion?->id,
            ]
        );

        return new OrderPromotion($orderPromotionAttributes);
    }

    /**
     *  Map related promotions to order promotions.
     *
     *  @return array
     */
    protected function mapRelatedPromotionsToOrderPromotions(): array
    {
        return collect($this->storePromotions)
            ->map(fn($storePromotion) => $this->mapToOrderPromotion($storePromotion))
            ->filter()
            ->all();
    }

    /**
     *  Map a related promotion to a order promotion.
     *
     * @param object $storePromotion
     * @return OrderPromotion|null
     */
    private function mapToOrderPromotion($storePromotion): ?OrderPromotion
    {
        $inValid = false;
        $cancellationReasons = [];

        $this->validatePromotion($storePromotion, $inValid, $cancellationReasons);

        $existingOrderPromotion = collect($this->existingOrderPromotions)
            ->first(fn($existingOrderPromotion) => $existingOrderPromotion->promotion_id == $storePromotion->id);

        if($inValid && !$existingOrderPromotion){
            return null;
        }

        return $this->prepareOrderPromotion($storePromotion, $inValid, $cancellationReasons, $existingOrderPromotion);
    }

    /**
     * Validate store promotion.
     *
     * @param object $storePromotion
     * @param bool &$inValid
     * @param array $cancellationReasons
     * @return void
     */
    private function validatePromotion($storePromotion, &$inValid, &$cancellationReasons): void
    {
        $invalidate = function ($reason) use (&$inValid, &$cancellationReasons){
            $inValid = true;
            $cancellationReasons[] = $reason;
        };

        if(!$storePromotion->active){
            $invalidate('Deactivated by store');
        }

        if($storePromotion->activate_using_code && $this->promotionCode != $storePromotion->code){
            $invalidate('Required a code for activation but the code provided was invalid');
        }

        if($storePromotion->activate_using_minimum_grand_total && $this->subtotalAfterDiscount < $storePromotion->minimum_grand_total->amount){
            $subtotalAfterDiscount = MoneyService::convertToMoneyFormat($this->subtotalAfterDiscount, $this->currency);
            $invalidate('Required a minimum grand total of ' . $storePromotion->minimum_grand_total->amountWithCurrency .
                ' but the cart total was valued at ' . $subtotalAfterDiscount->amountWithCurrency);
        }

        if($storePromotion->activate_using_minimum_total_products &&
            $this->totalSpecifiedUnCancelledOrderProducts < $storePromotion->minimum_total_products){
            $invalidate('Required a minimum total of ' . $storePromotion->minimum_total_products . ' unique items, ' .
                'but the cart contained ' . $this->totalSpecifiedUnCancelledOrderProducts . ' unique items');
        }

        if($storePromotion->activate_using_minimum_total_product_quantities &&
            $this->totalSpecifiedUncancelledOrderProductQuantities < $storePromotion->minimum_total_product_quantities){
            $invalidate('Required a minimum total of ' . $storePromotion->minimum_total_product_quantities . ' total quantities, ' .
                'but the cart contained ' . $this->totalSpecifiedUncancelledOrderProductQuantities . ' total quantities');
        }

        if($storePromotion->activate_using_start_datetime && Carbon::parse($storePromotion->start_datetime)->isFuture()){
            $invalidate('Starting date was not yet reached');
        }

        if($storePromotion->activate_using_end_datetime && Carbon::parse($storePromotion->end_datetime)->isPast()){
            $invalidate('Ending date was reached');
        }

        if($storePromotion->activate_using_hours_of_day && !in_array(Carbon::now()->format('H:00'), $storePromotion->hours_of_day)){
            $invalidate('Invalid hour of the day (Activated at specific hours of the day)');
        }

        if($storePromotion->activate_using_days_of_the_week && !in_array(Carbon::now()->format('l'), $storePromotion->days_of_the_week)){
            $invalidate('Invalid day of the week (Activated on specific days of the week)');
        }

        if($storePromotion->activate_using_days_of_the_month && !in_array(Carbon::now()->format('d'), $storePromotion->days_of_the_month)){
            $invalidate('Invalid day of the month (Activated on specific days of the month)');
        }

        if($storePromotion->activate_using_months_of_the_year && !in_array(Carbon::now()->format('F'), $storePromotion->months_of_the_year)){
            $invalidate('Invalid month of the year (Activated on specific months of the year)');
        }

        if($storePromotion->activate_for_new_customer){
            if($this->isExistingCustomer === true){
                $invalidate('Must be a new customer');
            } elseif($this->isExistingCustomer === null){
                $invalidate('Cannot determine if this is a new customer. Customer mobile number or email has not been provided');
            }
        }

        if($storePromotion->activate_for_existing_customer){
            if($this->isExistingCustomer === false){
                $invalidate('Must be an existing customer');
            } elseif($this->isExistingCustomer === null){
                $invalidate('Cannot determine if this is an existing customer. Customer mobile number or email has not been provided');
            }
        }

        if($storePromotion->activate_using_usage_limit && $storePromotion->remaining_quantity == 0){
            $invalidate('The usage limit was reached');
        }
    }

    /**
     * Check if a promotion code can be applied.
     *
     * @param object $storePromotion
     * @return bool
     */
    private function checkIfCanApplyPromotionCode($storePromotion): bool
    {
        if(!$storePromotion->active) {
            return false;
        }

        if(!$storePromotion->activate_using_code) {
            return false;
        }

        if($storePromotion->activate_using_minimum_grand_total && $this->subtotalAfterDiscount < $storePromotion->minimum_grand_total->amount) {
            return false;
        }

        if($storePromotion->activate_using_minimum_total_products && $this->totalSpecifiedUnCancelledOrderProducts < $storePromotion->minimum_total_products) {
            return false;
        }

        if($storePromotion->activate_using_minimum_total_product_quantities && $this->totalSpecifiedUncancelledOrderProductQuantities < $storePromotion->minimum_total_product_quantities) {
            return false;
        }

        if($storePromotion->activate_using_start_datetime && Carbon::parse($storePromotion->start_datetime)->isFuture()) {
            return false;
        }

        if($storePromotion->activate_using_end_datetime && Carbon::parse($storePromotion->end_datetime)->isPast()) {
            return false;
        }

        if($storePromotion->activate_using_hours_of_day && !in_array(Carbon::now()->format('H:00'), $storePromotion->hours_of_day)) {
            return false;
        }

        if($storePromotion->activate_using_days_of_the_week && !in_array(Carbon::now()->format('l'), $storePromotion->days_of_the_week)) {
            return false;
        }

        if($storePromotion->activate_using_days_of_the_month && !in_array(Carbon::now()->format('d'), $storePromotion->days_of_the_month)) {
            return false;
        }

        if($storePromotion->activate_using_months_of_the_year && !in_array(Carbon::now()->format('F'), $storePromotion->months_of_the_year)) {
            return false;
        }

        if($storePromotion->activate_for_new_customer) {
            if($this->isExistingCustomer === true || $this->isExistingCustomer === null) {
                return false;
            }
        }

        if($storePromotion->activate_for_existing_customer) {
            if($this->isExistingCustomer === false || $this->isExistingCustomer === null) {
                return false;
            }
        }

        if($storePromotion->activate_using_usage_limit && $storePromotion->remaining_quantity == 0) {
            return false;
        }

        return true;
    }

    /**
     * Prepare order promotion.
     *
     * @param object $storePromotion
     * @param bool $inValid
     * @param array $cancellationReasons
     * @param object|null $existingOrderPromotion
     * @return OrderPromotion
     */
    private function prepareOrderPromotion($storePromotion, $inValid, $cancellationReasons, $existingOrderPromotion): OrderPromotion
    {
        $orderPromotion = new OrderPromotion(
            collect($storePromotion->getAttributes())->merge([
                'detected_changes' => [],
                'is_cancelled' => $inValid,
                'store_id' => $this->store->id,
                'promotion_id' => $storePromotion->id,
                'cancellation_reasons' => $cancellationReasons
            ])->toArray()
        );

        if($existingOrderPromotion){
            $wasCancelledAndIsStillInvalid = $existingOrderPromotion->is_cancelled && $inValid;
            $wasNotCancelledButIsNowInvalid = !$existingOrderPromotion->is_cancelled && $inValid;

            if($wasNotCancelledButIsNowInvalid || $wasCancelledAndIsStillInvalid){
                $message = 'The (' . $storePromotion->name . ') promotion was cancelled because it is no longer valid';
                $this->recordOrderPromotionDetectedChangeAndCancel('cancelled', $message, $orderPromotion, $existingOrderPromotion);
            } else {
                $message = 'The (' . $storePromotion->name . ') promotion was added because it is valid again';
                $this->recordOrderPromotionDetectedChange('uncancelled', $message, $orderPromotion, $existingOrderPromotion);
            }
        }

        return $orderPromotion;
    }

    /**
     * Set can apply promotion code.
     *
     * @return void
     */
    public function setCanApplyPromotionCode(): void
    {
        $this->canApplyPromotionCode = collect($this->storePromotions)->contains(function ($storePromotion) {
            return $this->checkIfCanApplyPromotionCode($storePromotion);
        });
    }

    /**
     * Set promotion discount applied by code.
     *
     * @return void
     */
    public function setPromotionDiscountAppliedByCode(): void
    {
        if($this->canApplyPromotionCode) {

            $discountingOrderPromotion = collect($this->getSpecifiedUnCancelledOrderPromotions())->first(function ($orderPromotion) {
                return $orderPromotion->offer_discount && $orderPromotion->activate_using_code && $orderPromotion->code == $this->promotionCode;
            });

            if($discountingOrderPromotion) {
                $this->promotionApplied = true;
                $this->promotionName = $discountingOrderPromotion->name;

                if($discountingOrderPromotion->discount_rate_type == RateType::FLAT->value) {
                    $totalDiscount = MoneyService::convertToMoneyFormat($discountingOrderPromotion->discount_flat_rate->amount, $this->currency);
                    $this->promotionMessage = 'A discount of'.$totalDiscount->amountWithCurrency.' has been applied';
                }else if($discountingOrderPromotion->discount_rate_type == RateType::PERCENTAGE->value) {
                    $totalDiscount = MoneyService::convertToMoneyFormat($this->subtotalAfterDiscount * ($discountingOrderPromotion->discount_percentage_rate / 100), $this->currency);
                    $this->promotionMessage = 'A '.($discountingOrderPromotion->discount_percentage_rate).'% discount ('.$totalDiscount->amountWithCurrency.') has been applied';
                }

                $otherTotalDiscount = collect($this->getSpecifiedUnCancelledOrderPromotions())->filter(function ($orderPromotion) {
                    $promotion = collect($this->storePromotions)->firstWhere('id', $orderPromotion->promotion_id);
                    return $promotion->offer_discount && !$promotion->activate_using_code;
                })->sum(function ($orderPromotion) {
                    if($orderPromotion->discount_rate_type == RateType::FLAT->value) {
                        $totalDiscount = $orderPromotion->discount_flat_rate->amount;
                    }else if($orderPromotion->discount_rate_type == RateType::PERCENTAGE->value) {
                        $totalDiscount = $this->subtotalAfterDiscount * ($orderPromotion->discount_percentage_rate / 100);

                    }
                    return $totalDiscount;
                });

                if($otherTotalDiscount > 0) {
                    $otherTotalDiscount = MoneyService::convertToMoneyFormat($otherTotalDiscount, $this->currency);
                    $this->promotionMessage .= ', along with additional discounts of '.$otherTotalDiscount->amountWithCurrency.' from other offers.';
                }

            }

        }
    }

    public function getSpecifiedCancelledOrderProducts()
    {
        return collect($this->specifiedOrderProducts)->filter(fn($orderProduct) => $orderProduct->is_cancelled)->all();
    }

    public function getSpecifiedUnCancelledOrderProducts()
    {
        return collect($this->specifiedOrderProducts)->filter(fn($orderProduct) => !$orderProduct->is_cancelled)->all();
    }

    public function countSpecifiedOrderProducts()
    {
        return collect($this->specifiedOrderProducts)->count();
    }

    public function countSpecifiedCancelledOrderProducts()
    {
        return collect($this->getSpecifiedCancelledOrderProducts())->count();
    }

    public function countSpecifiedUnCancelledOrderProducts()
    {
        return collect($this->getSpecifiedUnCancelledOrderProducts())->count();
    }

    public function countSpecifiedOrderProductQuantities()
    {
        return collect($this->specifiedOrderProducts)->sum('quantity');
    }

    public function countSpecifiedCancelledOrderProductQuantities()
    {
        return collect($this->getSpecifiedCancelledOrderProducts())->sum('quantity');
    }

    public function countSpecifiedUncancelledOrderProductQuantities()
    {
        return collect($this->getSpecifiedUnCancelledOrderProducts())->sum('quantity');
    }

    public function getSpecifiedCancelledOrderPromotions()
    {
        return collect($this->specifiedOrderPromotions)->filter(fn($orderPromotion) => $orderPromotion->is_cancelled)->all();
    }

    public function getSpecifiedUnCancelledOrderPromotions()
    {
        return collect($this->specifiedOrderPromotions)->filter(fn($orderPromotion) => !$orderPromotion->is_cancelled)->all();
    }

    public function countSpecifiedOrderPromotions()
    {
        return collect($this->specifiedOrderPromotions)->count();
    }

    public function countSpecifiedCancelledOrderPromotions()
    {
        return collect($this->getSpecifiedCancelledOrderPromotions())->count();
    }

    public function countSpecifiedUnCancelledOrderPromotions()
    {
        return collect($this->getSpecifiedUnCancelledOrderPromotions())->count();
    }

    public function handleDeliveryMethod()
    {
        if(!$this->deliveryMethod) return;
        $this->validateDeliveryMethod();
        $this->setIfDeliveryMethodAddressIsRequired();
        $this->setIfDeliveryMethodScheduleIsRequired();
        $this->validateDeliveryMethodSchedule();
        $this->setDeliveryMethodTips();
        $this->setIfFeeDelivery();
    }

    /**
     * Validate delivery method.
     *
     * @return void
     */
    private function validateDeliveryMethod(): void
    {
        $this->deliveryMethodAvailable = true;

        $disqualify = function ($message) {
            array_push($this->deliveryMethodUnavailabilityReasons, $message);
            if($this->deliveryMethodAvailable) $this->deliveryMethodAvailable = false;
        };

        if($this->deliveryMethod->qualify_on_minimum_grand_total && $this->subtotalAfterDiscount < $this->deliveryMethod->minimum_grand_total->amount){
            $disqualify('Minimum order amount is '.$this->deliveryMethod->minimum_grand_total->amountWithCurrency.' for '.$this->deliveryMethod->name);
        }
    }

    /**
     * Set if delivery method address is required.
     *
     * @return void
     */
    private function setIfDeliveryMethodAddressIsRequired(): void
    {
        $this->pinLocationOnMap =
            $this->deliveryMethod->pin_location_on_map ||
            ($this->deliveryMethod->charge_fee && in_array($this->deliveryMethod->fee_type, [DeliveryMethodFeeType::FEE_BY_DISTANCE->value, DeliveryMethodFeeType::FEE_BY_POSTAL_CODE->value]));

        $this->deliveryAddressIsRequired = $this->deliveryMethod->ask_for_an_address || $this->pinLocationOnMap;
    }

    /**
     * Set if delivery method schedule is required.
     *
     * @return void
     */
    private function setIfDeliveryMethodScheduleIsRequired(): void
    {
        $this->scheduleIsRequired = $this->deliveryMethod->set_schedule;
    }

    /**
     * Validate delivery method schedule.
     *
     * @return void
     */
    private function validateDeliveryMethodSchedule(): void
    {
        if($this->scheduleIsRequired) {

            $this->scheduleIsComplete = true;

            $disqualify = function ($message) {
                array_push($this->scheduleIncompleteReasons, $message);
                if($this->scheduleIsComplete) $this->scheduleIsComplete = false;
            };

            if(!$this->deliveryDate){
                $disqualify('The delivery date is required');
            }else if(!$this->isTeamMember && !$this->deliveryMethod->isValidDate($this->deliveryDate)){
                $disqualify('The selected delivery date is unavailable');
            }else if($this->deliveryMethod->schedule_type == DeliveryMethodScheduleType::DATE_AND_TIME->value && !$this->deliveryTimeslot){
                $disqualify('The delivery time is required');
            }else if(!$this->isTeamMember && $this->deliveryMethod->schedule_type == DeliveryMethodScheduleType::DATE_AND_TIME->value && !$this->deliveryMethod->isValidTimeSlot($this->deliveryDate, $this->deliveryTimeslot)){
                $disqualify('The selected delivery time is unavailable');
            }

        }
    }

    /**
     * Set delivery method tips.
     *
     * @return void
     */
    private function setDeliveryMethodTips(): void
    {
        $tip = function ($message){
            array_push($this->deliveryMethodTips, $message);
        };

        if($this->deliveryMethod->offer_free_delivery_on_minimum_grand_total && ($this->subtotalAfterDiscount < $this->deliveryMethod->free_delivery_minimum_grand_total->amount)) {
            $tip('Minimum order amount is '.$this->deliveryMethod->free_delivery_minimum_grand_total->amountWithCurrency.' for free delivery');
        }
    }

    /**
     * Set if free delivery.
     *
     * @return void
     */
    private function setIfFeeDelivery(): void
    {
        if($this->deliveryMethod->qualify_on_minimum_grand_total && ($this->subtotalAfterDiscount >= $this->deliveryMethod->minimum_grand_total->amount)) {
            $this->freeDelivery = true;
        }
    }

    /**
     * Calculate order product totals.
     *
     * @return void
     */
    private function calculateOrderProductTotals(): void
    {
        collect($this->getSpecifiedUnCancelledOrderProducts())->each(function($orderProduct){
            $this->subtotal += $orderProduct->subtotal->amount;
            $this->subtotalAfterDiscount += $orderProduct->subtotal->amount;
            $this->addDiscount('sale discount', $orderProduct->sale_discount_total->amount);
        });
    }

    /**
     * Apply order promotion discounts.
     *
     * @return void
     */
    private function applyOrderPromotionDiscounts(): void
    {
        $discounts = [];

        collect($this->getSpecifiedUnCancelledOrderPromotions())->each(function($orderPromotion) use (&$discounts) {
            if(!$orderPromotion->offer_discount) return;

            if($orderPromotion->discount_rate_type == RateType::FLAT->value) {
                $totalDiscount = $orderPromotion->discount_flat_rate->amount;
            }else if($orderPromotion->discount_rate_type == RateType::PERCENTAGE->value) {
                $totalDiscount = $this->subtotalAfterDiscount * ($orderPromotion->discount_percentage_rate / 100);
            }

            $discounts[] = [
                'name' => $orderPromotion->name,
                'total' => $totalDiscount
            ];
        });

        collect($discounts)->each(function($discount) {
            $this->addDiscount($discount['name'], $discount['total']);
        });
    }

    /**
     * Add discount.
     *
     * @return void
     */
    private function addDiscount($name, $amount): void
    {
        // Update or add the discount
        $this->discounts[$name] = [
            'name' => ucfirst($name),
            'amount' => MoneyService::convertToMoneyFormat(($this->discounts[$name]['amount']->amount ?? 0) + $amount, $this->currency)
        ];

        // Update totals
        $this->discountTotal += $amount;
        $this->subtotalAfterDiscount -= $amount;
    }

    /**
     * Add fee.
     *
     * @param array $data
     * @return void
     */
    private function addFee(array $data): void
    {
        $name = $data['name'];
        $amount = $data['amount'];
        $keyName = $data['key_name'];
        $rateType = $data['rate_type'];
        $percentageRate = $data['percentage_rate'];

        // Update or add the fee
        $this->fees[$keyName ?? $name] = [
            'name' => ucfirst($name),
            'rate_type' => $rateType,
            'percentage_rate' => $percentageRate,
            'amount' => MoneyService::convertToMoneyFormat(($this->fees[$name]['amount']->amount ?? 0) + $amount, $this->currency)
        ];

        // Update totals
        $this->feeTotal += $amount;
    }

    /**
     * Calculate tax totals.
     *
     * @return void
     */
    private function calculateTaxTotals(): void
    {
        $this->vatRate = $this->store->tax_percentage_rate;

        if($this->store->tax_method == TaxMethod::EXCLUSIVE->value) {
            $this->vat = $this->subtotalAfterDiscount * ($this->store->tax_percentage_rate / 100);
        }else{
            $this->vat = $this->subtotalAfterDiscount * ($this->vatRate / (100 + $this->vatRate));
        }
    }

    /**
     * Calculate fee totals.
     *
     * @return void
     */
    private function calculateFeeTotals(): void
    {
        if($this->isTeamMember) {
            $this->calculateCartFeeTotals();
        }else{
            $this->calculateCustomFeeTotals();
            $this->calculateDeliveryFeeTotals();
            $this->calculateTipFeeTotals();
        }
    }

    /**
     * Calculate cart fee totals.
     *
     * @return void
     */
    private function calculateCartFeeTotals(): void
    {
        collect($this->cartFees)->each(function($cartFee) {
            $this->handleCartFlatFee($cartFee);
            $this->handleCartPercentageFee($cartFee);
        });
    }

    /**
     * Calculate custom fee totals.
     *
     * @return void
     */
    private function calculateCustomFeeTotals(): void
    {
        collect($this->store->checkout_fees)->each(function($checkoutFee) {
            $this->handleCustomFlatFee($checkoutFee);
            $this->handleCustomPercentageFee($checkoutFee);
        });
    }

    /**
     * Handle cart flat fee.
     *
     * @param array $cartFee
     * @return void
     */
    private function handleCartFlatFee(array $cartFee): void
    {
        $name = $cartFee['name'];
        $active = $cartFee['active'];
        $rateType = $cartFee['rate_type'];
        $flatRate = $cartFee['flat_rate'];

        if(empty($name) || $active !== true) return;

        if($rateType == RateType::FLAT->value) {

            $data = [
                'name' => $name,
                'key_name' => null,
                'amount' => $flatRate,
                'rate_type' => $rateType,
                'percentage_rate' => null
            ];

            $this->addFee($data);
        }
    }

    /**
     * Handle cart percentage fee.
     *
     * @param array $cartFee
     * @return void
     */
    private function handleCartPercentageFee(array $cartFee): void
    {
        $name = $cartFee['name'];
        $active = $cartFee['active'];
        $rateType = $cartFee['rate_type'];
        $percentageRate = $cartFee['percentage_rate'];

        if(empty($name) || $active !== true) return;

        if($rateType == RateType::PERCENTAGE->value) {

            $amount = $this->subtotalAfterDiscount * ($percentageRate / 100);

            $data = [
                'name' => $name,
                'amount' => $amount,
                'key_name' => null,
                'rate_type' => $rateType,
                'percentage_rate' => $percentageRate
            ];

            $this->addFee($data);
        }
    }

    /**
     * Handle custom flat fee.
     *
     * @param array $checkoutFee
     * @return void
     */
    private function handleCustomFlatFee(array $checkoutFee): void
    {
        $name = $checkoutFee['name'];
        $rateType = $checkoutFee['rate_type'];
        $amount = $checkoutFee['flat_rate']->amount;

        if($rateType == RateType::FLAT->value) {

            $data = [
                'name' => $name,
                'amount' => $amount,
                'key_name' => null,
                'rate_type' => $rateType,
                'percentage_rate' => null
            ];

            $this->addFee($data);
        }
    }

    /**
     * Handle custom percentage fee.
     *
     * @param array $checkoutFee
     * @return void
     */
    private function handleCustomPercentageFee(array $checkoutFee): void
    {
        $name = $checkoutFee['name'];
        $rateType = $checkoutFee['rate_type'];
        $percentageRate = $checkoutFee['percentage_rate']['value'];

        if($rateType == RateType::PERCENTAGE->value) {

            $amount = $this->subtotalAfterDiscount * ($percentageRate / 100);

            $data = [
                'name' => $name,
                'amount' => $amount,
                'key_name' => null,
                'rate_type' => $rateType,
                'percentage_rate' => $percentageRate
            ];

            $this->addFee($data);
        }
    }

    /**
     * Calculate delivery fee totals.
     *
     * @return void
     */
    private function calculateDeliveryFeeTotals(): void
    {
        if(!$this->deliveryMethodAvailable) return;
        if(!$this->deliveryMethod->charge_fee) return;

        $this->handleDeliveryFlatFee();
        $this->handleDeliveryFeeByWeight();
        $this->handleDeliveryPercentageFee();
        $this->handleDeliveryFeeByDistance();
        $this->handleDeliveryFeeByPostalCode();
    }

    /**
     * Handle delivery flat fee.
     *
     * @return void
     */
    private function handleDeliveryFlatFee(): void
    {
        if($this->deliveryMethod->fee_type != DeliveryMethodFeeType::FLAT_FEE->value) return;

        $name = 'Delivery fee';
        $rateType = RateType::FLAT->value;
        $amount = $this->freeDelivery ? 0 : $this->deliveryMethod->flat_fee_rate->amount;

        $data = [
            'name' => $name,
            'amount' => $amount,
            'key_name' => null,
            'rate_type' => $rateType,
            'percentage_rate' => null
        ];

        $this->addFee($data);
    }

    /**
     * Handle delivery percentage fee.
     *
     * @return void
     */
    private function handleDeliveryPercentageFee(): void
    {
        if($this->deliveryMethod->fee_type != DeliveryMethodFeeType::PERCENTAGE_FEE->value) return;

        $name = 'Delivery fee';
        $rateType = RateType::PERCENTAGE->value;
        $percentageRate = $this->deliveryMethod->percentage_fee_rate;
        $amount = $this->freeDelivery ? 0 : $this->subtotalAfterDiscount * ($percentageRate / 100);

        $data = [
            'name' => $name,
            'amount' => $amount,
            'key_name' => null,
            'rate_type' => $rateType,
            'percentage_rate' => $percentageRate
        ];

        $this->addFee($data);
    }

    /**
     * Handle delivery fee by distance.
     *
     * @return void
     */
    private function handleDeliveryFeeByDistance(): void
    {
        if($this->deliveryMethod->fee_type !== DeliveryMethodFeeType::FEE_BY_DISTANCE->value) return;

        $storeLocation = $this->deliveryMethod->address;
        $customerLocation = $this->deliveryAddress;

        if(!$storeLocation || ($storeLocation && (empty($storeLocation->latitude) || empty($storeLocation->longitude)))) {
            return;
        }

        if(!$customerLocation || ($customerLocation && (empty($customerLocation->latitude) || empty($customerLocation->longitude)))) {
            return;
        }

        $origin = "{$storeLocation['latitude']},{$storeLocation['longitude']}";
        $destination = "{$customerLocation['latitude']},{$customerLocation['longitude']}";

        [$this->deliveryDistance, $this->deliveryDuration] = $this->getDistanceFromGoogleMaps($origin, $destination);

        if($this->deliveryDistance) {

            foreach ($this->deliveryMethod->distance_zones as $zone) {

                if($this->deliveryDistance['value'] <= $zone['distance']) {

                    $rateType = RateType::FLAT->value;
                    $amount = $this->freeDelivery ? 0 : $zone['fee'];
                    $name = 'Delivery fee ('.$this->deliveryDistance['text'].')';

                    $data = [
                        'name' => $name,
                        'amount' => $amount,
                        'rate_type' => $rateType,
                        'percentage_rate' => null,
                        'key_name' => 'Delivery fee',
                    ];

                    $this->addFee($data);
                    return;

                }

            }

        }

        // If no zone matches, apply fallback fee
        $this->addFallbackFee($this->deliveryDistance['text'] ?? null);
    }

    /**
     * Handle delivery fee by postal code.
     *
     * @return void
     */
    private function handleDeliveryFeeByPostalCode(): void
    {
        if($this->deliveryMethod->fee_type !== DeliveryMethodFeeType::FEE_BY_POSTAL_CODE->value) return;

        // Retrieve the customer's postal code or attempt to fetch it using coordinates
        $customerPostalCode = $this->deliveryAddress->postal_code ?? null;

        if(!$customerPostalCode && $this->deliveryAddress->latitude && $this->deliveryAddress->longitude) {
            $customerPostalCode = $this->getPostalCodeFromCoordinates(
                $this->deliveryAddress->latitude,
                $this->deliveryAddress->longitude
            );
        }

        if($customerPostalCode) {
            foreach ($this->deliveryMethod->postal_code_zones as $zone) {
                foreach ($zone['postal_codes'] as $postalCode) {
                    if($this->isPostalCodeMatch($customerPostalCode, $postalCode)) {

                        $rateType = RateType::FLAT->value;
                        $amount = $this->freeDelivery ? 0 : $zone['fee'];
                        $name = 'Delivery fee (Zone ' . $postalCode . ')';

                        $data = [
                            'name' => $name,
                            'amount' => $amount,
                            'rate_type' => $rateType,
                            'percentage_rate' => null,
                            'key_name' => 'Delivery fee',
                        ];

                        $this->addFee($data);
                        return;
                    }
                }
            }
        }

        // If no zone matches, apply fallback fee
        $this->addFallbackFee($customerPostalCode ? 'Zone ' . $customerPostalCode . ')' : null);
    }

    /**
     * Handle delivery fee by weight.
     *
     * @return void
     */
    private function handleDeliveryFeeByWeight(): void
    {
        if($this->deliveryMethod->fee_type !== DeliveryMethodFeeType::FEE_BY_WEIGHT->value) return;

        $totalWeight = $this->calculateTotalWeight();
        $weightUnit = $this->store->weight_unit;

        $this->deliveryWeight = [
            'unit' => $weightUnit,
            'value' => $totalWeight,
            'text' => $totalWeight . $weightUnit
        ];

        foreach ($this->deliveryMethod->weight_categories as $category) {
            foreach ($category['weights'] as $weight) {
                if($this->isWeightMatch($totalWeight, $weight)) {

                    $rateType = RateType::FLAT->value;
                    $name = 'Delivery fee ('.$weight . $weightUnit.')';
                    $amount = $this->freeDelivery ? 0 : $category['fee'];

                    $data = [
                        'name' => $name,
                        'amount' => $amount,
                        'rate_type' => $rateType,
                        'percentage_rate' => null,
                        'key_name' => 'Delivery fee',
                    ];

                    $this->addFee($data);
                    return;
                }
            }
        }

        // If no category matches, apply fallback fee
        $this->addFallbackFee($this->deliveryWeight['text']);
    }

    /**
     * Check if a postal code matches a specific postal code or range.
     *
     * @param string $customerPostalCode
     * @param string $postalCode
     * @return bool
     */
    private function isPostalCodeMatch(string $customerPostalCode, string $postalCode): bool
    {
        // Check if the postal code is a range (e.g., "2000-2020")
        if(str_contains($postalCode, '-')) {
            [$start, $end] = explode('-', $this->sanitizePostalCode($postalCode));

            // Ensure both start and end are valid numbers
            if(is_numeric($start) && is_numeric($end)) {
                return $customerPostalCode >= $start && $customerPostalCode <= $end;
            }
        }

        // Otherwise, check for an exact match or prefix match
        return str_starts_with($customerPostalCode, $postalCode);
    }

    /**
     * Sanitize a postal code by trimming spaces and normalizing format.
     *
     * @param string $postalCode
     * @return string
     */
    private function sanitizePostalCode(string $postalCode): string
    {
        // Trim spaces and normalize the range format (e.g., "2000 - 2020" to "2000-2020")
        return preg_replace('/\s+/', '', $postalCode);
    }

    /**
     * Check if a total weight matches a specific weight or range.
     *
     * @return float
     */
    private function calculateTotalWeight(): float
    {
        return collect($this->getSpecifiedUnCancelledOrderProducts())->map(function($orderProduct) {
            return $orderProduct->quantity * ($orderProduct->unit_weight ?? 0);
        })->sum();
    }

    /**
     * Check if a total weight matches a specific weight or range.
     *
     * @param float $totalWeight
     * @param string $weight
     * @return bool
     */
    private function isWeightMatch(float $totalWeight, string $weight): bool
    {
        // Check if the weight is a range (e.g., "0-5")
        if(str_contains($weight, '-')) {
            [$start, $end] = explode('-', $weight);

            // Ensure both start and end are valid numbers
            if(is_numeric($start) && is_numeric($end)) {
                return $totalWeight >= (float)$start && $totalWeight <= (float)$end;
            }
        }

        // Otherwise, check for an exact match
        return (float)$totalWeight === (float)$weight;
    }

    /**
     * Get distance and duration information from Google Maps.
     *
     * @param string $origin
     * @param string $destination
     * @return array
     */
    private function getDistanceFromGoogleMaps(string $origin, string $destination): array
    {
        $apiKey = config('services.google_maps.key');
        $url = "https://maps.googleapis.com/maps/api/distancematrix/json?origins=$origin&destinations=$destination&key=$apiKey";

        $response = Http::get($url);

        if($response->successful()) {
            $data = $response->json();

            if(!empty($data['rows'][0]['elements'][0]['distance']['value']) &&
                !empty($data['rows'][0]['elements'][0]['duration']['value'])) {

                $distanceInMeters = $data['rows'][0]['elements'][0]['distance']['value'];
                $durationInSeconds = $data['rows'][0]['elements'][0]['duration']['value'];

                // Convert distance to the preferred unit
                $distanceUnit = $this->store->distance_unit ?? DistanceUnit::KM->value;
                $distance = $this->convertDistance($distanceInMeters, $distanceUnit);

                return [
                    [
                        'value' => $distance,
                        'unit' => $distanceUnit,
                        'text' => $this->formatDistance($distance, $distanceUnit),
                    ],
                    [
                        'value' => $durationInSeconds,
                        'text' => $data['rows'][0]['elements'][0]['duration']['text'],
                    ],
                ];
            }
        }

        return [null, null];
    }

    /**
     * Get postal code from Google Maps based on latitude and longitude.
     *
     * @param float $latitude
     * @param float $longitude
     * @return string|null
     */
    private function getPostalCodeFromCoordinates(float $latitude, float $longitude): ?string
    {
        $apiKey = config('services.google_maps.key');
        $url = "https://maps.googleapis.com/maps/api/geocode/json?latlng=$latitude,$longitude&key=$apiKey";

        $response = Http::get($url);

        if($response->successful()) {
            $data = $response->json();

            if(!empty($data['results'])) {
                foreach ($data['results'] as $result) {
                    foreach ($result['address_components'] as $component) {
                        if(in_array('postal_code', $component['types'])) {
                            return $component['long_name']; // Return the postal code
                        }
                    }
                }
            }
        }

        return null;
    }


    /**
     * Convert distance from meters to the desired unit.
     *
     * @param float $meters
     * @param string $unit
     * @return float
     */
    private function convertDistance(float $meters, string $unit): float
    {
        if($unit === DistanceUnit::MILE->value) {
            return round($meters * 0.000621371, 2); // Convert meters to miles
        }

        // Default is kilometers
        return $meters / 1000;
    }

    /**
     * Format distance with unit.
     *
     * @param float $distance
     * @param string $unit
     * @return string
     */
    private function formatDistance(float $distance, string $unit): string
    {
        return sprintf('%.2f %s', $distance, $unit === DistanceUnit::MILE->value ? 'miles' : 'km');
    }

    /**
     * Add a fallback fee when other calculations fail.
     *
     * @param string|null $unit
     * @return void
     */
    private function addFallbackFee(string|null $info = null): void
    {
        $name = 'Delivery fee';
        if($info) $name .= ' ('.$info.')';
        $rateType = $this->deliveryMethod->fallback_fee_type;

        switch ($rateType) {
            case DeliveryMethodFeeType::FLAT_FEE->value:
                $amount = $this->freeDelivery ? 0 : $this->deliveryMethod->fallback_flat_fee_rate->amount;

                $data = [
                    'name' => $name,
                    'amount' => $amount,
                    'rate_type' => $rateType,
                    'percentage_rate' => null,
                    'key_name' => 'Delivery fee',
                ];

                $this->addFee($data);
                break;

            case DeliveryMethodFeeType::PERCENTAGE_FEE->value:

                $percentageRate = $this->deliveryMethod->fallback_percentage_fee_rate;
                $amount = $this->freeDelivery ? 0 : $this->subtotalAfterDiscount * ($percentageRate / 100);

                $data = [
                    'name' => $name,
                    'amount' => $amount,
                    'rate_type' => $rateType,
                    'key_name' => 'Delivery fee',
                    'percentage_rate' => $percentageRate,
                ];

                $this->addFee($data);

                break;
        }
    }



    /**
     * Calculate tip fee totals.
     *
     * @return void
     */
    private function calculateTipFeeTotals(): void
    {
        $this->handleFlatFeeTip();
        $this->handlePercentageFeeTip();
    }

    /**
     * Handle flat fee tip.
     *
     * @return void
     */
    private function handleFlatFeeTip(): void
    {
        if(!is_null($this->tipFlatRate)) {

            $name = 'Tip';
            $amount = (float) $this->tipFlatRate;

            $data = [
                'name' => $name,
                'key_name' => null,
                'amount' => $amount,
                'percentage_rate' => null,
                'rate_type' => RateType::FLAT->value
            ];

            $this->addFee($data);
        }
    }

    /**
     * Handle percentage fee tip.
     *
     * @return void
     */
    private function handlePercentageFeeTip(): void
    {
        if(!is_null($this->tipPercentageRate)) {
            $name = 'Tip';
            $percentageRate = $this->tipPercentageRate;
            $amount = $this->subtotalAfterDiscount * ($percentageRate / 100);

            $data = [
                'name' => $name,
                'key_name' => null,
                'amount' => $amount,
                'rate_type' => RateType::FLAT->value,
                'percentage_rate' => $percentageRate
            ];

            $this->addFee($data);
        }
    }

    /**
     * Calculate grand total.
     *
     * @return void
     */
    private function calculateGrandTotal(): void
    {
        if ($this->store->tax_method == TaxMethod::EXCLUSIVE->value) {
            $this->grandTotal = $this->subtotalAfterDiscount + $this->vat + $this->feeTotal + $this->adjustmentTotal;
        } else {
            $this->grandTotal = $this->subtotalAfterDiscount + $this->feeTotal + $this->adjustmentTotal;
        }

        if ($this->adjustmentTotal < 0) {
            if ($this->grandTotal + $this->adjustmentTotal < 0) {
                $this->adjustmentTotal = -$this->grandTotal;
                $this->grandTotal = 0;
            }
        }
    }

    /**
     * Determine if can checkout.
     *
     * @return bool
     */
    private function canCheckout(): bool
    {
        return $this->totalSpecifiedUnCancelledOrderProducts > 0 && $this->deliveryMethodAvailable;
    }

    /**
     * Get transformed order products.
     *
     * @return array
     */
    private function getTransformedOrderProducts(): array
    {
        return collect($this->specifiedOrderProducts)->map(function($orderProduct) {
            return [
                'name' => $orderProduct->name,
                'is_free' => $orderProduct->is_free,
                'on_sale' => $orderProduct->on_sale,
                'subtotal' => $orderProduct->subtotal,
                'quantity' => $orderProduct->quantity,
                'product_id' => $orderProduct->product_id,
                'unit_price' => $orderProduct->unit_price,
                'grand_total' => $orderProduct->grand_total,
                'description' => $orderProduct->description,
                'is_cancelled' => $orderProduct->is_cancelled,
                'unit_sale_price' => $orderProduct->unit_sale_price,
                'detected_changes' => $orderProduct->detected_changes,
                'original_quantity' => $orderProduct->original_quantity,
                'has_limited_stock' => $orderProduct->has_limited_stock,
                'unit_sale_discount' => $orderProduct->unit_sale_discount,
                'unit_regular_price' => $orderProduct->unit_regular_price,
                'sale_discount_total' => $orderProduct->sale_discount_total,
                'cancellation_reasons' => $orderProduct->cancellation_reasons,
                'unit_sale_discount_percentage' => $orderProduct->unit_sale_discount_percentage,
                'has_exceeded_maximum_allowed_quantity_per_order' => $orderProduct->has_exceeded_maximum_allowed_quantity_per_order,
            ];
        })->all();

    }

    /**
     * Get transformed order promotions.
     *
     * @return array
     */
    private function getTransformedOrderPromotions(): array
    {
        return collect($this->specifiedOrderPromotions)->map(function($orderPromotion) {
            return [
                'name' => $orderPromotion->name,
                'promotion_id' => $orderPromotion->promotion_id,
                'description' => $orderPromotion->description,
                'is_cancelled' => $orderPromotion->is_cancelled,
                'detected_changes' => $orderPromotion->detected_changes,
                'cancellation_reasons' => $orderPromotion->cancellation_reasons,
            ];
        })->all();
    }
}
