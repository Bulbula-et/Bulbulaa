<?php
declare(strict_types=1);

define('BULBULA_ROOT', dirname(__DIR__));
require BULBULA_ROOT . '/vendor/autoload.php';
require BULBULA_ROOT . '/src/Core/Autoloader.php';
\Bulbula\Core\Autoloader::register(BULBULA_ROOT . '/src');

$dir = $argv[1] ?? '';
$id = (int) ($argv[2] ?? 0);
\Bulbula\Core\Config::load($dir . '/env.json');
$service = new \Bulbula\Services\ConfigurationService($dir . '/env.json', $dir . '/env.local.json', $dir . '/update.lock');
$service->update([
    'require_staff_login'=>'1', 'otp_expiry_seconds'=>'600', 'review_edit_window_days'=>'30',
    'google_client_id'=>'', 'google_client_secret'=>'', 'telegram_bot_username'=>'BulbulaEtBot', 'telegram_bot_token'=>'',
    'mail_driver'=>'smtp', 'mail_host'=>'smtp' . $id . '.example.com', 'mail_port'=>'587', 'mail_encryption'=>'tls',
    'mail_username'=>'mailer@example.com', 'mail_password'=>'', 'mail_from_email'=>'no-reply@example.com', 'mail_from_name'=>'Bulbula',
]);
