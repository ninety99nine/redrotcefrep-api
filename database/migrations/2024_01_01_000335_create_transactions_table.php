<?php

use App\Models\Store;
use App\Models\Transaction;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {

            $table->uuid('id')->primary();

            /*  Basic Information  */
            $table->enum('payment_status', Transaction::PAYMENT_STATUSES());
            $table->string('failure_type')->nullable();
            $table->string('failure_reason')->nullable();
            $table->string('description')->nullable();

            /*  Amount Information  */
            $table->char('currency', 3)->default(config('app.DEFAULT_CURRENCY'));
            $table->decimal('amount', 12, 3)->default(0);
            $table->unsignedTinyInteger('percentage')->default(100);

            /*  Metadata Information  */
            $table->json('metadata')->nullable();

            /*  Requester Information  */
            $table->foreignUuid('requested_by_user_id')->nullable();

            /*  Verifier Information  */
            $table->enum('verification_type', Transaction::VERIFICATION_TYPES());
            $table->foreignUuid('manually_verified_by_user_id')->nullable();

            /*  Payment Method Information  */
            $table->foreignUuid('payment_method_id')->nullable();
            $table->boolean('created_using_auto_billing')->default(0);

            /*  Customer Information  */
            $table->foreignUuid('customer_id')->nullable();

            /*  Store Information  */
            $table->foreignUuid('store_id')->nullable();

            /*  AI Assistant Information  */
            $table->foreignUuid('ai_assistant_id')->nullable();

            /*  Owenership Information  */
            $table->uuidMorphs('owner');

            /*  Timestamps  */
            $table->timestamps();

            /* Add Indexes */
            $table->index('customer_id');
            $table->index('payment_status');
            $table->index('ai_assistant_id');
            $table->index('requested_by_user_id');
            $table->index('manually_verified_by_user_id');

            /* Foreign Key Constraints */
            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->foreign('requested_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('ai_assistant_id')->references('id')->on('ai_assistants')->nullOnDelete();
            $table->foreign('payment_method_id')->references('id')->on('payment_methods')->nullOnDelete();
            $table->foreign('manually_verified_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transactions');
    }
}
