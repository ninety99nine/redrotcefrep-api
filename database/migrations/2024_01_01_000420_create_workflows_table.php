<?php

use App\Models\Workflow;
use App\Enums\WorkflowTriggerType;
use App\Enums\WorkflowResourceType;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateWorkflowsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('workflows', function (Blueprint $table) {

            $table->uuid('id')->primary();

            /*  Basic Information  */
            $table->boolean('active')->default(0);
            $table->string('name', Workflow::NAME_MAX_CHARACTERS);
            $table->enum('resource', Workflow::WORKFLOW_RESOURCE_TYPES())->default(WorkflowResourceType::ORDER->value);
            $table->enum('trigger', Workflow::WORKFLOW_TRIGGER_TYPES())->default(WorkflowTriggerType::WAITING->value);

            /*  Arrangement Information  */
            $table->unsignedTinyInteger('position')->nullable();

            /*  Ownership Information  */
            $table->foreignUuid('store_id')->nullable();

            /*  Timestamps  */
            $table->timestamps();

            /* Add Indexes */
            $table->index('name');
            $table->index('store_id');

            /* Foreign Key Constraints */
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
        Schema::dropIfExists('workflows');
    }
}
