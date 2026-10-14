<?php
declare(strict_types=1);

namespace Bulbula\Controllers\Staff;

use Bulbula\Core\Csrf;
use Bulbula\Core\Request;
use Bulbula\Core\Response;
use Bulbula\Core\Session;
use Bulbula\Core\View;
use Bulbula\Repositories\ReviewRepository;
use Bulbula\Services\ModerationService;
use Bulbula\Services\StaffAuthService;

/**
 * C-25 Pre-Publication Review Moderation Controller.
 */
final class ModerationController
{
    public function __construct(
        private readonly StaffAuthService $staffAuth = new StaffAuthService(),
        private readonly ReviewRepository $reviewRepo = new ReviewRepository(),
        private readonly ModerationService $moderationService = new ModerationService()
    ) {}

    public function index(Request $request): Response
    {
        $status = (string) $request->query('status', 'PENDING_MODERATION');
        $html = View::render('staff/moderation', [
            'pageTitle'    => 'Review Moderation Queue — Staff Console',
            'activeNav'    => 'moderation',
            'staff'        => $this->staffAuth->currentStaff(),
            'statusFilter' => $status,
            'reviews'      => $this->reviewRepo->listModerationQueue($status, 100),
        ], 'staff');

        return Response::html($html);
    }

    public function decide(Request $request): Response
    {
        if (!Csrf::verify((string) $request->input('_csrf', ''))) {
            Session::flash('error', 'Security token expired.');
            return Response::redirect('/staff/moderation');
        }

        $staff      = $this->staffAuth->currentStaff();
        $reviewId   = (int) $request->param('review_id', 0);
        $decision   = (string) $request->input('decision', 'PUBLISHED');
        $reasonCode = (string) $request->input('reason_code', 'POLICY_COMPLIANT');
        $notes      = trim((string) $request->input('notes', ''));

        $res = $this->moderationService->decideReview($reviewId, (int) ($staff['id'] ?? 1), $decision, $reasonCode, $notes);
        if ($res['ok']) {
            Session::flash('success', 'Review #' . $reviewId . ' moderated (' . $decision . ') and branch unweighted mean recalculated.');
        } else {
            Session::flash('error', (string) ($res['error'] ?? 'Moderation failed.'));
        }

        return Response::redirect('/staff/moderation');
    }
}
