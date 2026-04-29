<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

if (! function_exists('chat_upload_file')) {
    /**
     * Upload a file to the configured chat disk.
     */
    function chat_upload_file(UploadedFile $file, string $folder, ?string $customName = null): ?string
    {
        try {
            $disk = config('laravel-chat.uploads.disk', 'public');

            $fileName = $customName
                ? $customName . '.' . $file->getClientOriginalExtension()
                : time() . '_' . $file->getClientOriginalName();

            $path = $file->storeAs($folder, $fileName, $disk);

            return $path ? Storage::disk($disk)->url($path) : null;
        } catch (\Exception $e) {
            Log::error('chat_upload_file failed: ' . $e->getMessage());
            return null;
        }
    }
}

if (! function_exists('chat_delete_file')) {
    /**
     * Delete a file from the configured chat disk.
     */
    function chat_delete_file(?string $filePath): bool
    {
        if (! $filePath) {
            return false;
        }

        try {
            $disk = config('laravel-chat.uploads.disk', 'public');
            return Storage::disk($disk)->delete($filePath);
        } catch (\Exception $e) {
            Log::error('chat_delete_file failed: ' . $e->getMessage());
            return false;
        }
    }
}

if (! function_exists('chat_delete_files')) {
    /**
     * Delete multiple files from the configured chat disk.
     */
    function chat_delete_files(array $filePaths): array
    {
        $deleted = [];
        $failed  = [];

        foreach ($filePaths as $path) {
            chat_delete_file($path) ? $deleted[] = $path : $failed[] = $path;
        }

        return ['deleted' => $deleted, 'failed' => $failed];
    }
}

if (! function_exists('chat_get_file_type')) {
    /**
     * Determine file type category from extension.
     */
    function chat_get_file_type(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $allowedTypes = config('laravel-chat.uploads.allowed_types', []);

        foreach ($allowedTypes as $type => $extensions) {
            if (in_array($ext, $extensions, true)) {
                return $type;
            }
        }

        return 'file';
    }
}
