<?php

use App\Models\OrderFee;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOrderFeesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order_fees', function (Blueprint $table) {

            $table->uuid('id')->primary();
            $table->string('name', OrderFee::NAME_MAX_CHARACTERS);
            $table->char('currency', 3)->default(config('app.DEFAULT_CURRENCY'));
            $table->decimal('amount', 10, 2)->default(0);
            $table->foreignUuid('order_id');
            $table->foreignUuid('store_id');

            /* Timestamps */
            $table->timestamps();

            /* Foreign Key Constraints */
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
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
        Schema::dropIfExists('order_fees');
    }
}
