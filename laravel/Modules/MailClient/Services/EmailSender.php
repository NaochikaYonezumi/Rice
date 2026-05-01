<?php

namespace Modules\MailClient\Services;

use App\Models\Email;
use App\Models\EmailThread;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;

class EmailSender
{
    /**
     * Send an email and update thread ownership.
     */
    public function send($threadId, $data)
    {
        $thread = EmailThread::findOrFail($threadId);
        $user = Auth::user();

        // 署名の付与 (簡易実装)
        $signature = $user->signature ?? ''; // Userモデルにsignatureカラムが必要
        $body = $data['body'] . "\n\n--\n" . $signature;

        Mail::raw($body, function ($message) use ($data, $thread) {
            $message->to($data['to'])
                    ->subject($data['subject']);
            
            // 返信の場合、In-Reply-To をセット
            $latestEmail = $thread->emails()->orderByDesc('received_at')->first();
            if ($latestEmail && $latestEmail->message_id) {
                $message->getHeaders()->addTextHeader('In-Reply-To', $latestEmail->message_id);
                $message->getHeaders()->addTextHeader('References', $latestEmail->message_id);
            }
        });

        // スレッドオーナーシップの更新
        $thread->update([
            'assigned_user_id' => $user->id,
            'status'           => 'replied',
            'last_email_at'    => now(),
        ]);

        // 送信済みメールとして保存 (DB記録)
        return Email::create([
            'thread_id'    => $thread->id,
            'from_address' => config('mail.from.address'),
            'from_name'    => config('mail.from.name'),
            'to_address'   => $data['to'],
            'subject'      => $data['subject'],
            'body_text'    => $body,
            'received_at'  => now(),
        ]);
    }
}
