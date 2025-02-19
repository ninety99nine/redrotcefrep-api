<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRowColumnTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('row_column', function (Blueprint $table) {

            $table->uuid('id')->primary();
            $table->boolean('visible')->default(false);
            $table->unsignedTinyInteger('position')->nullable();
            $table->foreignUuid('row_id');
            $table->foreignUuid('column_id');
            $table->timestamps();

            /*  Foreign Key Constraints */
            $table->foreign('row_id')->references('id')->on('rows')->cascadeOnDelete();
            $table->foreign('column_id')->references('id')->on('columns')->cascadeOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('row_column');
    }
}
