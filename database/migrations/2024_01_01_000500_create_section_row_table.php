<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSectionRowTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('section_row', function (Blueprint $table) {

            $table->uuid('id')->primary();
            $table->boolean('visible')->default(false);
            $table->unsignedTinyInteger('position')->nullable();
            $table->foreignUuid('section_id');
            $table->foreignUuid('row_id');
            $table->timestamps();

            /*  Foreign Key Constraints */
            $table->foreign('section_id')->references('id')->on('sections')->cascadeOnDelete();
            $table->foreign('row_id')->references('id')->on('rows')->cascadeOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('section_row');
    }
}
