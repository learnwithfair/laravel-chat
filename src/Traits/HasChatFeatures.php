<?php

namespace RahatulRabbi\TalkBridge\Traits;

use RahatulRabbi\TalkBridge\Models\DeviceToken;

/**
 * HasChatFeatures
 *
 * This trait is automatically injected into your User model by:
 *   php artisan chat:install
 *
 * It is automatically removed by:
 *   php artisan chat:uninstall
 *
 * Do not remove the @laravel-chat markers — they are used to locate
 * and remove this trait during uninstall.
 */
trait HasChatFeatures
{
    // -------------------------------------------------------------------------
    // Blocking
    // -------------------------------------------------------------------------

    public function blockedUsers()
    {
        return $this->belongsToMany(static::class, 'user_blocks', 'user_id', 'blocked_id')->withTimestamps();
    }

    public function blockedByUsers()
    {
        return $this->belongsToMany(static::class, 'user_blocks', 'blocked_id', 'user_id')->withTimestamps();
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
        return $this->belongsToMany(static::class, 'user_restricts', 'user_id', 'restricted_id')->withTimestamps();
    }

    public function restrictedByUsers()
    {
        return $this->belongsToMany(static::class, 'user_restricts', 'restricted_id', 'user_id')->withTimestamps();
    }

    public function hasRestricted($user): bool
    {
        return $this->restrictedUsers()->where('restricted_id', $user->id)->exists();
    }

    // -------------------------------------------------------------------------
    // Device tokens (FCM push notifications)
    // -------------------------------------------------------------------------

    public function deviceTokens()
    {
        return $this->hasMany(DeviceToken::class);
    }

    // -------------------------------------------------------------------------
    // Online presence
    // -------------------------------------------------------------------------

    public function isOnline(): bool
    {
        $field     = config('laravel-chat.user_fields.last_seen', 'last_seen_at');
        $threshold = config('laravel-chat.online_threshold_minutes', 2);

        return $this->{$field} && $this->{$field}->greaterThan(now()->subMinutes($threshold));
    }
}
