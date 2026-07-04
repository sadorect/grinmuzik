<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class NewsletterDraft extends Model
{
    protected $fillable = [
        'subject',
        'period',
        'variables',
        'body',
        'status',
        'generated_at',
        'approved_at',
        'sent_at',
    ];

    protected $casts = [
        'variables'    => 'array',
        'generated_at' => 'datetime',
        'approved_at'  => 'datetime',
        'sent_at'      => 'datetime',
    ];

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function scopeSent(Builder $query): Builder
    {
        return $query->where('status', 'sent');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isSent(): bool
    {
        return $this->status === 'sent';
    }
}
