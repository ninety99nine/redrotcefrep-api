<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateColumnModuleTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('column_module', function (Blueprint $table) {

            $table->uuid('id')->primary();
            $table->boolean('visible')->default(false);
            $table->unsignedTinyInteger('position')->nullable();
            $table->foreignUuid('column_id');
            $table->foreignUuid('module_id');
            $table->timestamps();

            /*  Foreign Key Constraints */
            $table->foreign('column_id')->references('id')->on('columns')->cascadeOnDelete();
            $table->foreign('module_id')->references('id')->on('modules')->cascadeOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('column_module');
    }
}
