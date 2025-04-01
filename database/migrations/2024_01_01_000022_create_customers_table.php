<?php

use App\Models\Store;
use App\Models\Customer;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCustomersTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('first_name', Customer::FIRST_NAME_MAX_CHARACTERS);
            $table->string('last_name', Customer::LAST_NAME_MAX_CHARACTERS)->nullable();
            $table->string('email')->nullable();
            $table->string('mobile_number', 20)->nullable();
            $table->date('birthday')->nullable();
            $table->text('notes', Customer::NOTES_MAX_CHARACTERS)->nullable();
            $table->char('currency', 3)->default(config('app.DEFAULT_CURRENCY'));
            $table->foreignUuid('store_id')->cascadeOnDelete();

            $table->timestamp('last_order_at')->nullable();
            $table->unsignedInteger('total_orders')->default(0);
            $table->decimal('total_spend', 12, 3)->default(0);
            $table->decimal('total_average_spend', 12, 3)->default(0);

            /* Add Timestamps */
            $table->timestamps();

            /* Add Indexes */
            $table->index(['first_name', 'last_name']);
            $table->index('mobile_number');
            $table->index('email');

            /*  Foreign Key Constraints */
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
        Schema::dropIfExists('customers');
    }
}
