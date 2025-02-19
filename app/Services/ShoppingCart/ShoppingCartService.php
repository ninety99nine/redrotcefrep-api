<?php

namespace App\Services\ShoppingCart;

use Carbon\Carbon;
use App\Models\Store;
use App\Models\Product;
use App\Models\Address;
use App\Enums\CacheName;
use App\Enums\TaxMethod;
use App\Models\CouponLine;
use App\Models\ProductLine;
use App\Enums\DiscountType;
use App\Helpers\CacheManager;
use App\Enums\CheckoutFeeType;
use App\Traits\Base\BaseTrait;
use App\Enums\StockQuantityType;
use App\Enums\DeliveryMethodFeeType;
use Illuminate\Support\Facades\Http;
use App\Enums\AllowedQuantityPerOrder;
use App\Enums\DeliveryMethodScheduleType;
use App\Enums\DistanceUnit;

class ShoppingCartService
{
    use BaseTrait;

    public $store;
    public $vat = null;
    public $subtotal = 0;
    public $feeTotal = 0;
    public $address = null;
    public $vatRate = null;
    public $discounts = [];
    public $grandTotal = 0;
    public $currency = null;
    public $discountTotal = 0;
    public $tipFlatRate = null;
    public $cartProducts = [];
    public $storeCoupons = [];
    public $additionalFees = [];
    public $deliveryDate = null;
    public $existingCart = null;
    public $relatedProducts = [];
    public $deliveryMethod = null;
    public $promotionCode = null;
    public $promotionName = null;
    public $deliveryWeight = null;
    public $deliveryDistance = null;
    public $deliveryDuration = null;
    public $promotionMessage = null;
    public $deliveryTimeslot = null;
    public $pinLocationOnMap = null;
    public $deliveryMethodTips = [];
    public $existingCouponLines = [];
    public $promotionApplied = false;
    public $tipPercentageRate = null;
    public $addressIsRequired = null;
    public $addressIsComplete = false;
    public $scheduleIsRequired = null;
    public $scheduleIsComplete = null;
    public $isExistingCustomer = null;
    public $subtotalAfterDiscount = 0;
    public $existingProductLines = [];
    public $specifiedCouponLines = [];
    public $specifiedProductLines = [];
    public $canApplyPromotionCode = false;
    public $scheduleIncompleteReasons = [];
    public $detectedCouponLineChanges = [];
    public $deliveryMethodAvailable = null;
    public $availableDeliveryTimeSlots = [];
    public $detectedProductLineChanges = [];
    public $deliveryMethodUnavailabilityReasons = [];
    public $totalSpecifiedUnCancelledProductLines = 0;
    public $totalSpecifiedUncancelledProductLineQuantities = 0;

    public function startInspection(Store $store)
    {
        $this->setStore($store);
        $this->setStoreCoupons();
        $this->setStoreCurrency();

        $this->setCartProducts();
        $this->setPromotionCode();
        $this->setCartTipRate();
        $this->setAddress();

        $this->setExistingCustomerStatus();
        $this->setExistingShoppingCartFromCache();

        $this->setSpecifiedProductLines();
        $this->calculateProductLineTotals();
        $totalSpecifiedProductLines = $this->countSpecifiedProductLines();
        $totalSpecifiedCancelledProductLines = $this->countSpecifiedCancelledProductLines();
        $this->totalSpecifiedUnCancelledProductLines = $this->countSpecifiedUnCancelledProductLines();
        $totalSpecifiedProductLineQuantities = $this->countSpecifiedProductLineQuantities();
        $totalSpecifiedCancelledProductLineQuantities = $this->countSpecifiedCancelledProductLineQuantities();
        $this->totalSpecifiedUncancelledProductLineQuantities = $this->countSpecifiedUncancelledProductLineQuantities();

        $this->setSpecifiedCouponLines();

        $totalSpecifiedCouponLines = $this->countSpecifiedCouponLines();
        $totalSpecifiedCancelledCouponLines = $this->countSpecifiedCancelledCouponLines();
        $totalSpecifiedUnCancelledCouponLines = $this->countSpecifiedUnCancelledCouponLines();

        $this->setDeliveryDate();
        $this->setDeliveryMethod();
        $this->setDeliveryTimeslot();
        $this->handleDeliveryMethod();

        $this->setCanApplyPromotionCode();
        $this->setCouponDiscountAppliedByCode();
        $this->applyCouponLineDiscounts();
        $this->calculateTaxTotals();
        $this->calculateCustomFeeTotals();
        $this->calculateDeliveryFeeTotals();
        $this->calculateTipFeeTotals();
        $this->calculateGrandTotal();

        $vatRate = $this->convertToPercentageFormat($this->vatRate);
        $vat = $this->convertToMoneyFormat($this->vat, $this->currency);
        $subtotal = $this->convertToMoneyFormat($this->subtotal, $this->currency);
        $feeTotal = $this->convertToMoneyFormat($this->feeTotal, $this->currency);
        $grandTotal = $this->convertToMoneyFormat($this->grandTotal, $this->currency);
        $discountTotal = $this->convertToMoneyFormat($this->discountTotal, $this->currency);
        $subtotalAfterDiscount = $this->convertToMoneyFormat($this->subtotalAfterDiscount, $this->currency);

        return [
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
                'additional_fees' => $this->additionalFees,
                'fee_total' => $feeTotal,
                'grand_total' => $grandTotal,
            ],
            'totals_summary' => [
                'product_lines' => [
                    'total' => $totalSpecifiedProductLines,
                    'total_cancelled' => $totalSpecifiedCancelledProductLines,
                    'total_uncancelled' => $this->totalSpecifiedUnCancelledProductLines,
                    'total_quantities' => $totalSpecifiedProductLineQuantities,
                    'total_cancelled_quantities' => $totalSpecifiedCancelledProductLineQuantities,
                    'total_uncancelled_quantities' => $this->totalSpecifiedUncancelledProductLineQuantities,
                ],
                'coupon_lines' => [
                    'total' => $totalSpecifiedCouponLines,
                    'total_cancelled' => $totalSpecifiedCancelledCouponLines,
                    'total_uncancelled' => $totalSpecifiedUnCancelledCouponLines,
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
                    'name' => $this->deliveryMethod->name,
                    'is_available' => $this->deliveryMethodAvailable,
                    'unavailability_reasons' => $this->deliveryMethodUnavailabilityReasons,
                    'tips' => $this->deliveryMethodTips,
                ] : null,
                'weight' => $this->deliveryWeight,
                'distance' => $this->deliveryDistance,
                'duration' => $this->deliveryDuration
            ],
            'schedule' => [
                'is_required' => $this->scheduleIsRequired,
                'is_complete' => $this->scheduleIsComplete,
                'incomplete_reasons' => $this->scheduleIncompleteReasons
            ],
            'address' => [
                'is_required' => $this->addressIsRequired,
                'is_complete' => $this->addressIsComplete,
                'pin_location_on_map' => $this->pinLocationOnMap,
                'incomplete_reasons' => $this->scheduleIncompleteReasons,
            ],
            'changes' => [
                'detected_product_line_changes' => $this->detectedProductLineChanges,
                'detected_coupon_line_changes' => $this->detectedCouponLineChanges,
            ],
            'checkout' => [
                'can_checkout' => $this->canCheckout(),
            ],
            'product_lines' => $this->getTransformedProductLines(),
            'coupon_lines' => $this->getTransformedCouponLines(),
        ];

        //  deliveryTimeslots

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
     *  Set store coupons.
     *
     *  @return void
     */
    public function setStoreCoupons(): void
    {
        $this->storeCoupons = $this->store->coupons;
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
     *  Set cart products.
     *
     *  @return void
     */
    public function setCartProducts(): void
    {
        $this->cartProducts = is_string($cartProducts = request()->input('cart_products')) ? json_decode($cartProducts) : $cartProducts;
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
     *  Set address.
     *
     *  @return void
     */
    public function setAddress(): void
    {
        if(request()->has('address')) {

            $attributes = request()->input('address');

            $address = new Address();
            $address->fill($attributes);

            $this->address = $address;
            $this->addressIsComplete = true;

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
        if($this->getShoppingCartCacheManager()->has()){

            //  Get the shopping cart stored in memory (cached)
            $this->existingCart = $this->getShoppingCartCacheManager()->get();

            //  If we have an existing cached cart
            if($this->existingCart){

                //  Get the existing product lines of the cached cart
                $this->existingProductLines = $this->existingCart->productLines;

                //  Get the existing coupon lines of the cached cart
                $this->existingCouponLines = $this->existingCart->couponLines;

            }

        }
    }

    /**
     *  Set specified product lines based on cart products.
     *
     *  @return void
     */
    public function setSpecifiedProductLines(): void
    {
        $cartProductIds = $this->cartProductIds();
        if(empty($cartProductIds)) return;

        $this->relatedProducts = Product::forStore($this->store->id)
            ->whereIn('id', $cartProductIds)
            ->doesNotSupportVariations()
            ->get();

        $this->specifiedProductLines = $this->mapRelatedProductsToProductLines();
    }

    /**
     *  Get cart product IDs.
     *
     *  @return array
     */
    public function cartProductIds(): array
    {
        return collect($this->cartProducts)->pluck('id')->toArray();
    }

    /**
     *  Map related products to product lines.
     *
     *  @return array
     */
    protected function mapRelatedProductsToProductLines(): array
    {
        return collect($this->relatedProducts)
            ->map(fn($relatedProduct) => $this->mapToProductLine($relatedProduct))
            ->all();
    }

    /**
     *  Map a related product to a product line.
     *
     *  @param Product $relatedProduct The related product.
     *  @return ProductLine|null
     */
    protected function mapToProductLine($relatedProduct): ?ProductLine
    {
        $existingProductLine = collect($this->existingProductLines)->firstWhere('product_id', $relatedProduct->id);

        $productLine = $this->prepareProductLine($relatedProduct);
        $productLine = $this->detectChangesAgainstRelatedProduct($productLine, $relatedProduct, $existingProductLine);
        $productLine = $this->detectChangesAgainstExistingProductLine($productLine, $existingProductLine);

        return $productLine;
    }

    /**
     *  Calculate the quantity of a product line.
     *
     *  @param Product $relatedProduct The related product.
     *  @param int $originalQuantity The original quantity from the cart.
     *  @return array
     */
    protected function calculateProductLineQuantity($relatedProduct, int $originalQuantity): array
    {
        $hasLimitedStock = false;
        $quantity = $originalQuantity;
        $hasStock = $relatedProduct->has_stock;
        $hasExceededMaximumAllowedQuantityPerOrder = false;

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

        return [$quantity, $hasStock, $hasLimitedStock, $hasExceededMaximumAllowedQuantityPerOrder];
    }

    /**
     *  Calculate the subtotal of a product line.
     *
     *  @param Product $relatedProduct The related product.
     *  @param int $quantity The quantity of the product line.
     *  @return float
     */
    protected function calculateProductLineSubtotal($relatedProduct, int $quantity): float
    {
        return $relatedProduct->getRawOriginal('unit_regular_price') * $quantity;
    }

    /**
     *  Calculate the grand total of a product line.
     *
     *  @param Product $relatedProduct The related product.
     *  @param int $quantity The quantity of the product line.
     *  @return float
     */
    protected function calculateProductLineGrandTotal($relatedProduct, int $quantity): float
    {
        return $relatedProduct->getRawOriginal('unit_price') * $quantity;
    }

    /**
     *  Calculate the total sale discount for a product line.
     *
     *  @param Product $relatedProduct The related product.
     *  @param int $quantity The quantity of the product line.
     *  @return float
     */
    protected function calculateProductLineSaleDiscountTotal($relatedProduct, int $quantity): float
    {
        return $relatedProduct->getRawOriginal('unit_sale_discount') * $quantity;
    }

    /**
     * Prepare product line.
     *
     * @param Product $relatedProduct The related product.
     * @return ProductLine
     */
    private function prepareProductLine($relatedProduct): ProductLine
    {
        $cartProduct = collect($this->cartProducts)->first(fn($cartProduct) => $relatedProduct->id == $cartProduct['id']);

        $originalQuantity = $cartProduct['quantity'];
        [$quantity, $hasStock, $hasLimitedStock, $hasExceededMaximumAllowedQuantityPerOrder] = $this->calculateProductLineQuantity($relatedProduct, $originalQuantity);

        $subtotal = $this->calculateProductLineSubtotal($relatedProduct, $quantity);
        $grandTotal = $this->calculateProductLineGrandTotal($relatedProduct, $quantity);
        $saleDiscountTotal = $this->calculateProductLineSaleDiscountTotal($relatedProduct, $quantity);

        $productLine = new ProductLine(array_merge($relatedProduct->getAttributes(), [
            'quantity' => $quantity,
            'is_cancelled' => false,
            'subtotal' => $subtotal,
            'detected_changes' => [],
            'grand_total' => $grandTotal,
            'cancellation_reasons' => [],
            'store_id' => $this->store->id,
            'product_id' => $relatedProduct->id,
            'has_limited_stock' => $hasLimitedStock,
            'original_quantity' => $originalQuantity,
            'sale_discount_total' => $saleDiscountTotal,
            'has_exceeded_maximum_allowed_quantity_per_order' => $hasExceededMaximumAllowedQuantityPerOrder,
        ]));

        return $productLine;
    }

    /**
     * Detect changes against the related product.
     *
     * @param ProductLine $productLine
     * @param Product $relatedProduct
     * @param ProductLine|null $existingProductLine
     * @return ProductLine
     */
    protected function detectChangesAgainstRelatedProduct(ProductLine $productLine, Product $relatedProduct, ProductLine|null $existingProductLine): ProductLine
    {
        if($this->hasNoStock($relatedProduct)){
            $this->handleNoStock($productLine, $existingProductLine);
        }else{
            if($this->hasLimitedStockAtLowest($productLine, $relatedProduct)){
                $this->handleLimitedStock($productLine, $existingProductLine);
            }
            if($this->hasExceededMaximumAllowedQuantityAtLowest($productLine, $relatedProduct)){
                $this->handleExceededMaximumAllowedQuantityPerOrder($productLine, $existingProductLine);
            }
        }

        return $productLine;
    }

    /**
     * Handle no stock condition.
     *
     * @param ProductLine $productLine
     * @param ProductLine|null $existingProductLine
     * @return void
     */
    protected function handleNoStock(ProductLine $productLine, ProductLine|null $existingProductLine): void
    {
        $message = $productLine->quantity . 'x(' . $productLine->name . ') cancelled because it sold out';
        $this->recordProductLineDetectedChangeAndCancel('no_stock', $message, $productLine, $existingProductLine);
    }

    /**
     * Handle limited stock condition.
     *
     * @param ProductLine $productLine
     * @param ProductLine|null $existingProductLine
     * @return void
     */
    protected function handleLimitedStock(ProductLine $productLine, ProductLine|null $existingProductLine): void
    {
        $message = $productLine->original_quantity . 'x(' . $productLine->name . ') reduced to (' . $productLine->quantity . ') because of limited stock';
        $this->recordProductLineDetectedChange('limited_stock', $message, $productLine, $existingProductLine);
    }

    /**
     * Handle exceeded maximum allowed quantity per order.
     *
     * @param ProductLine $productLine
     * @param ProductLine|null $existingProductLine
     * @return void
     */
    protected function handleExceededMaximumAllowedQuantityPerOrder(ProductLine $productLine, ProductLine|null $existingProductLine): void
    {
        $message = $productLine->original_quantity . 'x(' . $productLine->name . ') reduced to (' . $productLine->quantity . ') because of maximum allowed quantity exceeded';
        $this->recordProductLineDetectedChange('has_exceeded_maximum_allowed_quantity_per_order', $message, $productLine, $existingProductLine);
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
     * @param ProductLine $productLine
     * @param Product $relatedProduct
     * @return bool
     */
    protected function hasLimitedStockAtLowest(ProductLine $productLine, Product $relatedProduct): bool
    {
        $stockQuantity = $relatedProduct->stock_quantity;
        $maximumAllowedQuantityPerOrder = $relatedProduct->maximum_allowed_quantity_per_order;
        return $productLine->has_limited_stock && $stockQuantity < $maximumAllowedQuantityPerOrder;
    }

    /**
     * Check if maximum allowed quantity has been exceeded.
     *
     * @param ProductLine $productLine
     * @param Product $relatedProduct
     * @return bool
     */
    protected function hasExceededMaximumAllowedQuantityAtLowest(ProductLine $productLine, Product $relatedProduct): bool
    {
        $stockQuantity = $relatedProduct->stock_quantity;
        $maximumAllowedQuantityPerOrder = $relatedProduct->maximum_allowed_quantity_per_order;
        return $productLine->has_exceeded_maximum_allowed_quantity_per_order && $maximumAllowedQuantityPerOrder < $stockQuantity;
    }

    /**
     * Detect changes against the existing product line.
     *
     * @param ProductLine $productLine
     * @param ProductLine|null $existingProductLine
     * @return ProductLine
     */
    protected function detectChangesAgainstExistingProductLine(ProductLine $productLine, ?ProductLine $existingProductLine): ProductLine
    {
        if(!$existingProductLine) return $productLine;

        $this->handleExceededToNotExceededMaximumAllowedQuantityPerOrderChanges($productLine, $existingProductLine);
        $this->handleNoStockToEnoughStockChanges($productLine, $existingProductLine);
        $this->handleNoStockToLimitedStockChanges($productLine, $existingProductLine);
        $this->handleLimitedStockToEnoughStock($productLine, $existingProductLine);
        $this->handleVisibleToNotVisibleChanges($productLine, $existingProductLine);
        $this->handleNotVisibleToVisibleChanges($productLine, $existingProductLine);
        $this->handleFreeToNotFreeChanges($productLine, $existingProductLine);
        $this->handleNotFreeToFreeChanges($productLine, $existingProductLine);
        $this->handlePriceChanges($productLine, $existingProductLine);
        $this->handleNameChanges($productLine, $existingProductLine);

        return $productLine;
    }

    /**
     * Handle change from exceeded to not exceeded maximum allowed quantity per order.
     *
     * @param ProductLine $productLine
     * @param ProductLine $existingProductLine
     * @return void
     */
    protected function handleExceededToNotExceededMaximumAllowedQuantityPerOrderChanges(ProductLine $productLine, ProductLine $existingProductLine): void
    {
        if($this->hasChangedFromExceededToNotExceededMaximumQuantity($productLine, $existingProductLine)){
            $message = $productLine->quantity.'x('.$productLine->name.') added because larger quantities are now permitted for this item';
            $this->recordProductLineDetectedChange('exceeded_to_not_has_exceeded_maximum_allowed_quantity_per_order', $message, $productLine, $existingProductLine);
        }
    }

    /**
     * Handle change from no stock to enough stock.
     *
     * @param ProductLine $productLine
     * @param ProductLine $existingProductLine
     * @return void
     */
    protected function handleNoStockToEnoughStockChanges(ProductLine $productLine, ProductLine $existingProductLine): void
    {
        if($this->hasChangedFromNoStockToEnoughStock($productLine, $existingProductLine)){
            $message = $productLine->quantity.'x('.$productLine->name.') added because of new stock';
            $this->recordProductLineDetectedChange('no_stock_to_enough_stock', $message, $productLine, $existingProductLine);
        }
    }

    /**
     * Handle change from no stock to limited stock.
     *
     * @param ProductLine $productLine
     * @param ProductLine $existingProductLine
     * @return void
     */
    protected function handleNoStockToLimitedStockChanges(ProductLine $productLine, ProductLine $existingProductLine): void
    {
        if($this->hasChangedFromNoStockToLimitedStock($productLine, $existingProductLine)){
            $message = $productLine->quantity.'x('.$productLine->name.') added because of new stock';
            $this->recordProductLineDetectedChange('no_stock_to_limited_stock', $message, $productLine, $existingProductLine);
        }
    }

    /**
     * Handle change from limited stock to enough stock.
     *
     * @param ProductLine $productLine
     * @param ProductLine $existingProductLine
     * @return void
     */
    protected function handleLimitedStockToEnoughStock(ProductLine $productLine, ProductLine $existingProductLine): void
    {
        if($this->hasChangedFromLimitedStockToEnoughStock($productLine, $existingProductLine)){
            $message = $productLine->quantity.'x('.$productLine->name.') added because of new stock';
            $this->recordProductLineDetectedChange('limited_stock_to_enough_stock', $message, $productLine, $existingProductLine);
        }
    }

    /**
     * Handle free to not free change.
     *
     * @param ProductLine $productLine
     * @param ProductLine $existingProductLine
     * @return void
     */
    protected function handleFreeToNotFreeChanges(ProductLine $productLine, ProductLine $existingProductLine): void
    {
        if($this->hasChangedFromFreeToNotFree($productLine, $existingProductLine)){
            $productLineUnitPrice = $productLine->unit_price->amountWithCurrency;
            $message = $productLine->quantity.'x('.$productLine->name.') added with new price '.$productLineUnitPrice.' each';
            $this->recordProductLineDetectedChange('free_to_not_free', $message, $productLine, $existingProductLine);
        }
    }

    /**
     * Handle not free to free change.
     *
     * @param ProductLine $productLine
     * @param ProductLine $existingProductLine
     * @return void
     */
    protected function handleNotFreeToFreeChanges(ProductLine $productLine, ProductLine $existingProductLine): void
    {
        if($this->hasChangedFromNotFreeToFree($productLine, $existingProductLine)){
            $message = $productLine->quantity.'x('.$productLine->name.') is now free';
            $this->recordProductLineDetectedChange('not_free_to_free', $message, $productLine, $existingProductLine);
        }
    }

    /**
     * Handle visible to not visible change.
     *
     * @param ProductLine $productLine
     * @param ProductLine $existingProductLine
     * @return void
     */
    protected function handleVisibleToNotVisibleChanges(ProductLine $productLine, ProductLine $existingProductLine): void
    {
        if($this->hasChangedFromVisibleToNotVisible($productLine, $existingProductLine)){
            $message = $productLine->quantity.'x('.$productLine->name.') cancelled because it was removed from the shelf';
            $this->recordProductLineDetectedChangeAndCancel('not_visible', $message, $productLine, $existingProductLine);
        }
    }

    /**
     * Handle not visible to visible change.
     *
     * @param ProductLine $productLine
     * @param ProductLine $existingProductLine
     * @return void
     */
    protected function handleNotVisibleToVisibleChanges(ProductLine $productLine, ProductLine $existingProductLine): void
    {
        if($this->hasChangedFromNotVisibleToVisible($productLine, $existingProductLine)){
            $message = $productLine->quantity.'x('.$productLine->name.') added because it was placed on the shelf';
            $this->recordProductLineDetectedChange('visible', $message, $productLine, $existingProductLine);
        }
    }

    /**
     * Handle old price to new price change.
     *
     * @param ProductLine $productLine
     * @param ProductLine $existingProductLine
     * @return void
     */
    protected function handlePriceChanges(ProductLine $productLine, ProductLine $existingProductLine): void
    {
        if($this->hasPriceChanged($productLine, $existingProductLine)){

            $inflation = $productLine->unit_price > $existingProductLine->unit_price ? 'increased' : 'reduced';
            $message = $productLine->quantity.'x('.$productLine->name.') price '.$inflation.' from '.$existingProductLine->unit_price->amountWithCurrency .' to '.$productLine->unit_price->amountWithCurrency.' each';

            //  Sale price changes - Was not on sale but the sale started
            if(!$existingProductLine->on_sale && $productLine->on_sale){

                $message .= ' (On sale)';

                if($inflation == 'increased'){
                    $changeType = 'old_price_to_new_price_increase_with_sale';
                }else{
                    $changeType = 'old_price_to_new_price_decrease_with_sale';
                }

            //  Sale price changes - Was on sale but the sale ended
            }elseif($existingProductLine->on_sale && !$productLine->on_sale){

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

            $this->recordProductLineDetectedChange($changeType, $message, $productLine, $existingProductLine);

        }
    }

    /**
     * Handle name changed event.
     *
     * @param ProductLine $productLine
     * @param ProductLine $existingProductLine
     * @return void
     */
    protected function handleNameChanges(ProductLine $productLine, ProductLine $existingProductLine): void
    {
        if($this->hasNameChanged($productLine, $existingProductLine)){
            $message = 'Product name has changed';
            $this->recordProductLineDetectedChange('name_changed', $message, $productLine, $existingProductLine);
        }
    }

    /**
     * Check if the quantity has changed from exceeding the maximum allowed to not exceeding it.
     *
     * @param ProductLine $productLine The current product line.
     * @param ProductLine $existingProductLine The previous product line.
     * @return bool
     */
    protected function hasChangedFromExceededToNotExceededMaximumQuantity(ProductLine $productLine, ProductLine $existingProductLine): bool
    {
        return $existingProductLine->hasDetectedChange('has_exceeded_maximum_allowed_quantity_per_order')
                   && !$productLine->hasDetectedChange('has_exceeded_maximum_allowed_quantity_per_order');
    }

    /**
     * Check if the stock status has changed from no stock to enough stock.
     *
     * @param ProductLine $productLine The current product line.
     * @param ProductLine $existingProductLine The previous product line.
     * @return bool
     */
    protected function hasChangedFromNoStockToEnoughStock(ProductLine $productLine, ProductLine $existingProductLine): bool
    {
        return $existingProductLine->hasDetectedChange('no_stock')
                   && !$productLine->hasDetectedChange('no_stock')
              && !$productLine->hasDetectedChange('limited_stock');
    }

    /**
     * Check if the stock status has changed from no stock to limited stock.
     *
     * @param ProductLine $productLine The current product line.
     * @param ProductLine $existingProductLine The previous product line.
     * @return bool
     */
    protected function hasChangedFromNoStockToLimitedStock(ProductLine $productLine, ProductLine $existingProductLine): bool
    {
        return $existingProductLine->hasDetectedChange('no_stock')
               && $productLine->hasDetectedChange('limited_stock');
    }

    /**
     * Check if the stock status has changed from limited stock to enough stock.
     *
     * @param ProductLine $productLine The current product line.
     * @param ProductLine $existingProductLine The previous product line.
     * @return bool
     */
    protected function hasChangedFromLimitedStockToEnoughStock(ProductLine $productLine, ProductLine $existingProductLine): bool
    {
        return $existingProductLine->hasDetectedChange('limited_stock')
                        && !$productLine->hasDetectedChange('no_stock')
                   && !$productLine->hasDetectedChange('limited_stock');
    }

    /**
     * Check if the product has changed from visible to not visible.
     *
     * @param ProductLine $productLine The current product line.
     * @param ProductLine $existingProductLine The previous product line.
     * @return bool
     */
    protected function hasChangedFromVisibleToNotVisible(ProductLine $productLine, ProductLine $existingProductLine): bool
    {
        return $existingProductLine->visible && !$productLine->visible;
    }

    /**
     * Check if the product has changed from not visible to visible.
     *
     * @param ProductLine $productLine The current product line.
     * @param ProductLine $existingProductLine The previous product line.
     * @return bool
     */
    protected function hasChangedFromNotVisibleToVisible(ProductLine $productLine, ProductLine $existingProductLine): bool
    {
        return !$existingProductLine->visible && $productLine->visible;
    }

    /**
     * Check if the product has changed from free to not free.
     *
     * @param ProductLine $productLine The current product line.
     * @param ProductLine $existingProductLine The previous product line.
     * @return bool
     */
    protected function hasChangedFromFreeToNotFree(ProductLine $productLine, ProductLine $existingProductLine): bool
    {
        return $existingProductLine->is_free && !$productLine->is_free;
    }

    /**
     * Check if the product has changed from not free to free.
     *
     * @param ProductLine $productLine The current product line.
     * @param ProductLine $existingProductLine The previous product line.
     * @return bool
     */
    protected function hasChangedFromNotFreeToFree(ProductLine $productLine, ProductLine $existingProductLine): bool
    {
        return !$existingProductLine->is_free && $productLine->is_free;
    }

    /**
     * Check if the price has changed.
     *
     * @param ProductLine $productLine The current product line.
     * @param ProductLine $existingProductLine The previous product line.
     * @return bool
     */
    protected function hasPriceChanged(ProductLine $productLine, ProductLine $existingProductLine): bool
    {
        return $existingProductLine->unit_price != $productLine->unit_price;
    }

    /**
     * Check if the name of the product has changed.
     *
     * @param ProductLine $productLine The current product line.
     * @param ProductLine $existingProductLine The previous product line.
     * @return bool
     */
    protected function hasNameChanged(ProductLine $productLine, ProductLine $existingProductLine): bool
    {
        return $productLine->name !== $existingProductLine->name;
    }

    /**
     * Record product line detected change and cancel.
     *
     * @param string $type.
     * @param string $message.
     * @param ProductLine $productLine The current product line.
     * @param ProductLine $existingProductLine The previous product line.
     * @return bool
     */
    protected function recordProductLineDetectedChangeAndCancel(string $type, string $message, &$productLine, $existingProductLine)
    {
        $this->recordProductLineDetectedChange($type, $message, $productLine, $existingProductLine);
        $productLine->cancelItemLine($message);
    }

    /**
     * Record product line detected change.
     *
     * @param string $type.
     * @param string $message.
     * @param ProductLine $productLine The current product line.
     * @param ProductLine $existingProductLine The previous product line.
     * @return bool
     */
    protected function recordProductLineDetectedChange(string $type, string $message, &$productLine, $existingProductLine)
    {
        $productLine->recordDetectedChange($type, $message, $existingProductLine);
        $notifiedUser = ($existingProductLine === null) ? false : $existingProductLine->hasDetectedChange($type);

        if(!$notifiedUser) {
            $lastDetectedChange = $productLine->detected_changes[count($productLine->detected_changes) - 1];
            array_push($this->detectedProductLineChanges, $lastDetectedChange);
        }
    }

    /**
     * Record coupon line detected change and cancel.
     *
     * @param string $type.
     * @param string $message.
     * @param CouponLine $couponLine The current coupon line.
     * @param CouponLine $existingCouponLine The previous coupon line.
     * @return bool
     */
    protected function recordCouponLineDetectedChangeAndCancel(string $type, string $message, &$couponLine, $existingCouponLine)
    {
        $this->recordCouponLineDetectedChange($type, $message, $couponLine, $existingCouponLine);
        $couponLine->cancelItemLine($message);
    }

    /**
     * Record coupon line detected change.
     *
     * @param string $type.
     * @param string $message.
     * @param CouponLine $couponLine The current coupon line.
     * @param CouponLine $existingCouponLine The previous coupon line.
     * @return bool
     */
    protected function recordCouponLineDetectedChange(string $type, string $message, &$couponLine, $existingCouponLine)
    {
        $couponLine->recordDetectedChange($type, $message, $existingCouponLine);
        $notifiedUser = ($existingCouponLine === null) ? false : $existingCouponLine->hasDetectedChange($type);

        if(!$notifiedUser) {
            $lastDetectedChange = $couponLine->detected_changes[count($couponLine->detected_changes) - 1];
            array_push($this->detectedCouponLineChanges, $lastDetectedChange);
        }
    }

    /**
     * Set specified coupon lines based on store coupons and validations.
     *
     * @return void
     */
    public function setSpecifiedCouponLines(): void
    {
        if(count($this->storeCoupons) === 0){
            $this->specifiedCouponLines = [];
            return;
        }

        $this->specifiedCouponLines = $this->mapRelatedCouponsToCouponLines();
    }

    /**
     *  Map related coupons to coupon lines.
     *
     *  @return array
     */
    protected function mapRelatedCouponsToCouponLines(): array
    {
        return collect($this->storeCoupons)
            ->map(fn($storeCoupon) => $this->mapToCouponLine($storeCoupon))
            ->filter()
            ->all();
    }

    /**
     *  Map a related coupon to a coupon line.
     *
     * @param object $storeCoupon
     * @return CouponLine|null
     */
    private function mapToCouponLine($storeCoupon): ?CouponLine
    {
        $inValid = false;
        $cancellationReasons = [];

        $this->validateCoupon($storeCoupon, $inValid, $cancellationReasons);

        $existingCouponLine = collect($this->existingCouponLines)
            ->first(fn($existingCouponLine) => $existingCouponLine->coupon_id == $storeCoupon->id);

        if($inValid && !$existingCouponLine){
            return null;
        }

        return $this->prepareCouponLine($storeCoupon, $inValid, $cancellationReasons, $existingCouponLine);
    }

    /**
     * Validate store coupon.
     *
     * @param object $storeCoupon
     * @param bool &$inValid
     * @param array $cancellationReasons
     * @return void
     */
    private function validateCoupon($storeCoupon, &$inValid, &$cancellationReasons): void
    {
        $invalidate = function ($reason) use (&$inValid, &$cancellationReasons){
            $inValid = true;
            $cancellationReasons[] = $reason;
        };

        if(!$storeCoupon->active){
            $invalidate('Deactivated by store');
        }

        if($storeCoupon->activate_using_code && $this->promotionCode != $storeCoupon->code){
            $invalidate('Required a code for activation but the code provided was invalid');
        }

        if($storeCoupon->activate_using_minimum_grand_total && $this->subtotalAfterDiscount < $storeCoupon->minimum_grand_total->amount){
            $subtotalAfterDiscount = $this->convertToMoneyFormat($this->subtotalAfterDiscount, $this->currency);
            $invalidate('Required a minimum grand total of ' . $storeCoupon->minimum_grand_total->amountWithCurrency .
                ' but the cart total was valued at ' . $subtotalAfterDiscount->amountWithCurrency);
        }

        if($storeCoupon->activate_using_minimum_total_products &&
            $this->totalSpecifiedUnCancelledProductLines < $storeCoupon->minimum_total_products){
            $invalidate('Required a minimum total of ' . $storeCoupon->minimum_total_products . ' unique items, ' .
                'but the cart contained ' . $this->totalSpecifiedUnCancelledProductLines . ' unique items');
        }

        if($storeCoupon->activate_using_minimum_total_product_quantities &&
            $this->totalSpecifiedUncancelledProductLineQuantities < $storeCoupon->minimum_total_product_quantities){
            $invalidate('Required a minimum total of ' . $storeCoupon->minimum_total_product_quantities . ' total quantities, ' .
                'but the cart contained ' . $this->totalSpecifiedUncancelledProductLineQuantities . ' total quantities');
        }

        if($storeCoupon->activate_using_start_datetime && Carbon::parse($storeCoupon->start_datetime)->isFuture()){
            $invalidate('Starting date was not yet reached');
        }

        if($storeCoupon->activate_using_end_datetime && Carbon::parse($storeCoupon->end_datetime)->isPast()){
            $invalidate('Ending date was reached');
        }

        if($storeCoupon->activate_using_hours_of_day && !in_array(Carbon::now()->format('H:00'), $storeCoupon->hours_of_day)){
            $invalidate('Invalid hour of the day (Activated at specific hours of the day)');
        }

        if($storeCoupon->activate_using_days_of_the_week && !in_array(Carbon::now()->format('l'), $storeCoupon->days_of_the_week)){
            $invalidate('Invalid day of the week (Activated on specific days of the week)');
        }

        if($storeCoupon->activate_using_days_of_the_month && !in_array(Carbon::now()->format('d'), $storeCoupon->days_of_the_month)){
            $invalidate('Invalid day of the month (Activated on specific days of the month)');
        }

        if($storeCoupon->activate_using_months_of_the_year && !in_array(Carbon::now()->format('F'), $storeCoupon->months_of_the_year)){
            $invalidate('Invalid month of the year (Activated on specific months of the year)');
        }

        if($storeCoupon->activate_for_new_customer){
            if($this->isExistingCustomer === true){
                $invalidate('Must be a new customer');
            } elseif($this->isExistingCustomer === null){
                $invalidate('Cannot determine if this is a new customer. Customer mobile number or email has not been provided');
            }
        }

        if($storeCoupon->activate_for_existing_customer){
            if($this->isExistingCustomer === false){
                $invalidate('Must be an existing customer');
            } elseif($this->isExistingCustomer === null){
                $invalidate('Cannot determine if this is an existing customer. Customer mobile number or email has not been provided');
            }
        }

        if($storeCoupon->activate_using_usage_limit && $storeCoupon->remaining_quantity == 0){
            $invalidate('The usage limit was reached');
        }
    }

    /**
     * Check if a promotion code can be applied.
     *
     * @param object $storeCoupon
     * @return bool
     */
    private function checkIfCanApplyPromotionCode($storeCoupon): bool
    {
        if(!$storeCoupon->active) {
            return false;
        }

        if(!$storeCoupon->activate_using_code) {
            return false;
        }

        if($storeCoupon->activate_using_minimum_grand_total && $this->subtotalAfterDiscount < $storeCoupon->minimum_grand_total->amount) {
            return false;
        }

        if($storeCoupon->activate_using_minimum_total_products && $this->totalSpecifiedUnCancelledProductLines < $storeCoupon->minimum_total_products) {
            return false;
        }

        if($storeCoupon->activate_using_minimum_total_product_quantities && $this->totalSpecifiedUncancelledProductLineQuantities < $storeCoupon->minimum_total_product_quantities) {
            return false;
        }

        if($storeCoupon->activate_using_start_datetime && Carbon::parse($storeCoupon->start_datetime)->isFuture()) {
            return false;
        }

        if($storeCoupon->activate_using_end_datetime && Carbon::parse($storeCoupon->end_datetime)->isPast()) {
            return false;
        }

        if($storeCoupon->activate_using_hours_of_day && !in_array(Carbon::now()->format('H:00'), $storeCoupon->hours_of_day)) {
            return false;
        }

        if($storeCoupon->activate_using_days_of_the_week && !in_array(Carbon::now()->format('l'), $storeCoupon->days_of_the_week)) {
            return false;
        }

        if($storeCoupon->activate_using_days_of_the_month && !in_array(Carbon::now()->format('d'), $storeCoupon->days_of_the_month)) {
            return false;
        }

        if($storeCoupon->activate_using_months_of_the_year && !in_array(Carbon::now()->format('F'), $storeCoupon->months_of_the_year)) {
            return false;
        }

        if($storeCoupon->activate_for_new_customer) {
            if($this->isExistingCustomer === true || $this->isExistingCustomer === null) {
                return false;
            }
        }

        if($storeCoupon->activate_for_existing_customer) {
            if($this->isExistingCustomer === false || $this->isExistingCustomer === null) {
                return false;
            }
        }

        if($storeCoupon->activate_using_usage_limit && $storeCoupon->remaining_quantity == 0) {
            return false;
        }

        return true;
    }

    /**
     * Prepare coupon line.
     *
     * @param object $storeCoupon
     * @param bool $inValid
     * @param array $cancellationReasons
     * @param object|null $existingCouponLine
     * @return CouponLine
     */
    private function prepareCouponLine($storeCoupon, $inValid, $cancellationReasons, $existingCouponLine): CouponLine
    {
        $couponLine = new CouponLine(
            collect($storeCoupon->getAttributes())->merge([
                'detected_changes' => [],
                'is_cancelled' => $inValid,
                'store_id' => $this->store->id,
                'coupon_id' => $storeCoupon->id,
                'cancellation_reasons' => $cancellationReasons
            ])->toArray()
        );

        if($existingCouponLine){
            $wasCancelledAndIsStillInvalid = $existingCouponLine->is_cancelled && $inValid;
            $wasNotCancelledButIsNowInvalid = !$existingCouponLine->is_cancelled && $inValid;

            if($wasNotCancelledButIsNowInvalid || $wasCancelledAndIsStillInvalid){
                $message = 'The (' . $storeCoupon->name . ') coupon was cancelled because it is no longer valid';
                $this->recordCouponLineDetectedChangeAndCancel('cancelled', $message, $couponLine, $existingCouponLine);
            } else {
                $message = 'The (' . $storeCoupon->name . ') coupon was added because it is valid again';
                $this->recordCouponLineDetectedChange('uncancelled', $message, $couponLine, $existingCouponLine);
            }
        }

        return $couponLine;
    }

    /**
     * Set can apply promotion code.
     *
     * @return void
     */
    public function setCanApplyPromotionCode(): void
    {
        $this->canApplyPromotionCode = collect($this->storeCoupons)->contains(function ($storeCoupon) {
            return $this->checkIfCanApplyPromotionCode($storeCoupon);
        });
    }

    /**
     * Set coupon discount applied by code.
     *
     * @return void
     */
    public function setCouponDiscountAppliedByCode(): void
    {
        if($this->canApplyPromotionCode) {

            $discountingCouponLine = collect($this->getSpecifiedUnCancelledCouponLines())->first(function ($couponLine) {
                $coupon = collect($this->storeCoupons)->firstWhere('id', $couponLine->coupon_id);
                return $coupon->offer_discount && $coupon->activate_using_code && $coupon->code == $this->promotionCode;
            });

            if($discountingCouponLine) {
                $this->promotionApplied = true;
                $this->promotionName = $discountingCouponLine->name;

                if($discountingCouponLine->discount_type == DiscountType::FIXED->value) {
                    $totalDiscount = $this->convertToMoneyFormat($discountingCouponLine->discount_fixed_rate->amount, $this->currency);
                    $this->promotionMessage = 'A discount of'.$totalDiscount->amountWithCurrency.' has been applied';
                }else if($discountingCouponLine->discount_type == DiscountType::PERCENTAGE->value) {
                    $totalDiscount = $this->convertToMoneyFormat($this->subtotalAfterDiscount * ($discountingCouponLine->discount_percentage_rate / 100), $this->currency);
                    $this->promotionMessage = 'A 10% discount ('.$totalDiscount->amountWithCurrency.') has been applied';
                }

                $otherTotalDiscount = collect($this->getSpecifiedUnCancelledCouponLines())->filter(function ($couponLine) {
                    $coupon = collect($this->storeCoupons)->firstWhere('id', $couponLine->coupon_id);
                    return $coupon->offer_discount && !$coupon->activate_using_code;
                })->sum(function ($couponLine) {
                    if($couponLine->discount_type == DiscountType::FIXED->value) {
                        $totalDiscount = $couponLine->discount_fixed_rate->amount;
                    }else if($couponLine->discount_type == DiscountType::PERCENTAGE->value) {
                        $totalDiscount = $this->subtotalAfterDiscount * ($couponLine->discount_percentage_rate / 100);

                    }
                    return $totalDiscount;
                });

                if($otherTotalDiscount > 0) {
                    $otherTotalDiscount = $this->convertToMoneyFormat($otherTotalDiscount, $this->currency);
                    $this->promotionMessage .= ', along with additional discounts of '.$otherTotalDiscount->amountWithCurrency.' from other offers.';
                }

            }

        }
    }

    public function getSpecifiedCancelledProductLines()
    {
        return collect($this->specifiedProductLines)->filter(fn($productLine) => $productLine->is_cancelled)->all();
    }

    public function getSpecifiedUnCancelledProductLines()
    {
        return collect($this->specifiedProductLines)->filter(fn($productLine) => !$productLine->is_cancelled)->all();
    }

    public function countSpecifiedProductLines()
    {
        return collect($this->specifiedProductLines)->count();
    }

    public function countSpecifiedCancelledProductLines()
    {
        return collect($this->getSpecifiedCancelledProductLines())->count();
    }

    public function countSpecifiedUnCancelledProductLines()
    {
        return collect($this->getSpecifiedUnCancelledProductLines())->count();
    }

    public function countSpecifiedProductLineQuantities()
    {
        return collect($this->specifiedProductLines)->sum('quantity');
    }

    public function countSpecifiedCancelledProductLineQuantities()
    {
        return collect($this->getSpecifiedCancelledProductLines())->sum('quantity');
    }

    public function countSpecifiedUncancelledProductLineQuantities()
    {
        return collect($this->getSpecifiedUnCancelledProductLines())->sum('quantity');
    }

    public function getSpecifiedCancelledCouponLines()
    {
        return collect($this->specifiedCouponLines)->filter(fn($couponLine) => $couponLine->is_cancelled)->all();
    }

    public function getSpecifiedUnCancelledCouponLines()
    {
        return collect($this->specifiedCouponLines)->filter(fn($couponLine) => !$couponLine->is_cancelled)->all();
    }

    public function countSpecifiedCouponLines()
    {
        return collect($this->specifiedCouponLines)->count();
    }

    public function countSpecifiedCancelledCouponLines()
    {
        return collect($this->getSpecifiedCancelledCouponLines())->count();
    }

    public function countSpecifiedUnCancelledCouponLines()
    {
        return collect($this->getSpecifiedUnCancelledCouponLines())->count();
    }

    public function handleDeliveryMethod()
    {
        if(!$this->deliveryMethod) return;
        $this->validateDeliveryMethod();
        $this->setIfDeliveryMethodAddressIsRequired();
        $this->setIfDeliveryMethodScheduleIsRequired();
        $this->validateDeliveryMethodSchedule();
        $this->setDeliveryMethodTips();
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

        $this->addressIsRequired = $this->deliveryMethod->ask_for_an_address || $this->pinLocationOnMap;
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
            }else if(!$this->deliveryMethod->isValidDate($this->deliveryDate)){
                $disqualify('The selected delivery date is unavailable');
            }else if($this->deliveryMethod->schedule_type == DeliveryMethodScheduleType::DATE_AND_TIME->value && !$this->deliveryTimeslot){
                $disqualify('The delivery time is required');
            }else if($this->deliveryMethod->schedule_type == DeliveryMethodScheduleType::DATE_AND_TIME->value && !$this->deliveryMethod->isValidTimeSlot($this->deliveryDate, $this->deliveryTimeslot)){
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

        if($this->deliveryMethod->offer_free_delivery_on_minimum_grand_total && $this->subtotalAfterDiscount < $this->deliveryMethod->free_delivery_minimum_grand_total->amount){
            $tip('Minimum order amount is '.$this->deliveryMethod->free_delivery_minimum_grand_total->amountWithCurrency.' for free delivery');
        }
    }

    /**
     * Calculate product line totals.
     *
     * @return void
     */
    private function calculateProductLineTotals(): void
    {
        collect($this->getSpecifiedUnCancelledProductLines())->each(function($productLine){
            $this->subtotal += $productLine->subtotal->amount;
            $this->subtotalAfterDiscount += $productLine->subtotal->amount;
            $this->addDiscount('sale discount', $productLine->sale_discount_total->amount);
        });
    }

    /**
     * Apply coupon line discounts.
     *
     * @return void
     */
    private function applyCouponLineDiscounts(): void
    {
        $discounts = [];

        collect($this->getSpecifiedUnCancelledCouponLines())->each(function($couponLine) use (&$discounts) {
            if(!$couponLine->offer_discount) return;

            if($couponLine->discount_type == DiscountType::FIXED->value) {
                $totalDiscount = $couponLine->discount_fixed_rate->amount;
            }else if($couponLine->discount_type == DiscountType::PERCENTAGE->value) {
                $totalDiscount = $this->subtotalAfterDiscount * ($couponLine->discount_percentage_rate / 100);
            }

            $discounts[] = [
                'name' => $couponLine->name,
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
            'amount' => $this->convertToMoneyFormat(($this->discounts[$name]['amount']->amount ?? 0) + $amount, $this->currency)
        ];

        // Update totals
        $this->discountTotal += $amount;
        $this->subtotalAfterDiscount -= $amount;
    }

    /**
     * Add fee.
     *
     * @return void
     */
    private function addFee($name, $amount, $keyName = null): void
    {
        // Update or add the fee
        $this->additionalFees[$keyName ?? $name] = [
            'name' => ucfirst($name),
            'amount' => $this->convertToMoneyFormat(($this->additionalFees[$name]['amount']->amount ?? 0) + $amount, $this->currency)
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
            $this->vat = round($this->subtotalAfterDiscount * ($this->store->tax_percentage_rate / 100), 2);
        }else{
            $this->vat = round($this->subtotalAfterDiscount * ($this->vatRate / (100 + $this->vatRate)), 2);
        }
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
     * Handle custom flat fee.
     *
     * @param array $checkoutFee
     * @return void
     */
    private function handleCustomFlatFee(array $checkoutFee): void
    {
        $name = $checkoutFee['name'];
        $type = $checkoutFee['type'];
        $flatRate = $checkoutFee['flat_rate'];

        if($type == CheckoutFeeType::FLAT->value) {
            $feeAmount = (float) $flatRate;
            $this->addFee($name, $feeAmount);
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
        $type = $checkoutFee['type'];
        $percentageRate = $checkoutFee['percentage_rate'];

        if($type == CheckoutFeeType::PERCENTAGE->value) {
            $feeAmount = $this->subtotalAfterDiscount * ($percentageRate / 100);
            $this->addFee($name, $feeAmount);
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
        $this->addFee('Delivery fee', $this->deliveryMethod->flat_fee_rate->amount);
    }

    /**
     * Handle delivery percentage fee.
     *
     * @return void
     */
    private function handleDeliveryPercentageFee(): void
    {
        if($this->deliveryMethod->fee_type != DeliveryMethodFeeType::PERCENTAGE_FEE->value) return;
        $this->addFee('Delivery fee', $this->subtotalAfterDiscount * ($this->deliveryMethod->percentage_fee_rate / 100));
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
        $customerLocation = $this->address;

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
                    $this->addFee('Delivery fee ('.$this->deliveryDistance['text'].')', $zone['fee'], 'Delivery fee');
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
        $customerPostalCode = $this->address->postal_code ?? null;

        if(!$customerPostalCode && $this->address->latitude && $this->address->longitude) {
            $customerPostalCode = $this->getPostalCodeFromCoordinates(
                $this->address->latitude,
                $this->address->longitude
            );
        }

        if($customerPostalCode) {
            foreach ($this->deliveryMethod->postal_code_zones as $zone) {
                foreach ($zone['postal_codes'] as $postalCode) {
                    if($this->isPostalCodeMatch($customerPostalCode, $postalCode)) {
                        $this->addFee('Delivery fee (Zone ' . $postalCode . ')', $zone['fee'], 'Delivery fee');
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
                    $this->addFee('Delivery fee ('.$weight . $weightUnit.')', $category['fee'], 'Delivery fee');
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
        return collect($this->getSpecifiedUnCancelledProductLines())->map(function($productLine) {
            return $productLine->quantity * ($productLine->unit_weight ?? 0);
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
        $fallbackFeeType = $this->deliveryMethod->fallback_fee_type;

        switch ($fallbackFeeType) {
            case DeliveryMethodFeeType::FLAT_FEE->value:
                $fallbackFee = $this->deliveryMethod->fallback_flat_fee_rate->amount;
                $this->addFee($name, $fallbackFee, 'Delivery fee');
                break;

            case DeliveryMethodFeeType::PERCENTAGE_FEE->value:
                $fallbackFee = $this->subtotalAfterDiscount * ($this->deliveryMethod->fallback_percentage_fee_rate / 100);
                $this->addFee($name, $fallbackFee, 'Delivery fee');
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
            $tipFee = (float) $this->tipFlatRate;
            $this->addFee('Tip', $tipFee);
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
            $tipFee = $this->subtotalAfterDiscount * ($this->tipPercentageRate / 100);
            if($tipFee) $this->addFee('Tip', $tipFee);
        }
    }

    /**
     * Calculate grand total.
     *
     * @return void
     */
    private function calculateGrandTotal(): void
    {
        if($this->store->tax_method == TaxMethod::EXCLUSIVE->value) {
            $this->grandTotal = round($this->subtotalAfterDiscount + $this->vat + $this->feeTotal, 2);
        }else{
            $this->grandTotal = round($this->subtotalAfterDiscount + $this->feeTotal, 2);
        }
    }

    /**
     * Determine if can checkout.
     *
     * @return bool
     */
    private function canCheckout(): bool
    {
        return $this->totalSpecifiedUnCancelledProductLines > 0 && $this->deliveryMethodAvailable;
    }

    /**
     * Get transformed product lines.
     *
     * @return array
     */
    private function getTransformedProductLines(): array
    {
        return collect($this->specifiedProductLines)->map(function($productLine) {
            return [
                'name' => $productLine->name,
                'is_free' => $productLine->is_free,
                'on_sale' => $productLine->on_sale,
                'subtotal' => $productLine->subtotal,
                'quantity' => $productLine->quantity,
                'product_id' => $productLine->product_id,
                'unit_price' => $productLine->unit_price,
                'grand_total' => $productLine->grand_total,
                'description' => $productLine->description,
                'is_cancelled' => $productLine->is_cancelled,
                'unit_sale_price' => $productLine->unit_sale_price,
                'detected_changes' => $productLine->detected_changes,
                'original_quantity' => $productLine->original_quantity,
                'has_limited_stock' => $productLine->has_limited_stock,
                'unit_sale_discount' => $productLine->unit_sale_discount,
                'unit_regular_price' => $productLine->unit_regular_price,
                'sale_discount_total' => $productLine->sale_discount_total,
                'cancellation_reasons' => $productLine->cancellation_reasons,
                'unit_sale_discount_percentage' => $productLine->unit_sale_discount_percentage,
                'has_exceeded_maximum_allowed_quantity_per_order' => $productLine->has_exceeded_maximum_allowed_quantity_per_order,
            ];
        })->all();

    }

    /**
     * Get transformed coupon lines.
     *
     * @return array
     */
    private function getTransformedCouponLines(): array
    {
        return collect($this->specifiedCouponLines)->map(function($couponLine) {
            return [
                'name' => $couponLine->name,
                'coupon_id' => $couponLine->coupon_id,
                'description' => $couponLine->description,
                'is_cancelled' => $couponLine->is_cancelled,
                'detected_changes' => $couponLine->detected_changes,
                'cancellation_reasons' => $couponLine->cancellation_reasons,
            ];
        })->all();
    }
}
