<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. 顧客フォルダ (グループ) テーブル
        Schema::create('customer_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // 2. 顧客テーブルにグループIDと表示順を追加
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('group_id')->nullable()->constrained('customer_groups')->nullOnDelete();
            $table->integer('sort_order')->default(0);
        });

        // 3. タグテーブルに表示順を追加
        Schema::table('tags', function (Blueprint $table) {
            $table->integer('sort_order')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['group_id']);
            $table->dropColumn(['group_id', 'sort_order']);
        });
        Schema::dropIfExists('customer_groups');
    }
};
