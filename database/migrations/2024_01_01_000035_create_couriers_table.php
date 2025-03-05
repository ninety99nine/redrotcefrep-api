<?php

use App\Models\Courier;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCouriersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('couriers', function (Blueprint $table) {

            $table->uuid('id')->primary();
            $table->string('name', Courier::NAME_MAX_CHARACTERS);
            $table->string('tracking_page', Courier::TRACKING_PAGE_MAX_CHARACTERS);
            $table->unsignedTinyInteger('position')->nullable();
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
        Schema::dropIfExists('couriers');
    }
}
