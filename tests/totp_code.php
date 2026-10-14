<?php
declare(strict_types=1);

if ($argc !== 2) { fwrite(STDERR, "Usage: php tests/totp_code.php staff@example.com\n"); exit(2); }
define('BULBULA_ROOT', dirname(__DIR__));
require BULBULA_ROOT . '/vendor/autoload.php';
require BULBULA_ROOT . '/src/Core/Autoloader.php';
\Bulbula\Core\Autoloader::register(BULBULA_ROOT . '/src');
\Bulbula\Core\Config::load(BULBULA_ROOT . '/config/env.json');
$row = (new \Bulbula\Repositories\StaffRepository())->findByEmailForAuth((string) $argv[1]);
if ($row === null || empty($row['totp_secret_enc'])) exit(3);
$service = new \Bulbula\Services\StaffTwoFactorService();
$secret = $service->decryptSecret((string) $row['totp_secret_enc']);
$provider = new class implements \RobThree\Auth\Providers\Qr\IQRCodeProvider {
    public function getQRCodeImage(string $qrText, int $size): string { return ''; }
    public function getMimeType(): string { return 'text/plain'; }
};
$totp = new \RobThree\Auth\TwoFactorAuth($provider, 'Bulbula.et Staff', 6, 30);
echo $totp->getCode($secret);
