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

        $customer = $this->authService->currentCustomer();
        if ($customer === null) {
            Session::flash('error', 'Please sign in with Email OTP or Google to save businesses to your Bulbula account.');
            return Response::redirect('/auth/login?return_to=' . rawurlencode($returnTo));
        }

        if (!Csrf::verify((string) $request->input('_csrf', ''))) {
            Session::flash('error', 'Security token expired.');
            return Response::redirect($returnTo);
        }

        $businessId = (int) $request->input('business_id', 0);
        $branchId   = $request->input('branch_id') ? (int) $request->input('branch_id') : null;

        if ($businessId > 0) {
            $nowSaved = $this->customerRepo->toggleSavedBusiness((int) $customer['id'], $businessId, $branchId);
            Session::flash('success', $nowSaved ? 'Business saved to your Bulbula list.' : 'Business removed from your Saved list.');
        }

        return Response::redirect($returnTo);
    }
}
