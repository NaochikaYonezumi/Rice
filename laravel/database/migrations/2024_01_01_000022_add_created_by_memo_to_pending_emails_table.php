<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pending_emails', function (Blueprint $table) {
            $table->string('created_by')->nullable()->after('status');
            $table->text('memo')->nullable()->after('created_by');
        });
    }

    public function down(): void
    {
        Schema::table('pending_emails', function (Blueprint $table) {
            $table->dropColumn(['created_by', 'memo']);
        });
    }
};
