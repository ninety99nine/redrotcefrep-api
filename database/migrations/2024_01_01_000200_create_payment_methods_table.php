<?php

use App\Models\PaymentMethod;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePaymentMethodsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payment_methods', function (Blueprint $table) {

            $table->uuid('id')->primary();

            $table->boolean('active')->default(0);
            $table->string('name', PaymentMethod::NAME_MAX_CHARACTERS);
            $table->string('type', PaymentMethod::TYPE_MAX_CHARACTERS);
            $table->boolean('automated_verification')->default(0);
            $table->json('currencies')->nullable();
            $table->json('countries')->nullable();
            $table->json('allowed_countries')->nullable();
            $table->json('ussd_codes')->nullable();
            $table->json('config_schema')->nullable();
            $table->unsignedTinyInteger('position')->nullable();

            /*  Timestamps  */
            $table->timestamps();

            /* Add Indexes */
            $table->index('name');
            $table->index('type');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payment_methods');
    }
}
