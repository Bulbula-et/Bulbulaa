<?php
declare(strict_types=1);

namespace Bulbula\Controllers\Customer;

use Bulbula\Core\Csrf;
use Bulbula\Core\Request;
use Bulbula\Core\Response;
use Bulbula\Core\Session;
use Bulbula\Core\View;
use Bulbula\Repositories\CustomerRepository;
use Bulbula\Services\CustomerAuthService;

/**
 * C-14 Save a Business & C-34 Save Management Controller.
 * Rule: Guest users browse freely; sign-in is requested only when they try to save.
 */
final class SaveController
{
    public function __construct(
        private readonly CustomerAuthService $authService = new CustomerAuthService(),
        private readonly CustomerRepository $customerRepo = new CustomerRepository()
    ) {}

    public function index(Request $request): Response
    {
        $customer = $this->authService->currentCustomer();
        if ($customer === null) {
            return Response::redirect('/auth/login?return_to=%2Faccount%2Fsaved');
        }

        $savedItems = $this->customerRepo->listSavedBusinesses((int) $customer['id']);

        $html = View::render('customer/saved', [
            'pageTitle'    => 'Saved Businesses — Bulbula.et',
            'canonicalUrl' => '/account/saved',
            'request'      => $request,
            'customer'     => $customer,
            'savedItems'   => $savedItems,
        ]);

        return Response::html($html);
    }

    public function toggle(Request $request): Response
    {
        $returnTo = (string) $request->input('return_to', '/account/saved');
        if (!str_starts_with($returnTo, '/')) {
            $returnTo = '/account/saved';
        }

        $async = (string) $request->input('_async', '') === '1';
        $customer = $this->authService->currentCustomer();
        if ($customer === null) {
            if ($async) return Response::json(['ok' => false, 'redirect' => '/auth/login?return_to=' . rawurlencode($returnTo)], 401);
            Session::flash('error', 'Please sign in with Email OTP or Google to save businesses to your Bulbula account.');
            return Response::redirect('/auth/login?return_to=' . rawurlencode($returnTo));
        }

        if (!Csrf::verify((string) $request->input('_csrf', ''))) {
            if ($async) return Response::json(['ok' => false, 'message' => 'Security token expired. Refresh the page and try again.'], 419);
            Session::flash('error', 'Security token expired.');
            return Response::redirect($returnTo);
        }
        if (empty($customer['email_verified_at'])) {
            if ($async) return Response::json(['ok' => false, 'redirect' => '/account', 'message' => 'Verify your email before saving places.'], 403);
            Session::flash('error', 'Verify your email before saving places.');
            return Response::redirect('/account');
        }

        $businessId = (int) $request->input('business_id', 0);
        $branchId   = $request->input('branch_id') ? (int) $request->input('branch_id') : null;

        if ($businessId <= 0 && $branchId !== null && $branchId > 0) {
            $stmt = \Bulbula\Core\Database::pdo()->prepare('SELECT business_id FROM branches WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $branchId]);
            $row = $stmt->fetch();
            if ($row) {
                $businessId = (int) $row['business_id'];
            }
        }

        if ($businessId > 0) {
            $nowSaved = $this->customerRepo->toggleSavedBusiness((int) $customer['id'], $businessId, $branchId);
            $message = $nowSaved ? 'Saved to your places.' : 'Removed from your saved places.';
            if ($async) return Response::json(['ok' => true, 'saved' => $nowSaved, 'message' => $message]);
            Session::flash('success', $message);
        }

        if ($async) return Response::json(['ok' => false, 'message' => 'This place could not be saved.'], 422);
        return Response::redirect($returnTo);
    }
}
