<?php

namespace App\Models;

use App\Support\UploadLimits;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Notifications\Auth\BrandedResetPassword;
use Laravel\Cashier\Billable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\File;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements HasMedia, FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, Billable, InteractsWithMedia;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'last_seen_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public static function onlineThreshold(): \Illuminate\Support\Carbon
    {
        return now()->subMinutes(5);
    }

    /**
     * Filament 3: determine if this user may access the admin panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAdmin();
    }

    /**
     * Get the artist profile associated with the user.
     */
    public function artistProfile()
    {
        return $this->hasOne(ArtistProfile::class);
    }

    /**
     * Get the playlists for the user.
     */
    public function playlists()
    {
        return $this->hasMany(Playlist::class);
    }

    /**
     * Get the play history for the user.
     */
    public function playHistory()
    {
        return $this->hasMany(PlayHistory::class);
    }

    public function likes(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Track::class, 'likes')->withTimestamps();
    }

    public function follows(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Follow::class);
    }

    public function followedArtists(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(ArtistProfile::class, 'follows')->withTimestamps();
    }

    public function comments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function donations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Donation::class);
    }

    public function contactMessages(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ContactMessage::class);
    }

    public function assignedContactMessages(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ContactMessage::class, 'assigned_admin_id');
    }

    public function contactMessageReplies(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ContactMessageReply::class);
    }

    // ─── MediaLibrary ────────────────────────────────────────────────────────
    public function registerMediaCollections(): void
    {
        $disk = config('media-storage.public_disk', config('media-library.disk_name'));

        $this->addMediaCollection('avatar')
            ->useDisk($disk)
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->acceptsFile(fn (File $file) => $file->size <= UploadLimits::imageBytes())
            ->withResponsiveImages();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(200)
            ->height(200)
            ->format('webp')
            ->performOnCollections('avatar');
    }

    public function getAvatarMediaUrl(string $conversion = 'thumb'): string
    {
        $avatar = $this->getFirstMedia('avatar');

        if (! $avatar) {
            return '';
        }

        return $conversion !== ''
            ? $avatar->getAvailableUrl([$conversion])
            : $avatar->getUrl();
    }

    public function getAvatarUrl(string $conversion = 'thumb'): string
    {
        return $this->getAvatarMediaUrl($conversion)
            ?: 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&background=ef4444&color=fff&size=200';
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new BrandedResetPassword($token));
    }

    /**
     * Check if user is an artist
     */
    public function isArtist()
    {
        return $this->hasRole('artist');
    }

    /**
     * Check if user is a listener
     */
    public function isListener()
    {
        return $this->hasRole('listener');
    }

    /**
     * Check if user is an admin
     */
    public function isAdmin()
    {
        return $this->hasRole('admin') || $this->hasRole('super_admin');
    }

    /**
     * Check if user is a super admin
     */
    public function isSuperAdmin()
    {
        return $this->hasRole('super_admin');
    }

    public function scopeOnline($query)
    {
        return $query
            ->whereNotNull('last_seen_at')
            ->where('last_seen_at', '>=', static::onlineThreshold());
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at?->gte(static::onlineThreshold()) ?? false;
    }

    public function getIsOnlineAttribute(): bool
    {
        return $this->isOnline();
    }
}
