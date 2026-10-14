<?php
declare(strict_types=1);

namespace Bulbula\Controllers\Customer;

use Bulbula\Core\Csrf;
use Bulbula\Core\Request;
use Bulbula\Core\Response;
use Bulbula\Core\Session;
use Bulbula\Core\View;
use Bulbula\Repositories\CustomerRepository;
use Bulbula\Repositories\ReviewRepository;
use Bulbula\Services\AuditService;
use Bulbula\Services\CustomerAuthService;

/**
 * C-33 Customer Minimal Profile & C-36 Account Deletion / Privacy Rights Controller.
 */
final class ProfileController
{
    public function __construct(
        private readonly CustomerAuthService $authService = new CustomerAuthService(),
        private readonly CustomerRepository $customerRepo = new CustomerRepository(),
        private readonly ReviewRepository $reviewRepo = new ReviewRepository(),
        private readonly AuditService $auditService = new AuditService()
    ) {}

    public function index(Request $request): Response
    {
        $customer = $this->authService->currentCustomer();
        if ($customer === null) {
            return Response::redirect('/auth/login?return_to=%2Faccount');
        }

        $saved   = $this->customerRepo->listSavedBusinesses((int) $customer['id']);
        $reviews = $this->reviewRepo->listByCustomer((int) $customer['id']);

        $html = View::render('customer/profile', [
            'pageTitle'    => 'My Bulbula Identity & Controls — Bulbula.et',
            'canonicalUrl' => '/account',
            'request'      => $request,
            'customer'     => $customer,
            'savedItems'   => $saved,
            'myReviews'    => $reviews,
        ]);

        return Response::html($html);
    }

    public function requestDeletion(Request $request): Response
    {
        $customer = $this->authService->currentCustomer();
        if ($customer === null) {
            return Response::redirect('/auth/login');
        }

        if (!Csrf::verify((string) $request->input('_csrf', ''))) {
            Session::flash('error', 'Security token expired.');
            return Response::redirect('/account');
        }

        $requestType = (string) $request->input('request_type', 'ACCOUNT_DELETION');
        $notes       = trim((string) $request->input('notes', ''));

        $reqId = $this->customerRepo->createPrivacyRequest(
            (int) $customer['id'],
            (string) $customer['email'],
            in_array($requestType, ['ACCOUNT_DELETION', 'DATA_ACCESS'], true) ? $requestType : 'ACCOUNT_DELETION',
            $notes
        );

        $this->auditService->logCustomerAction(
            (int) $customer['id'],
            'PRIVACY_REQUEST_SUBMITTED',
            'privacy_request',
            $reqId,
            ['request_type' => $requestType]
        );

        Session::flash('success', 'Your privacy / account deletion request (#' . $reqId . ') has been recorded and routed for audited processing.');
        return Response::redirect('/account');
    }
}
