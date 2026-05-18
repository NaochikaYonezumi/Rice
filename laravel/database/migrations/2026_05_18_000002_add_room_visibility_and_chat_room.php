<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('is_personal')->default(false)->after('domain');
            $table->foreignId('owner_user_id')->nullable()->after('is_personal')
                ->constrained('users')->nullOnDelete();
        });

        Schema::table('chat_queries', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('id')
                ->constrained('customers')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->after('customer_id')
                ->constrained('users')->nullOnDelete();
            $table->index(['customer_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('chat_queries', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['customer_id']);
            $table->dropColumn(['user_id', 'customer_id']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['owner_user_id']);
            $table->dropColumn(['owner_user_id', 'is_personal']);
        });
    }
};
