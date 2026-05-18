<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * customers.domain と customers.notes は UI / EmailFetcher / 検索のいずれからも参照されておらず、
 * 単に fillable と validation 規則に残っているだけのデッドカラムだったため除去する。
 * 将来再導入するなら別 migration を起こすこと。
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['domain', 'notes']);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('domain')->nullable()->after('email');
            $table->text('notes')->nullable()->after('domain');
        });
    }
};
