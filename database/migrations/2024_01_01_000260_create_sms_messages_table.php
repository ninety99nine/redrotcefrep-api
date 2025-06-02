<?php

use App\Models\SmsMessage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSmsMessagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sms_messages', function (Blueprint $table) {

            $table->uuid('id')->primary();

            /*  General Information */
            $table->enum('status', SmsMessage::STATUSES());
            $table->string('content', SmsMessage::CONTENT_MAX_CHARACTERS);
            $table->json('metadata');
            $table->foreignUuid('store_id')->cascadeOnDelete();
            $table->string('sender_name');
            $table->string('sender_mobile_number', 20);
            $table->string('recipient_mobile_number', 20);
            $table->enum('failure_type', SmsMessage::FAILURE_TYPES())->nullable();
            $table->string('failure_reason')->nullable();

            /*  Timestamps  */
            $table->timestamps();

            $table->index('sender_mobile_number');
            $table->index('recipient_mobile_number');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sms_messages');
    }
}
