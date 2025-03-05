<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order_products', function (Blueprint $table) {

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
            $table->string('name', 60)->nullable();
            $table->string('description', 500)->nullable();

            /*  Tracking Information  */
            $table->string('sku', 100)->nullable();
            $table->string('barcode', 100)->nullable();

            /*  Weight Information  */
            $table->decimal('unit_weight', 10, 2)->default(0);

            /*  Pricing Information  */
            $table->boolean('is_free')->default(false);
            $table->char('currency', 3)->default(config('app.DEFAULT_CURRENCY'));
            $table->decimal('unit_regular_price', 10, 2)->default(0);

            $table->boolean('on_sale')->default(false);
            $table->decimal('unit_sale_price', 10, 2)->default(0);
            $table->decimal('unit_sale_discount', 10, 2)->default(0);
            $table->unsignedSmallInteger('unit_sale_discount_percentage')->default(0);

            $table->decimal('unit_cost_price', 10, 2)->default(0);

            $table->boolean('has_price')->default(false);
            $table->decimal('unit_price', 10, 2)->default(0);

            $table->decimal('unit_profit', 10, 2)->default(0);
            $table->unsignedSmallInteger('unit_profit_percentage')->default(0);

            $table->decimal('unit_loss', 10, 2)->default(0);
            $table->unsignedSmallInteger('unit_loss_percentage')->default(0);

            $table->decimal('sale_discount_total', 10, 2)->default(0);
            $table->decimal('grand_total', 10, 2)->default(0);
            $table->decimal('subtotal', 10, 2)->default(0);

            /*  Quantity Information  */
            $table->unsignedSmallInteger('original_quantity')->default(1);
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->boolean('has_limited_stock')->default(false);
            $table->boolean('has_exceeded_maximum_allowed_quantity_per_order')->default(false);

            /*  Cancellation Information  */
            $table->boolean('is_cancelled')->default(false);
            $table->json('cancellation_reasons')->nullable();

            /*  Detected Changes Information  */
            $table->json('detected_changes')->nullable();

            /*  Ownership Information  */
            $table->foreignUuid('order_id');
            $table->foreignUuid('store_id');
            $table->foreignUuid('product_id')->nullable();

            /*  Timestamps  */
            $table->timestamps();

            /* Add Indexes */
            $table->index('sku');
            $table->index('name');
            $table->index('barcode');
            $table->index('store_id');
            $table->index('product_id');

            /* Foreign Key Constraints */
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('order_products');
    }
}
