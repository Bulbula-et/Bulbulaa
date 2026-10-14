<?php
declare(strict_types=1);

namespace Bulbula\Controllers\Staff;

use Bulbula\Core\Cache;
use Bulbula\Core\Config;
use Bulbula\Core\Csrf;
use Bulbula\Core\Mailer;
use Bulbula\Core\Request;
use Bulbula\Core\Response;
use Bulbula\Core\Session;
use Bulbula\Core\View;
use Bulbula\Services\AuditService;
use Bulbula\Services\StaffAuthService;

/**
 * Live Zero-Restart System, Auth & Mail Configuration Controller (/staff/settings).
 */
final class SettingsController
{
    public function __construct(
        private readonly StaffAuthService $staffAuth = new StaffAuthService(),
        private readonly AuditService $auditService = new AuditService()
    ) {}

    public function index(Request $request): Response
    {
        $role   = $this->staffAuth->currentRole();
        $rawEnv = Config::all();

        $html = View::render('staff/settings', [
            'pageTitle' => 'System, Auth & SMTP Settings (Zero-Restart) — Staff Console',
            'activeNav' => 'settings',
            'staff'     => $this->staffAuth->currentStaff(),
            'canManage' => $role->canApproveCampaigns(),
            'envConfig' => $rawEnv,
        ], 'staff');

        return Response::html($html);
    }

    public function save(Request $request): Response
    {
        if (!Csrf::verify((string) $request->input('_csrf', ''))) {
            Session::flash('error', 'Security token expired.');
            return Response::redirect('/staff/settings');
        }

        if (!$this->staffAuth->currentRole()->canApproveCampaigns()) {
            Session::flash('error', 'Only an Administrator can update system configuration.');
            return Response::redirect('/staff/settings');
        }

        // Secrets and site-specific settings are written only to an ignored local overlay.
        // Real server environment variables still take precedence after reload.
        $basePath  = BULBULA_ROOT . '/config/env.json';
        $localPath = BULBULA_ROOT . '/config/env.local.json';
        $data      = is_file($localPath)
            ? (json_decode((string) file_get_contents($localPath), true) ?: [])
            : [];

        // 1. Security & Staff Login Mode
        $data['security']['require_staff_login']     = (string) $request->input('require_staff_login', '0') === '1';
        $data['security']['otp_expiry_seconds']      = max(120, min(3600, (int) $request->input('otp_expiry_seconds', 600)));
        $data['security']['review_edit_window_days'] = max(1, min(90, (int) $request->input('review_edit_window_days', 30)));

        // 2. Google OAuth 2.0 & Telegram Mini App Settings (C-30)
        $data['auth']['google_client_id']      = trim((string) $request->input('google_client_id', ''));
        $googleSecretInput                     = trim((string) $request->input('google_client_secret', ''));
        if ($googleSecretInput !== '' && $googleSecretInput !== '••••••••') {
            $data['auth']['google_client_secret'] = $googleSecretInput;
        }
        $data['auth']['telegram_bot_username'] = trim((string) $request->input('telegram_bot_username', 'BulbulaEtBot'));
        $tgTokenInput                          = trim((string) $request->input('telegram_bot_token', ''));
        if ($tgTokenInput !== '' && $tgTokenInput !== '••••••••') {
            $data['auth']['telegram_bot_token'] = $tgTokenInput;
        }

        // 3. SMTP Email OTP Settings (C-31)
        $data['mail']['driver']     = in_array($request->input('mail_driver'), ['smtp', 'mail'], true) ? (string) $request->input('mail_driver') : 'smtp';
        $data['mail']['host']       = trim((string) $request->input('mail_host', 'mail.bulbula.et'));
        $data['mail']['port']       = max(1, min(65535, (int) $request->input('mail_port', 587)));
        $data['mail']['encryption'] = in_array($request->input('mail_encryption'), ['tls', 'ssl', 'none'], true) ? (string) $request->input('mail_encryption') : 'tls';
        $data['mail']['username']   = trim((string) $request->input('mail_username', 'no-reply@bulbula.et'));
        $mailPassInput              = trim((string) $request->input('mail_password', ''));
        if ($mailPassInput !== '' && $mailPassInput !== '••••••••') {
            $data['mail']['password'] = $mailPassInput;
        }
        $data['mail']['from_email'] = trim((string) $request->input('mail_from_email', 'no-reply@bulbula.et'));
        $data['mail']['from_name']  = trim((string) $request->input('mail_from_name', 'Bulbula.et Verification'));

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        $tmp  = $localPath . '.tmp.' . bin2hex(random_bytes(6));
        if (file_put_contents($tmp, $json, LOCK_EX) === false) {
            Session::flash('error', 'Could not write the protected local configuration file.');
            return Response::redirect('/staff/settings');
        }
        @chmod($tmp, 0600);
        if (!rename($tmp, $localPath)) {
            @unlink($tmp);
            Session::flash('error', 'Could not atomically activate the protected local configuration.');
            return Response::redirect('/staff/settings');
        }
        Config::load($basePath);
        Cache::flushAll();

        $actor = $this->staffAuth->currentStaff();
        $this->auditService->logStaffAction(
            (int) ($actor['id'] ?? 1),
            'SYSTEM_SETTINGS_UPDATED',
            'system_config',
            1,
            null,
            [
                'require_staff_login'   => $data['security']['require_staff_login'],
                'google_client_id_set'  => $data['auth']['google_client_id'] !== '',
                'mail_host'             => $data['mail']['host'],
                'mail_port'             => $data['mail']['port'],
                'telegram_bot_username' => $data['auth']['telegram_bot_username'],
            ]
        );

        Session::flash('success', 'Settings saved atomically to protected config/env.local.json and hot-reloaded without a server restart. Server environment variables remain authoritative.');
        return Response::redirect('/staff/settings');
    }

    public function testEmail(Request $request): Response
    {
        if (!Csrf::verify((string) $request->input('_csrf', ''))) {
            Session::flash('error', 'Security token expired.');
            return Response::redirect('/staff/settings');
        }
        if (!$this->staffAuth->currentRole()->canApproveCampaigns()) {
            Session::flash('error', 'Only an Administrator can test SMTP delivery.');
            return Response::redirect('/staff/settings');
        }

        $recipient = trim((string) $request->input('test_email', ''));
        $result = Mailer::send(
            $recipient,
            'Bulbula.et SMTP configuration test',
            '<div style="font-family:Arial,sans-serif;padding:24px"><h1 style="color:#EA6A15">SMTP is working</h1><p>This message confirms that Bulbula.et can deliver customer verification codes.</p><p>Tested at ' . htmlspecialchars(date('Y-m-d H:i:s T'), ENT_QUOTES, 'UTF-8') . '.</p></div>',
            'Bulbula.et SMTP is working. This message confirms customer OTP delivery.'
        );

        if ($result['ok']) {
            Session::flash('success', 'Test email delivered to ' . $recipient . ' using ' . strtoupper((string) ($result['transport'] ?? 'mail')) . '.');
        } else {
            Session::flash('error', 'Email test failed: ' . (string) ($result['error'] ?? 'Unknown transport error.'));
        }
        return Response::redirect('/staff/settings');
    }
}
