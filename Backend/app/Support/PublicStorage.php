<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

final class PublicStorage
{
    /**
     * @var list<string>
     */
    public const PUBLIC_PREFIXES = [
        'attendance/',
        'field-activities/',
        'employees/profile-photos/',
    ];

    public static function relativePath(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        $path = str_replace('\\', '/', trim($path));
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            $path = (string) (parse_url($path, PHP_URL_PATH) ?: '');
        }

        $path = ltrim($path, '/');
        foreach (['public/', 'storage/'] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                $path = substr($path, strlen($prefix));
            }
        }

        $path = ltrim($path, '/');

        return $path !== '' ? $path : null;
    }

    public static function isPublicPath(?string $path): bool
    {
        $relative = self::relativePath($path);
        if ($relative === null || str_contains($relative, '..')) {
            return false;
        }

        foreach (self::PUBLIC_PREFIXES as $prefix) {
            if (str_starts_with($relative, $prefix)) {
                return true;
            }
        }

        return false;
    }

    public static function url(?string $path): ?string
    {
        $relative = self::relativePath($path);
        if ($relative === null) {
            return null;
        }

        return url('storage/'.$relative);
    }

    public static function response(string $path): Response
    {
        $relative = self::relativePath($path);
        abort_unless(is_string($relative) && self::isPublicPath($relative), 404);

        $disk = self::disk();
        abort_unless($disk->exists($relative), 404);

        $mime = $disk->mimeType($relative) ?: 'application/octet-stream';

        return response($disk->get($relative), 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.basename($relative).'"',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    public static function disk(): Filesystem
    {
        return Storage::disk('public');
    }
}
