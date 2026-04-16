<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class SupportTicketAttachmentManager
{
    /**
     * @param  UploadedFile[]  $files
     * @return array<int, array<string, mixed>>
     */
    public static function storeUploadedFiles(array $files, int $userId, string $scope = 'messages'): array
    {
        $stored = [];

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $path = $file->store("support-tickets/{$userId}/{$scope}", 'public');

            $stored[] = [
                'disk' => 'public',
                'path' => $path,
                'file_name' => basename($path),
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ];
        }

        return $stored;
    }

    /**
     * @param  array<int, mixed>|null  $attachments
     * @return array<int, array<string, mixed>>
     */
    public static function presentMany(?array $attachments): array
    {
        if (! is_array($attachments)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($attachment) => self::presentOne($attachment),
            $attachments,
        )));
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function presentOne(mixed $attachment): ?array
    {
        if (is_string($attachment)) {
            return self::presentLegacyStringAttachment($attachment);
        }

        if (! is_array($attachment)) {
            return null;
        }

        $disk = is_string($attachment['disk'] ?? null) ? $attachment['disk'] : 'public';
        $path = is_string($attachment['path'] ?? null) ? $attachment['path'] : null;
        $url = is_string($attachment['url'] ?? null) ? $attachment['url'] : null;

        if ($path && ! $url) {
            $url = Storage::disk($disk)->url($path);
        }

        if (is_string($url) && str_starts_with($url, '/')) {
            $url = rtrim((string) config('app.url'), '/') . $url;
        }

        $name = $attachment['original_name']
            ?? $attachment['name']
            ?? $attachment['file_name']
            ?? ($path ? basename($path) : null);

        if (! is_string($name) || $name === '') {
            return null;
        }

        $mimeType = is_string($attachment['mime_type'] ?? null) ? $attachment['mime_type'] : null;
        $size = isset($attachment['size']) && is_numeric($attachment['size']) ? (int) $attachment['size'] : null;

        return [
            'name' => $name,
            'url' => $url,
            'mime_type' => $mimeType,
            'size' => $size,
            'is_image' => self::isImage($name, $mimeType),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function presentLegacyStringAttachment(string $attachment): ?array
    {
        $url = null;

        if (filter_var($attachment, FILTER_VALIDATE_URL)) {
            $url = $attachment;
        } elseif (str_starts_with($attachment, '/')) {
            $url = rtrim((string) config('app.url'), '/') . $attachment;
        } else {
            $normalized = ltrim($attachment, '/');

            if (str_starts_with($normalized, 'public/')) {
                $normalized = substr($normalized, 7);
            }

            if (str_starts_with($normalized, 'storage/')) {
                $url = '/' . $normalized;
            } else {
                $url = Storage::disk('public')->url($normalized);
            }

            if (str_starts_with((string) $url, '/')) {
                $url = rtrim((string) config('app.url'), '/') . $url;
            }
        }

        $nameSource = $url ?: $attachment;
        $path = parse_url($nameSource, PHP_URL_PATH) ?: $nameSource;
        $name = basename((string) $path);

        if ($name === '') {
            return null;
        }

        return [
            'name' => $name,
            'url' => $url,
            'mime_type' => null,
            'size' => null,
            'is_image' => self::isImage($name, null),
        ];
    }

    private static function isImage(string $name, ?string $mimeType): bool
    {
        if ($mimeType && str_starts_with($mimeType, 'image/')) {
            return true;
        }

        return in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], true);
    }
}