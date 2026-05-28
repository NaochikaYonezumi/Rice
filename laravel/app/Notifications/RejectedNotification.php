<?php

namespace App\Notifications;

use App\Models\PendingEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RejectedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public PendingEmail $pending,
        public ?string $rejectionReason = null,
        public ?string $rejecterName = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'pending_id'       => $this->pending->id,
            'subject'          => $this->pending->subject,
            'to_address'       => $this->pending->to_address,
            'reply_type'       => $this->pending->reply_type,
            'rejection_reason' => $this->rejectionReason,
            'rejecter_name'    => $this->rejecterName,
            'kind'             => 'rejected',
        ];
    }
}
