<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ext_workflow_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('condition_type'); // subject, from_address
            $table->string('condition_operator'); // contains, equals, regex
            $table->string('condition_value');
            $table->json('actions'); // tags_to_add, assign_user_id
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ラウンドロビン管理用
        Schema::create('ext_workflow_round_robin', function (Blueprint $table) {
            $table->id();
            $table->string('group_key')->default('default');
            $table->foreignId('last_assigned_user_id')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_workflow_round_robin');
        Schema::dropIfExists('ext_workflow_rules');
    }
};
