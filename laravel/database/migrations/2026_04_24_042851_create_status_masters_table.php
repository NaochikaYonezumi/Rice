<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('status_masters', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // 完了, テスト, 保留など
            $table->string('key')->unique();  // completed, test, holdなど
            $table->string('color')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_masters');
    }
};
