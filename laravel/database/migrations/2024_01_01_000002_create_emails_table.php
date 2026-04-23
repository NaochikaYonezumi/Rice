<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->nullable()->constrained('email_threads')->nullOnDelete();
            $table->string('message_id')->unique()->nullable();
            $table->string('in_reply_to')->nullable()->index();
            $table->string('subject')->nullable();
            $table->string('from_address');
            $table->string('from_name')->nullable();
            $table->string('to_address');
            $table->string('cc')->nullable();
            $table->longText('body_text')->nullable();
            $table->longText('body_html')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->index(['thread_id', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emails');
    }
};
