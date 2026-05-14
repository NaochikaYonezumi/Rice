<?php

use App\Models\PendingEmail;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * 既存データの一括補正:
     * - status='inbox' なのに承認待ち PendingEmail を持つスレッド → status='pending'
     * - status='pending' なのに承認待ちが既に解消されているスレッド → status='inbox'
     */
    public function up(): void
    {
        // inbox → pending
        $idsToPending = DB::table('email_threads')
            ->where('email_threads.status', 'inbox')
            ->whereExists(function ($q) {
                $q->selectRaw(1)
                  ->from('pending_emails')
                  ->join('emails', 'emails.id', '=', 'pending_emails.in_reply_to_email_id')
                  ->where('pending_emails.status', 'pending')
                  ->whereColumn('emails.thread_id', 'email_threads.id');
            })
            ->pluck('email_threads.id');
        if ($idsToPending->isNotEmpty()) {
            DB::table('email_threads')->whereIn('id', $idsToPending)->update(['status' => 'pending']);
        }

        // pending → inbox (もう承認待ちが残っていないスレッド)
        $idsToInbox = DB::table('email_threads')
            ->where('email_threads.status', 'pending')
            ->whereNotExists(function ($q) {
                $q->selectRaw(1)
                  ->from('pending_emails')
                  ->join('emails', 'emails.id', '=', 'pending_emails.in_reply_to_email_id')
                  ->where('pending_emails.status', 'pending')
                  ->whereColumn('emails.thread_id', 'email_threads.id');
            })
            ->pluck('email_threads.id');
        if ($idsToInbox->isNotEmpty()) {
            DB::table('email_threads')->whereIn('id', $idsToInbox)->update(['status' => 'inbox']);
        }
    }

    public function down(): void
    {
        // 不可逆的データ補正なので戻さない
    }
};
