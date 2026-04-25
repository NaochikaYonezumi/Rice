<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('email_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_id')->constrained('emails')->cascadeOnDelete();
            $table->string('filename');
            $table->string('mime_type')->default('application/octet-stream');
            $table->unsignedBigInteger('size')->default(0);
            $table->string('disk_path');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_attachments');
    }
};
