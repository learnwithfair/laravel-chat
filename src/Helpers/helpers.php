<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

// =============================================================================
// File helpers
// =============================================================================

if (! function_exists('talkbridge_upload_file')) {
    /**
     * Upload a file to the configured chat disk.
     */
    function talkbridge_upload_file(UploadedFile $file, string $folder, ?string $customName = null): ?string
    {
        try {
            $disk     = config('talkbridge.uploads.disk', 'public');
            $fileName = $customName
                ? $customName . '.' . $file->getClientOriginalExtension()
                : time() . '_' . $file->getClientOriginalName();

            $path = $file->storeAs($folder, $fileName, $disk);
            return $path ? Storage::disk($disk)->url($path) : null;
        } catch (\Exception $e) {
            Log::error('talkbridge_upload_file: ' . $e->getMessage());
            return null;
        }
    }
}

if (! function_exists('talkbridge_delete_file')) {
    /**
     * Delete a file from the configured chat disk.
     */
    function talkbridge_delete_file(?string $filePath): bool
    {
        if (! $filePath) {
            return false;
        }

        try {
            return Storage::disk(config('talkbridge.uploads.disk', 'public'))->delete($filePath);
        } catch (\Exception $e) {
            Log::error('talkbridge_delete_file: ' . $e->getMessage());
            return false;
        }
    }
}

if (! function_exists('talkbridge_delete_files')) {
    /**
     * Delete multiple files from the configured chat disk.
     */
    function talkbridge_delete_files(array $paths): array
    {
        $deleted = [];
        $failed  = [];

        foreach ($paths as $path) {
            talkbridge_delete_file($path) ? $deleted[] = $path : $failed[] = $path;
        }

        return ['deleted' => $deleted, 'failed' => $failed];
    }
}

if (! function_exists('talkbridge_file_type')) {
    /**
     * Detect file type category from extension.
     */
    function talkbridge_file_type(string $path): string
    {
        $ext   = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $types = config('talkbridge.uploads.allowed_types', []);

        foreach ($types as $type => $extensions) {
            if (in_array($ext, $extensions, true)) {
                return $type;
            }
        }

        return 'file';
    }
}

// =============================================================================
// User helpers — all null-safe
// =============================================================================

if (! function_exists('talkbridge_user_name')) {
    /**
     * Resolve display name from a user model instance.
     *
     * Null-safe: returns empty string if $user is null or not an object.
     * Supports single column or composite columns defined in config:
     *   'name' => 'name'
     *   'name' => ['first_name', 'last_name']
     *   'name' => ['f_name', 'm_name', 'l_name']
     */
    function talkbridge_user_name($user): string
    {
        // Null / non-object guard
        if ($user === null || ! is_object($user)) {
            return '';
        }

        // Use trait method if available
        if (method_exists($user, 'getChatDisplayName')) {
            return (string) $user->getChatDisplayName();
        }

        $nameConfig = config('talkbridge.user_fields.name', 'name');

        if (is_array($nameConfig)) {
            return collect($nameConfig)
                ->map(fn($col) => isset($user->{$col}) ? (string) $user->{$col} : '')
                ->filter()
                ->implode(' ');
        }

        $col = $nameConfig ?: 'name';

        return isset($user->{$col}) ? (string) $user->{$col} : '';
    }
}

if (! function_exists('talkbridge_user_avatar')) {
    /**
     * Resolve avatar URL from a user model instance.
     *
     * Null-safe: returns null if $user is null or not an object.
     */
    function talkbridge_user_avatar($user): ?string
    {
        if ($user === null || ! is_object($user)) {
            return null;
        }

        if (method_exists($user, 'getChatAvatar')) {
            return $user->getChatAvatar();
        }

        $col = config('talkbridge.user_fields.avatar', 'avatar_path');

        if (! $col) {
            return null;
        }

        return isset($user->{$col}) ? (string) $user->{$col} : null;
    }
}

if (! function_exists('talkbridge_user_online')) {
    /**
     * Check if a user is online.
     *
     * Null-safe: returns false if $user is null.
     */
    function talkbridge_user_online($user): bool
    {
        if ($user === null || ! is_object($user)) {
            return false;
        }

        if (method_exists($user, 'isOnline')) {
            return (bool) $user->isOnline();
        }

        $col       = config('talkbridge.user_fields.last_seen', 'last_seen_at');
        $threshold = config('talkbridge.online_threshold_minutes', 2);

        if (! $col || ! isset($user->{$col}) || ! $user->{$col}) {
            return false;
        }

        return $user->{$col}->greaterThan(now()->subMinutes($threshold));
    }
}

if (! function_exists('talkbridge_user_last_seen')) {
    /**
     * Get human-readable last seen string.
     *
     * Null-safe: returns null if $user is null.
     */
    function talkbridge_user_last_seen($user): ?string
    {
        if ($user === null || ! is_object($user)) {
            return null;
        }

        if (method_exists($user, 'getChatLastSeen')) {
            return $user->getChatLastSeen();
        }

        $col = config('talkbridge.user_fields.last_seen', 'last_seen_at');

        if (! $col || ! isset($user->{$col}) || ! $user->{$col}) {
            return null;
        }

        return $user->{$col}->diffForHumans();
    }
}
