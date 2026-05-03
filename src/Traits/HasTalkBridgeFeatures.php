<?php

namespace RahatulRabbi\TalkBridge\Traits;

use RahatulRabbi\TalkBridge\Models\DeviceToken;

/**
 * HasTalkBridgeFeatures
 *
 * Automatically injected into your User model by:
 *   php artisan talkbridge:install
 *
 * Automatically removed by:
 *   php artisan talkbridge:uninstall
 *
 * Markers below are used for precise removal — do not edit them.
 */
trait HasTalkBridgeFeatures
{
    // -------------------------------------------------------------------------
    // Dynamic full name resolution
    // -------------------------------------------------------------------------

    /**
     * Get the user's display name.
     *
     * Supports single column, or composite columns defined in config:
     *   'name' => 'name'
     *   'name' => ['first_name', 'last_name']
     *   'name' => ['f_name', 'm_name', 'l_name']
     */
    public function getChatDisplayName(): string
    {
        $nameConfig = config('talkbridge.user_fields.name', 'name');

        if (is_array($nameConfig)) {
            return collect($nameConfig)
                ->map(fn($col) => $this->{$col} ?? '')
                ->filter()
                ->implode(' ');
        }

        return $this->{$nameConfig} ?? '';
    }

    /**
     * Get the user's avatar URL.
     */
    public function getChatAvatar(): ?string
    {
        $col = config('talkbridge.user_fields.avatar', 'avatar_path');
        return $col ? ($this->{$col} ?? null) : null;
    }

    /**
     * Get the user's last seen timestamp.
     */
    public function getChatLastSeen(): ?string
    {
        $col = config('talkbridge.user_fields.last_seen', 'last_seen_at');
        return $col ? ($this->{$col}?->diffForHumans() ?? null) : null;
    }

    // -------------------------------------------------------------------------
    // Online presence
    // -------------------------------------------------------------------------

    public function isOnline(): bool
    {
        $col       = config('talkbridge.user_fields.last_seen', 'last_seen_at');
        $threshold = config('talkbridge.online_threshold_minutes', 2);

        if (! $col || ! $this->{$col}) {
            return false;
        }

        return $this->{$col}->greaterThan(now()->subMinutes($threshold));
    }

    // -------------------------------------------------------------------------
    // Blocking
    // -------------------------------------------------------------------------

    public function blockedUsers()
    {
        return $this->belongsToMany(static::class, 'user_blocks', 'user_id', 'blocked_id')
            ->withTimestamps();
    }

    public function blockedByUsers()
    {
        return $this->belongsToMany(static::class, 'user_blocks', 'blocked_id', 'user_id')
            ->withTimestamps();
    }

    public function hasBlocked($user): bool
    {
        return $this->blockedUsers()->where('users.id', $user->id)->exists();
    }

    public function isBlockedBy($user): bool
    {
        return $this->blockedByUsers()->where('users.id', $user->id)->exists();
    }

    // -------------------------------------------------------------------------
    // Restricting
    // -------------------------------------------------------------------------

    public function restrictedUsers()
    {
        return $this->belongsToMany(static::class, 'user_restricts', 'user_id', 'restricted_id')
            ->withTimestamps();
    }

    public function restrictedByUsers()
    {
        return $this->belongsToMany(static::class, 'user_restricts', 'restricted_id', 'user_id')
            ->withTimestamps();
    }

    public function hasRestricted($user): bool
    {
        return $this->restrictedUsers()->where('restricted_id', $user->id)->exists();
    }

    // -------------------------------------------------------------------------
    // Device tokens (push notifications)
    // -------------------------------------------------------------------------

    public function deviceTokens()
    {
        return $this->hasMany(DeviceToken::class);
    }
}
