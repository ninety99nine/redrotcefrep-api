<?php

namespace App\Models;

use App\Casts\Money;
use App\Casts\Status;
use App\Casts\Currency;
use App\Casts\Percentage;
use App\Casts\JsonToArray;
use App\Traits\ItemLineTrait;
use App\Models\Base\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductLine extends BaseModel
{
    use HasFactory, ItemLineTrait;

    protected $table = 'product_lines';

    protected $casts = [
        'is_free' => 'boolean',
        'on_sale' => 'boolean',
        'has_price' => 'boolean',
        'is_cancelled' => 'boolean',
        'subtotal' => Money::class,
        'unit_loss' => Money::class,
        'unit_weight' => 'decimal:2',
        'unit_price' => Money::class,
        'grand_total' => Money::class,
        'unit_profit' => Money::class,
        'has_limited_stock' => 'boolean',
        'unit_sale_price' => Money::class,
        'unit_cost_price' => Money::class,
        'unit_sale_discount' => Money::class,
        'unit_regular_price' => Money::class,
        'sale_discount_total' => Money::class,
        'detected_changes' => JsonToArray::class,
        'cancellation_reasons' => JsonToArray::class,
        'has_exceeded_maximum_allowed_quantity_per_order' => 'boolean',
    ];

    protected $tranformableCasts = [
        'has_exceeded_maximum_allowed_quantity_per_order' => Status::class,
        'unit_sale_discount_percentage' => Percentage::class,
        'unit_profit_percentage' => Percentage::class,
        'unit_loss_percentage' => Percentage::class,
        'is_cancelled' => Status::class,
        'currency' => Currency::class,
        'has_price' => Status::class,
        'is_free' => Status::class,
        'on_sale' => Status::class,
    ];

    protected $fillable = [

        /*  General Information  */
        'name', 'description',

        /*  Tracking Information  */
        'sku', 'barcode',

        /*  Weight Information  */
        'unit_weight',

        /*  Pricing Information  */
        'is_free', 'currency', 'unit_regular_price', 'unit_sale_price', 'unit_cost_price',
        'on_sale', 'unit_sale_discount', 'unit_sale_discount_percentage', 'has_price',
        'unit_price', 'unit_profit', 'unit_profit_percentage', 'unit_loss',
        'unit_loss_percentage', 'sale_discount_total', 'subtotal',
        'grand_total',

        /*  Quantity Information  */
        'quantity', 'original_quantity', 'has_limited_stock', 'has_exceeded_maximum_allowed_quantity_per_order',

        /*  Cancellation Information  */
        'is_cancelled', 'cancellation_reasons',

        /*  Detected Changes Information  */
        'detected_changes',

        /*  Ownership Information  */
        'product_id', 'cart_id', 'store_id'

    ];

    /****************************
     *  RELATIONSHIPS           *
     ***************************/

    /**
     *  Returns the associated product
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     *  Returns the associated cart
     */
    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     *  Returns the associated store
     */
    public function store()
    {
        return $this->belongsTo(Store::class);
    }

}
