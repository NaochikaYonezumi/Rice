<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('email_threads', function (Blueprint $table) {
            $table->string('status')->default('inbox')->after('customer_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('email_threads', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
