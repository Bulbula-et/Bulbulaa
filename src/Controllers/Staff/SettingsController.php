<?php
declare(strict_types=1);

namespace Bulbula\Controllers\Staff;

use Bulbula\Core\Csrf;
use Bulbula\Core\Logger;
use Bulbula\Core\Mailer;
use Bulbula\Core\RateLimiter;
use Bulbula\Core\Request;
use Bulbula\Core\Response;
use Bulbula\Core\Session;
use Bulbula\Core\View;
use Bulbula\Services\AuditService;
use Bulbula\Services\ConfigurationService;
use Bulbula\Services\StaffAuthService;

/**
 * Live Zero-Restart System, Auth & Mail Configuration Controller (/staff/settings).
 */
final class SettingsController
{
    public function __construct(
        private readonly StaffAuthService $staffAuth = new StaffAuthService(),
        private readonly AuditService $auditService = new AuditService(),
        private readonly ConfigurationService $configuration = new ConfigurationService()
    ) {}

    public function index(Request $request): Response
    {
        $role   = $this->staffAuth->currentRole();
        $rawEnv = $this->configuration->safeSnapshot();

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

        $input = $request->input();
        if (!is_array($input)) $input = [];
        unset($input['_csrf']);
        $section = trim((string) ($input['settings_section'] ?? ''));
        unset($input['settings_section']);
        $actor = $this->staffAuth->currentStaff();
        try {
            $summary = $section !== ''
                ? $this->configuration->updateSection($section, $input)
                : $this->configuration->update($input);
        } catch (\Throwable $exception) {
            $this->auditService->logStaffAction(
                (int) ($actor['id'] ?? 1), 'SYSTEM_SETTINGS_REJECTED', 'system_config', 1,
                null, ['reason_class' => $exception::class, 'reason_sha256' => hash('sha256', $exception->getMessage())]
            );
            Session::flash('error', $exception instanceof \InvalidArgumentException
                ? $exception->getMessage()
                : 'Configuration was not changed because secure validation or atomic activation failed.');
            return Response::redirect('/staff/settings');
        }

        $actor = $this->staffAuth->currentStaff();
        $this->auditService->logStaffAction(
            (int) ($actor['id'] ?? 1),
            'SYSTEM_SETTINGS_UPDATED',
            'system_config',
            1,
            null,
            $summary
        );

        $label = match ($section) { 'security' => 'Security settings', 'auth' => 'Identity-provider settings', 'mail' => 'Email-delivery settings', default => 'Settings' };
        Session::flash('success', $label . ' saved atomically and activated without a server restart. Server environment variables remain authoritative.');
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
        if (strlen($recipient) > 254 || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            Session::flash('error', 'Enter a valid owner-controlled test email address.');
            return Response::redirect('/staff/settings');
        }
        $actor = $this->staffAuth->currentStaff();
        $staffId = (int) ($actor['id'] ?? 0);
        if ($staffId < 1 || !RateLimiter::allow('staff-mail-test:' . $staffId, 3, 3600)) {
            Session::flash('error', 'Email test limit reached. Try again after one hour.');
            return Response::redirect('/staff/settings');
        }

        $reference = bin2hex(random_bytes(8));
        $text = "Bulbula.et transport acceptance test. Reference: {$reference}. Acceptance by a mail transport does not prove inbox delivery. Please check your inbox, junk folder, and message authentication headers.";
        $result = Mailer::send(
            $recipient,
            'Bulbula.et mail transport acceptance test',
            '<div style="font-family:Arial,sans-serif;padding:24px"><h1 style="color:#EA6A15">Transport acceptance test</h1><p>' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</p></div>',
            $text
        );
        try {
            $this->auditService->logStaffAction(
                $staffId, 'SYSTEM_MAIL_TEST_ATTEMPTED', 'system_config', 1,
                null, ['accepted_by_transport' => (bool) ($result['ok'] ?? false), 'transport' => (string) ($result['transport'] ?? 'unknown'), 'test_reference' => $reference]
            );
        } catch (\Throwable $exception) {
            Logger::warning('Mail test audit persistence failed', ['reason_class' => $exception::class]);
        }

        if ($result['ok']) {
            Session::flash('success', "Mail transport accepted test {$reference}. Ask the recipient to confirm inbox placement and SPF/DKIM/DMARC results; this is not delivery proof.");
        } else {
            Session::flash('error', "Mail transport did not accept test {$reference}. Check secure mail configuration and logs; no inbox delivery is claimed.");
        }
        return Response::redirect('/staff/settings');
    }
}
