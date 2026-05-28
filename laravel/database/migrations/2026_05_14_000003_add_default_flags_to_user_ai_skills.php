<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('user_ai_skills', function (Blueprint $table) {
            $table->boolean('is_default_summary')->default(false)->after('is_active');
            $table->boolean('is_default_reply')->default(false)->after('is_default_summary');
        });
    }

    public function down(): void
    {
        Schema::table('user_ai_skills', function (Blueprint $table) {
            $table->dropColumn(['is_default_summary', 'is_default_reply']);
        });
    }
};
