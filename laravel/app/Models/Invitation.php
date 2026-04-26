<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invitation extends Model
{
    protected $fillable = ['email', 'token', 'role', 'expires_at', 'accepted_at', 'invited_by'];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    /**
     * Check if invitation is still valid.
     */
    public function isValid(): bool
    {
        return is_null($this->accepted_at) && $this->expires_at->isFuture();
    }

    /**
     * User who created the invitation.
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
