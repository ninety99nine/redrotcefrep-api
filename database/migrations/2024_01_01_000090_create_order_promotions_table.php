<?php

use App\Models\OrderPromotion;
use App\Enums\DiscountType;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOrderPromotionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order_promotions', function (Blueprint $table) {

             $table->uuid('id')->primary();

            /*  General Information */
            $table->string('name', 50);
            $table->string('description', 500)->nullable();

            /*  Offer Discount Information */
            $table->boolean('offer_discount')->default(false);
            $table->enum('discount_type', OrderPromotion::DISCOUNT_TYPES())->default(DiscountType::FIXED);
            $table->unsignedTinyInteger('discount_percentage_rate')->default(0);
            $table->decimal('discount_fixed_rate', 10, 2)->default(0);

            /*  Offer Free Delivery Information */
            $table->boolean('offer_free_delivery')->default(false);

            /*  Activation Information  */
            $table->boolean('activate_using_code')->default(false);
            $table->string('code', 10)->nullable();

            $table->boolean('activate_using_minimum_grand_total')->default(false);
            $table->decimal('minimum_grand_total', 10, 2)->default(0);
            $table->char('currency', 3)->default(config('app.DEFAULT_CURRENCY'));

            $table->boolean('activate_using_minimum_total_products')->default(false);
            $table->unsignedSmallInteger('minimum_total_products')->default(1);

            $table->boolean('activate_using_minimum_total_product_quantities')->default(false);
            $table->unsignedSmallInteger('minimum_total_product_quantities')->default(1);

            $table->boolean('activate_using_start_datetime')->default(false);
            $table->timestamp('start_datetime')->nullable();

            $table->boolean('activate_using_end_datetime')->default(false);
            $table->timestamp('end_datetime')->nullable();

            $table->boolean('activate_using_hours_of_day')->default(false);
            $table->json('hours_of_day')->nullable();

            $table->boolean('activate_using_days_of_the_week')->default(false);
            $table->json('days_of_the_week')->nullable();

            $table->boolean('activate_using_days_of_the_month')->default(false);
            $table->json('days_of_the_month')->nullable();

            $table->boolean('activate_using_months_of_the_year')->default(false);
            $table->json('months_of_the_year')->nullable();

            $table->boolean('activate_for_new_customer')->default(false);
            $table->boolean('activate_for_existing_customer')->default(false);

            $table->boolean('activate_using_usage_limit')->default(false);
            $table->unsignedMediumInteger('remaining_quantity')->default(0);

            /*  Cancellation Information  */
            $table->boolean('is_cancelled')->default(false);
            $table->json('cancellation_reasons')->nullable();

            /*  Detected Changes Information  */
            $table->json('detected_changes')->nullable();

            /*  Ownership  */
            $table->foreignUuid('order_id');
            $table->foreignUuid('store_id');
            $table->foreignUuid('promotion_id')->nullable();

            /*  Timestamps  */
            $table->timestamps();

            /* Add Indexes */
            $table->index('name');
            $table->index('code');
            $table->index('store_id');
            $table->index('promotion_id');

            /* Foreign Key Constraints */
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
            $table->foreign('promotion_id')->references('id')->on('promotions')->nullOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('order_promotions');
    }
}
