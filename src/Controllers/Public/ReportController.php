<?php
declare(strict_types=1);

namespace Bulbula\Controllers\Public;

use Bulbula\Core\Csrf;
use Bulbula\Core\RateLimiter;
use Bulbula\Core\Request;
use Bulbula\Core\Response;
use Bulbula\Core\Session;
use Bulbula\Repositories\ReportRepository;
use Bulbula\Services\CustomerAuthService;

/**
 * C-15 Public Report & Correction Suggestion Controller.
 * Rule: Guests can report listing problems/corrections; Review reports require an authenticated Customer.
 */
final class ReportController
{
    public function __construct(
        private readonly ReportRepository $reportRepo = new ReportRepository(),
        private readonly CustomerAuthService $authService = new CustomerAuthService()
    ) {}

    public function submit(Request $request): Response
    {
        $returnTo = (string) $request->input('return_to', '/');
        if (!str_starts_with($returnTo, '/')) {
            $returnTo = '/';
        }

        if (!Csrf::verify((string) $request->input('_csrf', ''))) {
            Session::flash('error', 'Security token expired. Please submit your report again.');
            return Response::redirect($returnTo);
        }

        if (!RateLimiter::allow('report:' . $request->clientIpHash(), 10, 600)) {
            Session::flash('error', 'Too many reports submitted recently. Please wait a few minutes.');
            return Response::redirect($returnTo);
        }

        $targetType = (string) $request->input('target_type', 'BRANCH_LISTING');
        $customer   = $this->authService->currentCustomer();

        // C-15 Rule: Review reports require an authenticated Customer
        if ($targetType === 'REVIEW' && $customer === null) {
            Session::flash('error', 'Please sign in to report a customer review (Guest reports are supported for listing corrections).');
            return Response::redirect('/auth/login?return_to=' . rawurlencode($returnTo));
        }

        $details = trim((string) $request->input('details_text', ''));
        if ($details === '') {
            Session::flash('error', 'Please describe the correction or issue so Bulbula Staff can verify it.');
            return Response::redirect($returnTo);
        }

        $this->reportRepo->create([
            'target_type'            => in_array($targetType, ['BRANCH_LISTING', 'BUSINESS_LISTING', 'REVIEW'], true) ? $targetType : 'BRANCH_LISTING',
            'branch_id'              => $request->input('branch_id') ? (int) $request->input('branch_id') : null,
            'business_id'            => $request->input('business_id') ? (int) $request->input('business_id') : null,
            'review_id'              => $request->input('review_id') ? (int) $request->input('review_id') : null,
            'reporter_customer_id'   => $customer ? (int) $customer['id'] : null,
            'reporter_contact_email' => $request->input('reporter_contact_email'),
            'issue_category'         => (string) $request->input('issue_category', 'WRONG_HOURS'),
            'details_text'           => $details,
        ]);

        Session::flash('success', 'Thank you! Your correction report has been routed to the Bulbula Staff verification queue.');
        return Response::redirect($returnTo);
    }
}
