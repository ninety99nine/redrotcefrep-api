<?php

use App\Models\Store;
use App\Models\Product;
use App\Enums\StockQuantityType;
use App\Enums\AllowedQuantityPerOrder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {

            $table->uuid('id')->primary();

            /*  General Information
             *
             *  Note: The product name can be up to 60 characters
             *  since variation names can have long generated
             *  names e.g
             *
             *  Nike Winter Jacket (Red, Large and Cotton) = 42 characters
             *
             *  Lets give an allowance of 60 characters to avoid
             *  possible issues because of long product names
             */
            $table->string('name', Product::NAME_MAX_CHARACTERS)->nullable();
            $table->boolean('visible')->default(true);
            $table->timestamp('visibility_expires_at')->nullable();

            $table->boolean('show_description')->default(false);
            $table->string('description', Product::DESCRIPTION_MAX_CHARACTERS)->nullable();

            /*  Tracking Information  */
            $table->string('sku', Product::SKU_MAX_CHARACTERS)->nullable();
            $table->string('barcode', Product::BARCODE_MAX_CHARACTERS)->nullable();

            /*  Variation Information  */
            $table->boolean('allow_variations')->default(false);
            $table->json('variant_attributes')->nullable();
            $table->unsignedTinyInteger('total_variations')->nullable();
            $table->unsignedTinyInteger('total_visible_variations')->nullable();

            /*  Weight Information  */
            $table->decimal('unit_weight', 12, 3)->default(0);

            /*  Pricing Information  */
            $table->boolean('is_free')->default(false);
            $table->char('currency', 3)->default(config('app.DEFAULT_CURRENCY'));
            $table->decimal('unit_regular_price', 12, 3)->default(0);

            $table->boolean('on_sale')->default(false);
            $table->decimal('unit_sale_price', 12, 3)->default(0);
            $table->decimal('unit_sale_discount', 12, 3)->default(0);
            $table->unsignedSmallInteger('unit_sale_discount_percentage')->default(0);

            $table->decimal('unit_cost_price', 12, 3)->default(0);

            $table->boolean('has_price')->default(false);
            $table->decimal('unit_price', 12, 3)->default(0);

            $table->decimal('unit_profit', 12, 3)->default(0);
            $table->unsignedSmallInteger('unit_profit_percentage')->default(0);

            $table->decimal('unit_loss', 12, 3)->default(0);
            $table->unsignedSmallInteger('unit_loss_percentage')->default(0);

            /*  Quantity Information  */
            $table->enum('allowed_quantity_per_order', Product::ALLOWED_QUANTITY_PER_ORDER_OPTIONS())->default(AllowedQuantityPerOrder::UNLIMITED->value);
            $table->unsignedSmallInteger('maximum_allowed_quantity_per_order')->default(1);

            /*  Stock Information  */
            $table->boolean('has_stock')->default(true);
            $table->enum('stock_quantity_type', Product::STOCK_QUANTITY_TYPES())->default(StockQuantityType::UNLIMITED->value);
            $table->unsignedMediumInteger('stock_quantity')->default(100);

            /*  Arrangement Information  */
            $table->unsignedTinyInteger('position')->nullable();

            /*  Ownership Information  */
            $table-> foreignUuid('parent_product_id')->nullable();
            $table-> foreignUuid('user_id')->nullable();
            $table-> foreignUuid('store_id');

            /*  Timestamps  */
            $table->timestamps();

            /* Add Indexes */
            $table->index('sku');
            $table->index('name');
            $table->index('barcode');
            $table->index('user_id');
            $table->index('position');
            $table->index('store_id');
            $table->index('parent_product_id');

            /* Foreign Key Constraints */
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('products');
    }
}
