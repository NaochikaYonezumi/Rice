<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_groups', function (Blueprint $blueprint) {
            $blueprint->unsignedBigInteger('parent_id')->nullable()->after('id');
            $blueprint->foreign('parent_id')->references('id')->on('customer_groups')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('customer_groups', function (Blueprint $blueprint) {
            $blueprint->dropForeign(['parent_id']);
            $blueprint->dropColumn('parent_id');
        });
    }
};
