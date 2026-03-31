<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContactMessage extends Model
{
    use HasFactory;

    public const STATUS_NEW = 'new';
    public const STATUS_OPEN = 'open';
    public const STATUS_REPLIED = 'replied';
    public const STATUS_ARCHIVED = 'archived';

    public const PRIORITY_LOW = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';

    protected $fillable = [
        'user_id',
        'assigned_admin_id',
        'name',
        'email',
        'subject',
        'message',
        'status',
        'priority',
        'source',
        'read_at',
        'archived_at',
        'last_replied_at',
        'reply_count',
        'ip_address',
        'user_agent',
        'admin_notes',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'archived_at' => 'datetime',
            'last_replied_at' => 'datetime',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            static::STATUS_NEW => 'New',
            static::STATUS_OPEN => 'Open',
            static::STATUS_REPLIED => 'Replied',
            static::STATUS_ARCHIVED => 'Archived',
        ];
    }

    public static function priorityOptions(): array
    {
        return [
            static::PRIORITY_LOW => 'Low',
            static::PRIORITY_NORMAL => 'Normal',
            static::PRIORITY_HIGH => 'High',
            static::PRIORITY_URGENT => 'Urgent',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_admin_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(ContactMessageReply::class)->latest('sent_at');
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function scopeArchived($query)
    {
        return $query->whereNotNull('archived_at');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('archived_at');
    }

    public function markAsRead(): void
    {
        if ($this->read_at) {
            return;
        }

        $updates = [
            'read_at' => now(),
        ];

        if ($this->status === static::STATUS_NEW) {
            $updates['status'] = static::STATUS_OPEN;
        }

        $this->forceFill($updates)->save();
    }

    public function archive(): void
    {
        $this->forceFill([
            'status' => static::STATUS_ARCHIVED,
            'archived_at' => now(),
            'read_at' => $this->read_at ?? now(),
        ])->save();
    }

    public function unarchive(): void
    {
        $status = match (true) {
            $this->read_at === null => static::STATUS_NEW,
            $this->last_replied_at !== null => static::STATUS_REPLIED,
            default => static::STATUS_OPEN,
        };

        $this->forceFill([
            'status' => $status,
            'archived_at' => null,
        ])->save();
    }

    public function recordReply(?User $admin, string $subject, string $body): ContactMessageReply
    {
        $reply = $this->replies()->create([
            'user_id' => $admin?->getKey(),
            'subject' => $subject,
            'body' => $body,
            'sent_at' => now(),
        ]);

        $this->forceFill([
            'assigned_admin_id' => $admin?->getKey() ?? $this->assigned_admin_id,
            'status' => static::STATUS_REPLIED,
            'archived_at' => null,
            'last_replied_at' => $reply->sent_at,
            'reply_count' => $this->reply_count + 1,
            'read_at' => $this->read_at ?? now(),
        ])->save();

        return $reply;
    }

    public function getIsUnreadAttribute(): bool
    {
        return $this->read_at === null;
    }

    public function getIsArchivedAttribute(): bool
    {
        return $this->archived_at !== null;
    }
}
