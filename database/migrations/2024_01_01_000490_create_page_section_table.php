<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePageSectionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('page_section', function (Blueprint $table) {

            $table->uuid('id')->primary();
            $table->boolean('visible')->default(false);
            $table->unsignedTinyInteger('position')->nullable();
            $table->foreignUuid('page_id');
            $table->foreignUuid('section_id');
            $table->timestamps();

            /*  Foreign Key Constraints */
            $table->foreign('page_id')->references('id')->on('pages')->cascadeOnDelete();
            $table->foreign('section_id')->references('id')->on('sections')->cascadeOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('page_section');
    }
}
