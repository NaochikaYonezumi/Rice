<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerGroup extends Model
{
    protected $fillable = ['name', 'sort_order', 'parent_id'];

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'group_id')->orderBy('sort_order');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(CustomerGroup::class, 'parent_id')->orderBy('sort_order');
    }
}
