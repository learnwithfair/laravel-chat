<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

if (! function_exists('talkbridge_upload_file')) {
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
    function talkbridge_delete_file(?string $filePath): bool
    {
        if (! $filePath) return false;
        try {
            return Storage::disk(config('talkbridge.uploads.disk', 'public'))->delete($filePath);
        } catch (\Exception $e) {
            Log::error('talkbridge_delete_file: ' . $e->getMessage());
            return false;
        }
    }
}

if (! function_exists('talkbridge_delete_files')) {
    function talkbridge_delete_files(array $paths): array
    {
        $deleted = $failed = [];
        foreach ($paths as $path) {
            talkbridge_delete_file($path) ? $deleted[] = $path : $failed[] = $path;
        }
        return ['deleted' => $deleted, 'failed' => $failed];
    }
}

if (! function_exists('talkbridge_file_type')) {
    function talkbridge_file_type(string $path): string
    {
        $ext   = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $types = config('talkbridge.uploads.allowed_types', []);
        foreach ($types as $type => $extensions) {
            if (in_array($ext, $extensions, true)) return $type;
        }
        return 'file';
    }
}

if (! function_exists('talkbridge_user_name')) {
    /**
     * Resolve display name from a user model instance.
     * Supports single column or composite columns defined in config.
     */
    function talkbridge_user_name($user): string
    {
        if (method_exists($user, 'getChatDisplayName')) {
            return $user->getChatDisplayName();
        }

        $nameConfig = config('talkbridge.user_fields.name', 'name');

        if (is_array($nameConfig)) {
            return collect($nameConfig)
                ->map(fn($col) => $user->{$col} ?? '')
                ->filter()
                ->implode(' ');
        }

        return $user->{$nameConfig} ?? '';
    }
}

if (! function_exists('talkbridge_user_avatar')) {
    function talkbridge_user_avatar($user): ?string
    {
        $col = config('talkbridge.user_fields.avatar', 'avatar_path');
        return $col ? ($user->{$col} ?? null) : null;
    }
}
