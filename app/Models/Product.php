<?php

namespace App\Models;

use Carbon\Carbon;
use App\Casts\Money;
use App\Casts\Status;
use App\Casts\Currency;
use App\Casts\Percentage;
use App\Casts\JsonToArray;
use App\Casts\StockQuantity;
use App\Traits\ProductTrait;
use App\Enums\SortProductBy;
use App\Models\Base\BaseModel;
use App\Enums\RequestFileName;
use App\Casts\StockQuantityType;
use App\Casts\AllowedQuantityPerOrder;
use Illuminate\Database\Eloquent\Model;
use App\Casts\MaximumAllowedQuantityPerOrder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Enums\StockQuantityType as _StockQuantityType;
use App\Enums\AllowedQuantityPerOrder as _AllowedQuantityPerOrder;

class Product extends BaseModel
{
    use HasFactory, ProductTrait;

    public $relationships = ['store', 'photos', 'variations'];
    public $countableRelationships = ['photos', 'variations'];

    public static function SORT_BY_OPTIONS(): array
    {
        return array_map(fn($status) => $status->value, SortProductBy::cases());
    }

    public static function STOCK_QUANTITY_TYPES(): array
    {
        return array_map(fn($status) => $status->value, _StockQuantityType::cases());
    }

    public static function ALLOWED_QUANTITY_PER_ORDER_OPTIONS(): array
    {
        return array_map(fn($status) => $status->value, _AllowedQuantityPerOrder::cases());
    }

    /**
     *  Magic Numbers
     */
    const NAME_MIN_CHARACTERS = 3;
    const NAME_MAX_CHARACTERS = 60;

    const DESCRIPTION_MIN_CHARACTERS = 3;
    const DESCRIPTION_MAX_CHARACTERS = 200;

    const SKU_MIN_CHARACTERS = 3;
    const SKU_MAX_CHARACTERS = 100;

    const BARCODE_MIN_CHARACTERS = 3;
    const BARCODE_MAX_CHARACTERS = 100;

    const STOCK_QUANTITY_MIN = 0;
    const STOCK_QUANTITY_MAX = 16777215;                    //  since we use unsignedMediumInteger() table schema

    const MAXIMUM_ALLOWED_QUANTITY_PER_ORDER_MIN = 1;
    const MAXIMUM_ALLOWED_QUANTITY_PER_ORDER_MAX = 65535;   //  since we use unsignedSmallInteger() table schema

    const POSITION_MAX = 255;                               //  since we use unsignedTinyInteger() table schema

    const MAXIMUM_VARIATIONS_PER_PRODUCT = 100;

    protected $casts = [
        'is_free' => 'boolean',
        'on_sale' => 'boolean',
        'visible' => 'boolean',
        'has_price' => 'boolean',
        'has_stock' => 'boolean',
        'unit_loss' => Money::class,
        'unit_weight' => 'decimal:2',
        'unit_price' => Money::class,
        'unit_profit' => Money::class,
        'allow_variations' => 'boolean',
        'show_description' => 'boolean',
        'unit_sale_price' => Money::class,
        'unit_cost_price' => Money::class,
        'unit_sale_discount' => Money::class,
        'unit_regular_price' => Money::class,
        'visibility_expires_at' => 'datetime',
        'variant_attributes' => JsonToArray::class,
    ];

    protected $tranformableCasts = [
        'is_free' => Status::class,
        'on_sale' => Status::class,
        'visible' => Status::class,
        'has_price' => Status::class,
        'has_stock' => Status::class,
        'currency' => Currency::class,
        'allow_variations' => Status::class,
        'show_description' => Status::class,
        'stock_quantity' => StockQuantity::class,
        'unit_loss_percentage' => Percentage::class,
        'unit_profit_percentage' => Percentage::class,
        'stock_quantity_type' => StockQuantityType::class,
        'unit_sale_discount_percentage' => Percentage::class,
        'allowed_quantity_per_order' => AllowedQuantityPerOrder::class,
        'maximum_allowed_quantity_per_order' => MaximumAllowedQuantityPerOrder::class,
    ];

    protected $fillable = [

        /*  General Information  */
        'name', 'visible', 'visibility_expires_at', 'show_description', 'description',

        /*  Tracking Information  */
        'sku', 'barcode',

        /*  Variation Information  */
        'allow_variations', 'variant_attributes', 'total_variations', 'total_visible_variations',

        /*  Weight Information  */
        'unit_weight',

        /*  Pricing Information  */
        'is_free', 'currency', 'unit_regular_price', 'unit_sale_price', 'unit_cost_price',
        'on_sale',  'unit_sale_discount', 'unit_sale_discount_percentage', 'has_price',
        'unit_price', 'unit_profit', 'unit_profit_percentage', 'unit_loss',
        'unit_loss_percentage',

        /*  Quantity Information  */
        'allowed_quantity_per_order', 'maximum_allowed_quantity_per_order',

        /*  Stock Information  */
        'has_stock', 'stock_quantity_type', 'stock_quantity',

        /*  Position Information  */
        'position',

        /*  Ownership Information  */
        'parent_product_id', 'user_id', 'store_id'

    ];

    /****************************
     *  SCOPES                  *
     ***************************/

    /*
     *  Scope: Return products that are being searched using the product name
     */
    public function scopeSearch($query, $searchWord)
    {
        return $query->where('name', 'like', "%$searchWord%");
    }

    /**
     *  Scope products that are visible to the customer
     */
    public function scopeVisible($query)
    {
        return $query->where('visible', '1');
    }

    /**
     *  Scope products that are hidden from the customer
     */
    public function scopeHidden($query)
    {
        return $query->where('visible', '0');
    }

    /**
     *  Scope products that support variations.
     *  This means that this product has
     *  different versions of itself
     */
    public function scopeSupportsVariations($query)
    {
        return $query->where('allow_variations', '1');
    }

    /**
     *  Scope products that does not support variations.
     *  This means that this product does not have
     *  different versions of itself
     */
    public function scopeDoesNotSupportVariations($query)
    {
        return $query->where('allow_variations', '0');
    }

    /**
     *  Scope products that are variations of other products
     */
    public function scopeIsVariation($query)
    {
        return $query->whereNotNull('parent_product_id');
    }

    /**
     *  Scope products that are not variations of other products
     */
    public function scopeIsNotVariation($query)
    {
        return $query->where('parent_product_id', null);
    }

    public function scopeVisibilityExpired($query)
    {
        return $query->where('visibility_expires_at', '<=', Carbon::now());
    }

    public function scopeVisibilityNotExpired($query)
    {
        return $query->where('visibility_expires_at', '>', Carbon::now());
    }

    /**
     *  Scope products for a given store
     */
    public function scopeForStore($query, $store)
    {
        return $query->where('store_id', $store instanceof Model ? $store->id : $store);
    }

    /****************************
     *  RELATIONSHIPS           *
     ***************************/

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function photos()
    {
        return $this->morphMany(MediaFile::class, 'mediable')->where('type', RequestFileName::PRODUCT_PHOTO->value);
    }

    public function variations()
    {
        return $this->hasMany(Product::class, 'parent_product_id')->with('variables');
    }

    public function orderProducts()
    {
        return $this->hasMany(OrderProduct::class);
    }

    /**
     *  Returns the product variables. These are the properties that
     *  make this product a variation e.g Size=Small, Color=Blue,
     *  and Material=Cotton are all variables that make this
     *  product variation different from other variations.
     */
    public function variables()
    {
        return $this->hasMany(Variable::class);
    }

    /****************************
     *  ACCESSORS               *
     ***************************/

     protected $appends = [
        'is_variation'
     ];

     /**
      *  Attribute to check if this product is a variation
      */
     protected function isVariation(): Attribute
     {
         return new Attribute(
             get: fn() => !is_null($this->parent_product_id)
         );
     }

}
