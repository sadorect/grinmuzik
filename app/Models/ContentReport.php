<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ContentReport extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_UPHELD = 'upheld';

    public const STATUS_REJECTED = 'rejected';

    public const ACTION_NONE = 'none';

    public const ACTION_UNPUBLISHED = 'unpublished';

    public const ACTION_DELETED = 'deleted';

    /**
     * Report reasons.
     *
     * These are the categories a Play reviewer expects to find, and the ones
     * that map to real obligations: copyright has a legal process behind it,
     * and CSAE/violent-extremism reports need to be actionable immediately.
     * "Other" exists so a reporter never abandons a report for lack of a
     * matching option.
     *
     * @return array<string, string>
     */
    public static function reasons(): array
    {
        return [
            'copyright' => 'Copyright or trademark infringement',
            'sexual_content' => 'Sexual or pornographic content',
            'child_safety' => 'Child sexual abuse or exploitation',
            'hate_speech' => 'Hate speech or harassment',
            'violence' => 'Violence or violent extremism',
            'illegal' => 'Illegal or dangerous activity',
            'impersonation' => 'Impersonation of another artist or person',
            'spam' => 'Spam or misleading content',
            'other' => 'Something else',
        ];
    }

    /**
     * Reasons that must never sit in a queue behind ordinary spam.
     *
     * @return array<int, string>
     */
    public static function urgentReasons(): array
    {
        return ['child_safety', 'violence', 'illegal'];
    }

    protected $fillable = [
        'reportable_type',
        'reportable_id',
        'reporter_id',
        'reporter_email',
        'reporter_ip',
        'reason',
        'details',
        'status',
        'reviewed_by',
        'reviewed_at',
        'moderator_note',
        'action_taken',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function reasonLabel(): string
    {
        return self::reasons()[$this->reason] ?? $this->reason;
    }

    public function isUrgent(): bool
    {
        return in_array($this->reason, self::urgentReasons(), true);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
