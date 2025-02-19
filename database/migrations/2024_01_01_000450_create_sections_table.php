<?php

use App\Models\Section;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSectionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', Section::NAME_MAX_CHARACTERS)->nullable();
            $table->boolean('visible')->default(false);
            $table->char('background_color', 9)->nullable();

            // Divider fields
            $table->enum('top_divider_type', Section::DIVIDERS())->nullable();
            $table->string('top_divider_color', 9)->nullable();
            $table->integer('top_divider_height')->nullable();

            $table->enum('bottom_divider_type', Section::DIVIDERS())->nullable();
            $table->string('bottom_divider_color', 9)->nullable();
            $table->integer('bottom_divider_height')->nullable();

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
        Schema::dropIfExists('sections');
    }
}
