<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAutoBillingSchedulesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('auto_billing_schedules', function (Blueprint $table) {

            $table->uuid('id')->primary();
            $table->boolean('active')->default(1);
            $table->foreignUuid('user_id');
            $table->foreignUuid('pricing_plan_id');
            $table->foreignUuid('payment_method_id');
            $table->foreignUuid('store_id')->nullable();
            $table->datetime('next_attempt_date')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedSmallInteger('total_failed_attempts')->default(0);
            $table->unsignedSmallInteger('total_successful_attempts')->default(0);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
            $table->foreign('pricing_plan_id')->references('id')->on('pricing_plans')->cascadeOnDelete();
            $table->foreign('payment_method_id')->references('id')->on('payment_methods')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('auto_billing_schedules');
    }
}
