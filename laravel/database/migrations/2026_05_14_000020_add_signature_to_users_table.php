<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // 個人ごとの表示名 (任意。空なら name を使う)
            $table->string('display_name', 128)->nullable()->after('name');
            // メール返信や AI 生成の末尾に付ける署名
            $table->text('signature')->nullable()->after('display_name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['display_name', 'signature']);
        });
    }
};
