<?php
declare(strict_types=1);

namespace Bulbula\Services;

use Bulbula\Core\Cache;
use Bulbula\Core\Config;
use InvalidArgumentException;
use RuntimeException;

/** Validates and atomically activates the administrator-managed local config overlay. */
final class ConfigurationService
{
    private const MASK = '••••••••';
    private const ALLOWED = [
        'require_staff_login', 'otp_expiry_seconds', 'review_edit_window_days',
        'google_client_id', 'google_client_secret', 'telegram_bot_username', 'telegram_bot_token',
        'mail_driver', 'mail_host', 'mail_port', 'mail_encryption', 'mail_username',
        'mail_password', 'mail_from_email', 'mail_from_name',
    ];

    public function __construct(
        private readonly ?string $basePath = null,
        private readonly ?string $localPath = null,
        private readonly ?string $lockPath = null
    ) {}

    /** @param array<string,mixed> $input @return array<string,mixed> safe audit summary */
    public function update(array $input): array
    {
        $unknown = array_diff(array_keys($input), self::ALLOWED);
        if ($unknown !== []) throw new InvalidArgumentException('Unsupported configuration field: ' . reset($unknown));
        foreach (self::ALLOWED as $required) {
            if (!array_key_exists($required, $input)) throw new InvalidArgumentException("Missing configuration field: {$required}");
        }
        $validated = $this->validate($input);
        $lock = fopen($this->lockFile(), 'c+b');
        if ($lock === false) throw new RuntimeException('Configuration update lock is unavailable.');
        @chmod($this->lockFile(), 0600);
        if (!flock($lock, LOCK_EX)) { fclose($lock); throw new RuntimeException('Configuration update lock is unavailable.'); }
        try {
            $data = $this->readLocal();
            foreach ($validated as $section => $values) {
                foreach ($values as $key => $value) {
                    if ($value !== null) $data[$section][$key] = $value;
                }
            }
            $this->atomicWrite($data);
            Config::load($this->baseFile());
            Cache::flushAll();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        return [
            'require_staff_login' => $validated['security']['require_staff_login'],
            'google_client_id_set' => $validated['auth']['google_client_id'] !== '',
            'telegram_bot_username' => $validated['auth']['telegram_bot_username'],
            'mail_host' => $validated['mail']['host'],
            'mail_port' => $validated['mail']['port'],
        ];
    }

    /** @return array<string,mixed> */
    public function safeSnapshot(): array
    {
        $all = Config::all();
        foreach ([['auth','google_client_secret'], ['auth','telegram_bot_token'], ['mail','password']] as [$section, $key]) {
            $set = trim((string) ($all[$section][$key] ?? '')) !== '';
            unset($all[$section][$key]);
            $all[$section][$key . '_is_set'] = $set;
        }
        return $all;
    }

    /** @param array<string,mixed> $input @return array<string,array<string,mixed>> */
    private function validate(array $input): array
    {
        $clientId = trim((string) $input['google_client_id']);
        if ($clientId !== '' && preg_match('/^[0-9]+-[a-z0-9_-]+\.apps\.googleusercontent\.com$/i', $clientId) !== 1) {
            throw new InvalidArgumentException('Google client ID format is invalid.');
        }
        $bot = trim((string) $input['telegram_bot_username']);
        if (preg_match('/^[A-Za-z0-9_]{5,32}$/', $bot) !== 1) throw new InvalidArgumentException('Telegram bot username format is invalid.');
        $host = trim((string) $input['mail_host']);
        if (!$this->validHost($host)) throw new InvalidArgumentException('SMTP host must be a valid hostname or IP address without a scheme.');
        $fromEmail = trim((string) $input['mail_from_email']);
        if (filter_var($fromEmail, FILTER_VALIDATE_EMAIL) === false) throw new InvalidArgumentException('Sender email address is invalid.');
        $username = trim((string) $input['mail_username']);
        if ($username === '' || strlen($username) > 254 || preg_match('/[\x00-\x1F\x7F]/', $username)) throw new InvalidArgumentException('SMTP username is invalid.');
        $fromName = trim((string) $input['mail_from_name']);
        if ($fromName === '' || mb_strlen($fromName) > 100 || preg_match('/[\r\n]/', $fromName)) throw new InvalidArgumentException('Sender name is invalid.');
        $driver = (string) $input['mail_driver'];
        if (!in_array($driver, ['smtp', 'mail'], true)) throw new InvalidArgumentException('Mail transport is invalid.');
        $encryption = (string) $input['mail_encryption'];
        if (!in_array($encryption, ['tls', 'ssl', 'none'], true)) throw new InvalidArgumentException('Mail encryption mode is invalid.');
        $port = filter_var($input['mail_port'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
        if ($port === false) throw new InvalidArgumentException('SMTP port must be between 1 and 65535.');
        $otp = filter_var($input['otp_expiry_seconds'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 120, 'max_range' => 3600]]);
        if ($otp === false) throw new InvalidArgumentException('OTP expiry must be between 120 and 3600 seconds.');
        $review = filter_var($input['review_edit_window_days'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 90]]);
        if ($review === false) throw new InvalidArgumentException('Review edit window must be between 1 and 90 days.');

        return [
            'security' => ['require_staff_login' => (string) $input['require_staff_login'] === '1', 'otp_expiry_seconds' => $otp, 'review_edit_window_days' => $review],
            'auth' => [
                'google_client_id' => $clientId,
                'google_client_secret' => $this->optionalSecret($input['google_client_secret'], 'Google client secret'),
                'telegram_bot_username' => $bot,
                'telegram_bot_token' => $this->optionalSecret($input['telegram_bot_token'], 'Telegram bot token'),
            ],
            'mail' => [
                'driver' => $driver, 'host' => $host, 'port' => $port, 'encryption' => $encryption,
                'username' => $username, 'password' => $this->optionalSecret($input['mail_password'], 'SMTP password'),
                'from_email' => $fromEmail, 'from_name' => $fromName,
            ],
        ];
    }

    private function optionalSecret(mixed $value, string $label): ?string
    {
        $secret = trim((string) $value);
        if ($secret === '' || $secret === self::MASK) return null;
        if (strlen($secret) < 8 || strlen($secret) > 4096 || preg_match('/[\x00-\x1F\x7F]/', $secret)) throw new InvalidArgumentException("{$label} is invalid.");
        return $secret;
    }

    private function validHost(string $host): bool
    {
        if ($host === '' || strlen($host) > 253 || str_contains($host, '://')) return false;
        return filter_var($host, FILTER_VALIDATE_IP) !== false
            || filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
    }

    /** @return array<string,mixed> */
    private function readLocal(): array
    {
        $path = $this->localFile();
        if (!is_file($path)) return [];
        $raw = file_get_contents($path);
        if ($raw === false) throw new RuntimeException('Protected configuration could not be read.');
        $data = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($data)) throw new RuntimeException('Protected configuration root must be an object.');
        return $data;
    }

    /** @param array<string,mixed> $data */
    private function atomicWrite(array $data): void
    {
        $target = $this->localFile();
        $tmp = $target . '.tmp.' . bin2hex(random_bytes(8));
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        $handle = fopen($tmp, 'xb');
        if ($handle === false) throw new RuntimeException('Could not create protected temporary configuration.');
        try {
            if (!chmod($tmp, 0600) || fwrite($handle, $json) !== strlen($json) || !fflush($handle)) throw new RuntimeException('Could not persist protected temporary configuration.');
            if (function_exists('fsync') && !fsync($handle)) throw new RuntimeException('Could not synchronize protected temporary configuration.');
        } catch (\Throwable $exception) {
            fclose($handle);
            @unlink($tmp);
            throw $exception;
        }
        fclose($handle);
        if (!rename($tmp, $target)) { @unlink($tmp); throw new RuntimeException('Could not atomically activate protected configuration.'); }
        chmod($target, 0600);
    }

    private function root(): string { return defined('BULBULA_ROOT') ? BULBULA_ROOT : dirname(__DIR__, 2); }
    private function baseFile(): string { return $this->basePath ?? $this->root() . '/config/env.json'; }
    private function localFile(): string { return $this->localPath ?? $this->root() . '/config/env.local.json'; }
    private function lockFile(): string { return $this->lockPath ?? $this->root() . '/storage/config-update.lock'; }
}
