<?php

namespace App\Notifications;

use App\Models\PendingEmail;
use Illuminate\Notifications\Notification;

class ApprovalRequestedNotification extends Notification
{
    public function __construct(public PendingEmail $pending) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'pending_id'   => $this->pending->id,
            'subject'      => $this->pending->subject,
            'to_address'   => $this->pending->to_address,
            'created_by'   => $this->pending->created_by,
            'reply_type'   => $this->pending->reply_type,
        ];
    }
}
