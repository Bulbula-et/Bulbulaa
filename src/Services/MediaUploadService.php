<?php
declare(strict_types=1);

namespace Bulbula\Services;

/**
 * Native-PHP/GD image pipeline for cheap shared hosting.
 * Validates actual bytes, decodes and re-encodes to JPEG (stripping metadata),
 * bounds dimensions/pixels, resizes, and writes with a random server filename.
 */
final class MediaUploadService
{
    private const MAX_BYTES = 8_388_608; // 8 MiB
    private const MAX_PIXELS = 30_000_000;
    private const MAX_WIDTH = 1600;
    private const MAX_HEIGHT = 1200;
    private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(private readonly bool $requireHttpUpload = true) {}

    /** @return array{ok: bool, error?: string, file_path?: string, width?: int, height?: int, size?: int, mime?: string} */
    public function process(array $file, int $branchId): array
    {
        if (!extension_loaded('gd') || !class_exists(\finfo::class)) {
            return ['ok' => false, 'error' => 'Server image processing requires PHP GD and Fileinfo.'];
        }
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) return ['ok' => false, 'error' => $this->uploadError($error)];

        $tmp = (string) ($file['tmp_name'] ?? '');
        $declaredSize = (int) ($file['size'] ?? 0);
        if ($tmp === '' || !is_file($tmp) || ($this->requireHttpUpload && !is_uploaded_file($tmp))) {
            return ['ok' => false, 'error' => 'The uploaded file could not be verified.'];
        }
        $actualSize = filesize($tmp);
        if ($actualSize === false || $actualSize < 1 || $actualSize > self::MAX_BYTES || $declaredSize > self::MAX_BYTES) {
            return ['ok' => false, 'error' => 'Image must be between 1 byte and 8 MiB.'];
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (!is_string($mime) || !in_array($mime, self::ALLOWED_MIME, true)) {
            return ['ok' => false, 'error' => 'Only genuine JPEG, PNG, or WebP images are accepted.'];
        }
        $info = @getimagesize($tmp);
        if (!is_array($info) || empty($info[0]) || empty($info[1])) {
            return ['ok' => false, 'error' => 'Image dimensions could not be validated.'];
        }
        $sourceWidth = (int) $info[0];
        $sourceHeight = (int) $info[1];
        if ($sourceWidth < 320 || $sourceHeight < 240 || ($sourceWidth * $sourceHeight) > self::MAX_PIXELS) {
            return ['ok' => false, 'error' => 'Image must be at least 320×240 and no more than 30 megapixels.'];
        }

        $bytes = file_get_contents($tmp);
        if ($bytes === false) return ['ok' => false, 'error' => 'Image could not be read.'];
        $source = @imagecreatefromstring($bytes);
        unset($bytes);
        if (!$source instanceof \GdImage) return ['ok' => false, 'error' => 'Image decoder rejected the file.'];

        $scale = min(1, self::MAX_WIDTH / $sourceWidth, self::MAX_HEIGHT / $sourceHeight);
        $width = max(1, (int) floor($sourceWidth * $scale));
        $height = max(1, (int) floor($sourceHeight * $scale));
        $target = imagecreatetruecolor($width, $height);
        if (!$target instanceof \GdImage) {
            imagedestroy($source);
            return ['ok' => false, 'error' => 'Image workspace could not be allocated.'];
        }
        $white = imagecolorallocate($target, 255, 255, 255);
        imagefill($target, 0, 0, $white);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);
        imagedestroy($source);

        $relativeDir = 'media/branches/' . $branchId;
        $absoluteDir = (defined('BULBULA_PUBLIC') ? BULBULA_PUBLIC : dirname(__DIR__, 2) . '/public_html') . '/' . $relativeDir;
        if (!is_dir($absoluteDir) && !@mkdir($absoluteDir, 0755, true) && !is_dir($absoluteDir)) {
            imagedestroy($target);
            return ['ok' => false, 'error' => 'Media storage directory is unavailable.'];
        }
        $filename = bin2hex(random_bytes(16)) . '.jpg';
        $final = $absoluteDir . '/' . $filename;
        $temp = $final . '.tmp';
        $written = imagejpeg($target, $temp, 86);
        imagedestroy($target);
        if (!$written || !is_file($temp)) {
            @unlink($temp);
            return ['ok' => false, 'error' => 'Processed image could not be written.'];
        }
        @chmod($temp, 0644);
        if (!rename($temp, $final)) {
            @unlink($temp);
            return ['ok' => false, 'error' => 'Processed image could not be activated.'];
        }
        $size = filesize($final);
        return [
            'ok' => true,
            'file_path' => '/' . $relativeDir . '/' . $filename,
            'width' => $width,
            'height' => $height,
            'size' => $size === false ? 0 : $size,
            'mime' => 'image/jpeg',
        ];
    }

    public function deleteProcessed(string $publicPath): void
    {
        if (!preg_match('#^/media/branches/[0-9]+/[a-f0-9]{32}\.jpg$#', $publicPath)) return;
        $root = defined('BULBULA_PUBLIC') ? BULBULA_PUBLIC : dirname(__DIR__, 2) . '/public_html';
        $absolute = $root . $publicPath;
        if (is_file($absolute)) @unlink($absolute);
    }

    private function uploadError(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The uploaded image exceeds the server size limit.',
            UPLOAD_ERR_PARTIAL => 'The image upload was incomplete.',
            UPLOAD_ERR_NO_FILE => 'Choose an image to upload.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'The server could not securely receive the image.',
            default => 'Unknown image upload error.',
        };
    }
}
