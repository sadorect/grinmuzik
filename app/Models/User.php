<?php

namespace App\Models;

use App\Support\UploadLimits;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Notifications\Auth\BrandedResetPassword;
use Laravel\Cashier\Billable;
use Laravel\Sanctum\HasApiTokens;
use NotificationChannels\WebPush\HasPushSubscriptions;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\File;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements HasMedia, FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, Billable, InteractsWithMedia, HasApiTokens, HasPushSubscriptions;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
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

    protected static function booted(): void
    {
        static::saved(function (User $user): void {
            if ($user->wasChanged('email_verified_at')) {
                $user->syncArtistVerificationState();
            }
        });
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

        $this->addMediaConversion('share')
            ->fit(Fit::Contain, 1200, 1200, false, '#ffffff')
            ->format('jpg')
            ->quality(85)
            ->performOnCollections('avatar')
            ->nonQueued();
    }

    public function getAvatarMediaUrl(string $conversion = 'thumb'): string
    {
        $avatar = $this->getFirstMedia('avatar');

        if (! $avatar) {
            return SiteSetting::defaultImageUrl();
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

    public function getShareImageUrl(): string
    {
        return $this->getAvatarMediaUrl('share');
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

    public function syncArtistVerificationState(): void
    {
        if (! $this->isArtist()) {
            return;
        }

        $profile = $this->relationLoaded('artistProfile')
            ? $this->artistProfile
            : $this->artistProfile()->first();

        if (! $profile) {
            return;
        }

        if ($this->shouldAutoActivateArtistProfile()) {
            $profile->markVerifiedAndApproved();

            return;
        }

        $profile->markPendingEmailVerification();
    }

    public function shouldAutoActivateArtistProfile(): bool
    {
        return $this->hasVerifiedEmail() || ! SiteSetting::registrationRequiresEmailVerification();
    }

    public function manuallyVerifyAndApproveArtist(): void
    {
        if (! $this->hasVerifiedEmail()) {
            $this->forceFill([
                'email_verified_at' => now(),
            ])->save();

            return;
        }

        $profile = $this->relationLoaded('artistProfile')
            ? $this->artistProfile
            : $this->artistProfile()->first();

        $profile?->markVerifiedAndApproved();
    }
}
