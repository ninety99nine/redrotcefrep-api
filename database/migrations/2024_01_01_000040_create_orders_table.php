<?php

use App\Models\Order;
use App\Models\Store;
use App\Enums\TaxMethod;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {

            $table->uuid('id')->primary();

            /* General Summary */
            $table->string('summary')->nullable();
            $table->enum('status', Order::STATUSES())->default(Arr::first(Order::STATUSES()));
            $table->char('currency', 3)->default(config('app.DEFAULT_CURRENCY'));
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('discount_total', 10, 2)->default(0);
            $table->decimal('subtotal_after_discount', 10, 2)->default(0);
            $table->enum('vat_method', Store::TAX_METHOD_OPTIONS())->default(TaxMethod::INCLUSIVE);
            $table->decimal('vat_rate', 5, 2)->default(0);
            $table->decimal('vat_amount', 10, 2)->default(0);
            $table->decimal('fee_total', 10, 2)->default(0);
            $table->decimal('grand_total', 10, 2)->default(0);

            /* Payment Information */
            $table->enum('payment_status', Order::PAYMENT_STATUSES())->default(Arr::last(Order::PAYMENT_STATUSES()));
            $table->decimal('paid_total', 10, 2)->default(0);
            $table->unsignedTinyInteger('paid_percentage')->default(0);
            $table->decimal('pending_total', 10, 2)->default(0);
            $table->unsignedTinyInteger('pending_percentage')->default(0);
            $table->decimal('outstanding_total', 10, 2)->default(0);
            $table->unsignedTinyInteger('outstanding_percentage')->default(100);

            /* Product Information */
            $table->unsignedSmallInteger('total_products')->default(0);
            $table->unsignedSmallInteger('total_cancelled_products')->default(0);
            $table->unsignedSmallInteger('total_uncancelled_products')->default(0);
            $table->unsignedSmallInteger('total_product_quantities')->default(0);
            $table->unsignedSmallInteger('total_cancelled_product_quantities')->default(0);
            $table->unsignedSmallInteger('total_uncancelled_product_quantities')->default(0);

            /* Promotion Information */
            $table->unsignedSmallInteger('total_promotions')->default(0);
            $table->unsignedSmallInteger('total_cancelled_promotions')->default(0);
            $table->unsignedSmallInteger('total_uncancelled_promotions')->default(0);
            $table->boolean('applied_promotion_code')->default(false);

            /* Delivery Information */
            $table->string('delivery_method_name')->nullable();
            $table->float('delivery_distance_value')->nullable();
            $table->string('delivery_distance_unit')->nullable();
            $table->string('delivery_distance_text')->nullable();
            $table->float('delivery_duration_value')->nullable();
            $table->string('delivery_duration_text')->nullable();

            $table->float('delivery_weight_value')->nullable();
            $table->string('delivery_weight_unit')->nullable();
            $table->string('delivery_weight_text')->nullable();
            $table->boolean('free_delivery')->default(false);

            $table->date('delivery_date')->nullable();
            $table->string('delivery_timeslot')->nullable();
            $table->foreignUuid('delivery_method_id')->nullable();

            /* Collection Verification */
            $table->char('collection_code', 6)->nullable();
            $table->string('collection_qr_code')->nullable();
            $table->timestamp('collection_code_expires_at')->nullable();
            $table->boolean('collection_verified')->default(false);
            $table->timestamp('collection_verified_at')->nullable();
            $table->foreignUuid('collection_verified_by_user_id')->nullable();
            $table->text('collection_note')->nullable();

            /* Cancellation Information */
            $table->enum('cancellation_reason', Order::CANCELLATION_REASONS())->nullable();
            $table->string('other_cancellation_reason', Order::OTHER_CANCELLATION_REASON_MAX_CHARACTERS)->nullable();
            $table->timestamp('cancelled_at')->nullable();

            /* Customer Information */
            $table->string('customer_first_name');
            $table->string('customer_last_name')->nullable();
            $table->string('customer_mobile_number', 20)->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_note', Order::CUSTOMER_NOTE_MAX_CHARACTERS)->nullable();
            $table->foreignUuid('customer_id')->nullable();
            $table->foreignUuid('placed_by_user_id')->nullable();

            /* Team Views */
            $table->unsignedSmallInteger('total_views_by_team')->default(1);
            $table->timestamp('first_viewed_by_team_at')->nullable();
            $table->timestamp('last_viewed_by_team_at')->nullable();

            /* Notes */
            $table->text('store_note')->nullable();

            /* Other Relationships */
            $table->foreignUuid('store_id');
            $table->foreignUuid('occasion_id')->nullable();
            $table->foreignUuid('friend_group_id')->nullable();
            $table->foreignUuid('created_by_user_id')->nullable();
            $table->foreignUuid('assigned_to_user_id')->nullable();

            /* Timestamps */
            $table->timestamps();

            /* Add Indexes */
            $table->index(['status']);
            $table->index(['created_at']);
            $table->index(['payment_status']);
            $table->index(['customer_first_name', 'customer_last_name']);

            /* Foreign Key Constraints */
            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
            $table->foreign('occasion_id')->references('id')->on('occasions')->nullOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->foreign('placed_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('assigned_to_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('friend_group_id')->references('id')->on('friend_groups')->nullOnDelete();
            $table->foreign('delivery_method_id')->references('id')->on('delivery_methods')->nullOnDelete();
            $table->foreign('collection_verified_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('orders');
    }
}
