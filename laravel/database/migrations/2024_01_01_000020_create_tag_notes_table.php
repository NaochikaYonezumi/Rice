<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tag_notes', function (Blueprint $table) {
            $table->id();
            $table->string('tag')->unique();
            $table->text('memo')->nullable();
            $table->longText('wiki')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tag_notes');
    }
};
