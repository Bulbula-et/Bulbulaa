<?php
declare(strict_types=1);

use Bulbula\Services\MediaUploadService;
use PHPUnit\Framework\TestCase;

final class MediaUploadServiceTest extends TestCase
{
    public function testValidPngIsDecodedReencodedAndMetadataIsMeasured(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'bulbula-image-');
        self::assertNotFalse($tmp);
        $image = imagecreatetruecolor(400, 300);
        self::assertInstanceOf(GdImage::class, $image);
        $orange = imagecolorallocate($image, 234, 106, 21);
        imagefill($image, 0, 0, $orange);
        imagepng($image, $tmp);
        imagedestroy($image);

        $result = (new MediaUploadService(false))->process([
            'tmp_name' => $tmp, 'size' => filesize($tmp), 'error' => UPLOAD_ERR_OK, 'name' => 'anything.php.png',
        ], 999999);
        @unlink($tmp);

        self::assertTrue($result['ok'], (string) ($result['error'] ?? ''));
        self::assertSame(400, $result['width']);
        self::assertSame(300, $result['height']);
        self::assertSame('image/jpeg', $result['mime']);
        self::assertMatchesRegularExpression('#^/media/branches/999999/[a-f0-9]{32}\.jpg$#', (string) $result['file_path']);
        $absolute = BULBULA_PUBLIC . $result['file_path'];
        self::assertSame('image/jpeg', (new finfo(FILEINFO_MIME_TYPE))->file($absolute));
        (new MediaUploadService(false))->deleteProcessed((string) $result['file_path']);
        @rmdir(BULBULA_PUBLIC . '/media/branches/999999');
    }

    public function testSpoofedImageExtensionIsRejectedByByteMime(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'bulbula-fake-');
        file_put_contents($tmp, '<?php echo "malicious";');
        $result = (new MediaUploadService(false))->process([
            'tmp_name' => $tmp, 'size' => filesize($tmp), 'error' => UPLOAD_ERR_OK, 'name' => 'photo.jpg',
        ], 999999);
        @unlink($tmp);
        self::assertFalse($result['ok']);
        self::assertStringContainsString('genuine JPEG', (string) $result['error']);
    }

    public function testMissingAndOversizedUploadsFailClosed(): void
    {
        $service = new MediaUploadService(false);
        self::assertFalse($service->process([], 1)['ok']);
        self::assertFalse($service->process(['error' => UPLOAD_ERR_INI_SIZE], 1)['ok']);
    }
}
