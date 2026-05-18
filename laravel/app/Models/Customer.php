<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = ['name', 'email', 'group_id', 'sort_order', 'is_personal', 'owner_user_id'];

    protected $casts = [
        'is_personal' => 'boolean',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class, 'group_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * 指定ユーザに見えるルームだけを絞り込む。共有 (is_personal=false) は全員可、
     * 個人 (is_personal=true) は owner のみ。
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->where('is_personal', false);
        }
        return $query->where(function ($q) use ($user) {
            $q->where('is_personal', false)
              ->orWhere('owner_user_id', $user->id);
        });
    }

    /** 代表ルームとして登録されているスレッド (customer_id ベース) */
    public function primaryEmailThreads(): HasMany
    {
        return $this->hasMany(EmailThread::class);
    }

    /** 所属する全スレッド (代表+追加。pivot 経由) */
    public function emailThreads(): BelongsToMany
    {
        return $this->belongsToMany(EmailThread::class, 'customer_email_thread')
            ->withTimestamps();
    }
}
