<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('email_threads', function (Blueprint $table) {
            $table->string('ticket_number', 32)->nullable()->after('subject')->index();
        });
    }

    public function down(): void
    {
        Schema::table('email_threads', function (Blueprint $table) {
            $table->dropIndex(['ticket_number']);
            $table->dropColumn('ticket_number');
        });
    }
};
