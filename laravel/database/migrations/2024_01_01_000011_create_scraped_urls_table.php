<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scraped_urls', function (Blueprint $table) {
            $table->id();
            $table->string('url', 2048);
            $table->string('collection', 64)->default('default');
            $table->unsignedInteger('chunks_indexed')->default(0);
            $table->string('status', 16)->default('ok');
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scraped_urls');
    }
};
