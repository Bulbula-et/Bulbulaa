<?php
declare(strict_types=1);
namespace Bulbula\Controllers\Staff;

use Bulbula\Core\Csrf;
use Bulbula\Core\Request;
use Bulbula\Core\Response;
use Bulbula\Core\Session;
use Bulbula\Core\View;
use Bulbula\Services\AuditService;
use Bulbula\Services\EmailTemplateCatalog;
use Bulbula\Services\EmailTemplateService;
use Bulbula\Services\StaffAuthService;

/** Admin-only, draft-first transactional email designer. Never sends an email. */
final class EmailTemplateController
{
    public function __construct(
        private readonly StaffAuthService $auth = new StaffAuthService(),
        private readonly EmailTemplateService $templates = new EmailTemplateService(),
        private readonly AuditService $audit = new AuditService()
    ) {}

    public function index(Request $request): Response
    {
        $key = (string)$request->query('type', 'registration_code');
        if (!isset(EmailTemplateCatalog::LABELS[$key])) return Response::redirect('/staff/email-templates');
        $rows = $this->templates->revisions($key);
        $active = $this->templates->activeId($key);
        $selected = (int)$request->query('revision', 0);
        $revision = null;
        foreach ($rows as $row) {
            if (($selected > 0 && (int)$row['id'] === $selected) || ($selected === 0 && (int)$row['id'] === $active)) { $revision = $row; break; }
        }
        $initial = EmailTemplateCatalog::initial($key);
        return Response::html(View::render('staff/email-templates', [
            'pageTitle'=>'Email designs — Staff Console', 'activeNav'=>'email-templates', 'staff'=>$this->auth->currentStaff(),
            'key'=>$key, 'catalog'=>$this->templates->list(), 'revisions'=>$rows, 'activeId'=>$active,
            'selectedRevision'=>$revision, 'initial'=>$initial, 'requiredToken'=>'{{'.EmailTemplateCatalog::required($key).'}}',
        ], 'staff'));
    }

    public function save(Request $request): Response
    {
        if (!$this->allowed($request)) return Response::redirect('/staff/email-templates');
        $key = (string)$request->input('template_key', '');
        if (!isset(EmailTemplateCatalog::LABELS[$key])) return Response::redirect('/staff/email-templates');
        $actor = (int)($this->auth->currentStaff()['id'] ?? 0);
        try {
            $id = $this->templates->saveDraft($key, (string)$request->input('subject', ''), (string)$request->input('html', ''), (string)$request->input('text', ''), (string)$request->input('project', ''), $actor);
            $this->audit->logStaffAction($actor, 'EMAIL_TEMPLATE_DRAFT_SAVED', 'email_template_revision', $id, null, ['type'=>$key]);
            Session::flash('success', 'Draft saved. It will not affect outgoing emails until you publish it.');
            return Response::redirect('/staff/email-templates?type=' . rawurlencode($key) . '&revision=' . $id);
        } catch (\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
        } catch (\Throwable) {
            Session::flash('error', 'Could not safely save this design. No active email was changed.');
        }
        return Response::redirect('/staff/email-templates?type=' . rawurlencode($key));
    }

    public function publish(Request $request): Response
    {
        if (!$this->allowed($request)) return Response::redirect('/staff/email-templates');
        $key = (string)$request->input('template_key', '');
        if (!isset(EmailTemplateCatalog::LABELS[$key])) return Response::redirect('/staff/email-templates');
        $revision = (int)$request->input('revision_id', 0);
        $actor = (int)($this->auth->currentStaff()['id'] ?? 0);
        try {
            $this->templates->publish($key, $revision);
            $this->audit->logStaffAction($actor, 'EMAIL_TEMPLATE_PUBLISHED', 'email_template_revision', $revision, null, ['type'=>$key]);
            Session::flash('success', 'Published. Future emails of this type now use this design; earlier revisions remain available for rollback.');
        } catch (\Throwable) { Session::flash('error', 'Could not publish this revision; the currently active design remains unchanged.'); }
        return Response::redirect('/staff/email-templates?type=' . rawurlencode($key) . '&revision=' . $revision);
    }

    private function allowed(Request $request): bool
    {
        return Csrf::verify((string)$request->input('_csrf', '')) && $this->auth->currentRole()->canApproveCampaigns();
    }
}
