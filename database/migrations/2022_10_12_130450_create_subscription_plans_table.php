<?php

use Illuminate\Support\Arr;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSubscriptionPlansTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('subscription_plans', function (Blueprint $table) {

             $table->id();

            /*  Basic Information  */
            $table->string('name');
            $table->string('description')->nullable();
            $table->enum('service', SubscriptionPlan::SERVICES);
            $table->enum('type', SubscriptionPlan::TYPES);
            $table->char('currency', 3)->default('BWP');
            $table->float('price')->default(0);
            $table->unsignedTinyInteger('position')->nullable();
            $table->boolean('active')->default(false);
            $table->json('metadata');

            /* Add Indexes */
            $table->index('name');
            $table->index('service');
            $table->index('type');
            $table->index('position');
            $table->index('active');

            /*  Timestamps  */
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('subscription_plans');
    }
}
