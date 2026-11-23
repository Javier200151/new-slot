<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('member_procedure_settings', function (Blueprint $table): void {
            $table->string('telegram_server_chat_id', 80)->nullable();
            $table->boolean('telegram_server_notifications_enabled')->default(false);
            $table->unsignedSmallInteger('telegram_server_notice_minutes')->default(120);
        });

        Schema::create('mission_control_telegram_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->string('chat_id', 80);
            $table->string('message_key', 191);
            $table->unsignedBigInteger('event_id')->nullable();
            $table->string('result', 32)->nullable();
            $table->timestamp('sent_at');
            $table->timestamps();
            $table->unique(['chat_id', 'message_key'], 'mc_telegram_delivery_unique');
            $table->index(['chat_id', 'event_id', 'result'], 'mc_telegram_event_status_index');
        });

        Schema::create('mission_control_telegram_cursors', function (Blueprint $table): void {
            $table->string('chat_id', 80)->primary();
            $table->unsignedBigInteger('last_notice_id')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_control_telegram_cursors');
        Schema::dropIfExists('mission_control_telegram_deliveries');
        Schema::table('member_procedure_settings', function (Blueprint $table): void {
            $table->dropColumn(['telegram_server_chat_id', 'telegram_server_notifications_enabled', 'telegram_server_notice_minutes']);
        });
    }
};
