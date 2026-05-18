<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_email_thread', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('email_thread_id')->constrained('email_threads')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['customer_id', 'email_thread_id']);
            $table->index('email_thread_id');
        });

        // 既存の email_threads.customer_id を pivot に複製
        DB::table('email_threads')
            ->whereNotNull('customer_id')
            ->select(['id', 'customer_id'])
            ->chunkById(500, function ($rows) {
                $now = now();
                $payload = $rows->map(fn ($r) => [
                    'customer_id'     => $r->customer_id,
                    'email_thread_id' => $r->id,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ])->all();

                if ($payload) {
                    DB::table('customer_email_thread')->insertOrIgnore($payload);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_email_thread');
    }
};
