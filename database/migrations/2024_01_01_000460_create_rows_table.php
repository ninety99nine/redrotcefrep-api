<?php

use App\Models\Row;
use App\Enums\RowLayout;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRowsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('rows', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', Row::NAME_MAX_CHARACTERS)->nullable();
            $table->boolean('visible')->default(false);
            $table->char('background_color', 9)->nullable();
            $table->enum('layout', Row::LAYOUTS())->default(RowLayout::ONE_COLUMN);
            $table->foreignUuid('store_id');
            $table->timestamps();

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
        Schema::dropIfExists('rows');
    }
}
