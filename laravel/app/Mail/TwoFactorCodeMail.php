<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TwoFactorCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $code, public int $lifetimeMinutes)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '【Rice】ログイン認証コード',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.two-factor-code',
            with: [
                'code' => $this->code,
                'user' => $this->user,
                'lifetimeMinutes' => $this->lifetimeMinutes,
            ],
        );
    }
}
