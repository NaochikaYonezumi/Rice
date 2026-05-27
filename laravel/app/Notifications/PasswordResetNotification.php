<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class PasswordResetNotification extends Notification
{
    use Queueable;

    public function __construct(public string $token)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        $expireMinutes = (int) config('auth.passwords.' . config('auth.defaults.passwords') . '.expire', 60);

        return (new MailMessage)
            ->subject('【Rice】パスワード再設定のご案内')
            ->greeting(($notifiable->resolvedDisplayName() ?? '') . ' 様')
            ->line('パスワード再設定のリクエストを受け付けました。')
            ->line('以下のボタンをクリックして、新しいパスワードを設定してください。')
            ->action('パスワードを再設定する', $url)
            ->line("このリンクの有効期限は **{$expireMinutes}分** です。")
            ->line('心当たりがない場合は、何も操作せずこのメールを破棄してください。アカウントは安全です。')
            ->salutation('Rice');
    }
}
