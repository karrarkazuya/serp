<?php

namespace App\Services;

use App\Models\File;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileService
{
    public const ALLOWED_MIMES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain', 'text/csv', 'application/csv',
        'application/vnd.oasis.opendocument.text',
        'application/vnd.oasis.opendocument.spreadsheet',
    ];

    public const IMAGE_MIMES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
    ];

    public const ALLOWED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'webp',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'txt', 'csv', 'odt', 'ods',
    ];

    public const MAX_SIZE = 10 * 1024 * 1024; // 10 MB

    public const THUMBNAIL_WIDTH = 200;

    /**
     * Store an uploaded file, create a thumbnail if it's an image, and return the File record.
     *
     * The file UUID is what callers store in their own model columns.
     * $context should be passed for files that require per-record ownership checks at serve time
     * (currently: Ticket and ChatRoom).
     */
    public function store(
        UploadedFile $file,
        string $directory,
        ?string $permissionKey,
        ?Model $context = null,
        ?Model $source = null,
        string $disk = 'local',
    ): File {
        $uuid      = Str::uuid()->toString();
        $extension = strtolower($file->getClientOriginalExtension() ?: '');
        $filename  = $uuid . ($extension ? '.' . $extension : '');
        $mime      = $file->getMimeType() ?? 'application/octet-stream';

        $path = $file->storeAs($directory, $filename, $disk);

        $thumbnailPath = $this->makeThumbnail($path, $disk, $mime);

        return File::create([
            'uuid'           => $uuid,
            'disk'           => $disk,
            'path'           => $path,
            'thumbnail_path' => $thumbnailPath,
            'original_name'  => $file->getClientOriginalName(),
            'mime_type'      => $mime,
            'extension'      => $extension,
            'size'           => $file->getSize(),
            'permission_key' => $permissionKey,
            'context_type'   => $context ? get_class($context) : null,
            'context_id'     => $context?->getKey(),
            'source_type'    => $source?->getTable(),
            'source_id'      => $source?->getKey(),
        ]);
    }

    public function delete(File $file): void
    {
        Storage::disk($file->disk)->delete($file->path);

        if ($file->thumbnail_path) {
            Storage::disk($file->disk)->delete($file->thumbnail_path);
        }

        $file->delete();
    }

    public function deleteByUuid(string $uuid): void
    {
        $file = File::where('uuid', $uuid)->first();
        if ($file) {
            $this->delete($file);
        }
    }

    /**
     * Generate a 200px-wide JPEG thumbnail for image uploads using PHP GD.
     * Returns the thumbnail storage path, or null if not applicable.
     */
    private function makeThumbnail(string $storedPath, string $disk, string $mime): ?string
    {
        if (!in_array($mime, self::IMAGE_MIMES) || !extension_loaded('gd')) {
            return null;
        }

        $fullPath = Storage::disk($disk)->path($storedPath);
        $info     = @getimagesize($fullPath);

        if (!$info || !$info[0] || !$info[1]) {
            return null;
        }

        [$srcWidth, $srcHeight, $type] = $info;

        if ($srcWidth <= self::THUMBNAIL_WIDTH) {
            return null;
        }

        $ratio     = self::THUMBNAIL_WIDTH / $srcWidth;
        $newWidth  = self::THUMBNAIL_WIDTH;
        $newHeight = (int) round($srcHeight * $ratio);

        $src = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($fullPath),
            IMAGETYPE_PNG  => @imagecreatefrompng($fullPath),
            IMAGETYPE_GIF  => @imagecreatefromgif($fullPath),
            IMAGETYPE_WEBP => @imagecreatefromwebp($fullPath),
            default        => null,
        };

        if (!$src) {
            return null;
        }

        $dst = imagecreatetruecolor($newWidth, $newHeight);

        // Preserve alpha for PNG/GIF before resampling
        if (in_array($type, [IMAGETYPE_PNG, IMAGETYPE_GIF])) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefilledrectangle($dst, 0, 0, $newWidth, $newHeight, $transparent);
        }

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $srcWidth, $srcHeight);

        $dir       = dirname($storedPath) . '/thumbs';
        $thumbName = pathinfo($storedPath, PATHINFO_FILENAME) . '_thumb.jpg';
        $thumbPath = $dir . '/' . $thumbName;

        $tmpFile = tempnam(sys_get_temp_dir(), 'serp_thumb_');
        imagejpeg($dst, $tmpFile, 85);

        Storage::disk($disk)->put($thumbPath, file_get_contents($tmpFile));

        @unlink($tmpFile);
        imagedestroy($src);
        imagedestroy($dst);

        return $thumbPath;
    }
}
