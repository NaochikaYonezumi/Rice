<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pending_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('in_reply_to_email_id')->nullable()->constrained('emails')->nullOnDelete();
            $table->string('reply_type')->default('compose'); // compose, reply, reply_all
            $table->string('to_address');
            $table->string('cc')->nullable();
            $table->string('subject');
            $table->text('body');
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_emails');
    }
};
