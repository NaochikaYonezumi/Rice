<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_queries', function (Blueprint $table) {
            $table->string('provider', 16)->nullable()->after('question');
            $table->string('model', 64)->nullable()->after('provider');
        });
    }

    public function down(): void
    {
        Schema::table('chat_queries', function (Blueprint $table) {
            $table->dropColumn(['provider', 'model']);
        });
    }
};
