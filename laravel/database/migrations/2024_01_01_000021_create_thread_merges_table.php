<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('thread_merges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('target_thread_id')->constrained('email_threads')->cascadeOnDelete();
            $table->unsignedBigInteger('source_thread_id_original');
            $table->string('source_subject');
            $table->json('source_tags')->nullable();
            $table->json('merged_email_ids');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('thread_merges');
    }
};
