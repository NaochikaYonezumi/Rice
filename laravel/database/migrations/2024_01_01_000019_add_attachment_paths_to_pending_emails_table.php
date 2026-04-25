<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pending_emails', function (Blueprint $table) {
            $table->json('attachment_paths')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('pending_emails', function (Blueprint $table) {
            $table->dropColumn('attachment_paths');
        });
    }
};
