<?php
declare(strict_types=1);
namespace Bulbula\Controllers\Customer;

use Bulbula\Core\Csrf;
use Bulbula\Core\Session;
use Bulbula\Core\RateLimiter;
use Bulbula\Core\Request;
use Bulbula\Core\Response;
use Bulbula\Repositories\AccountSessionRepository;
use Bulbula\Repositories\CustomerRepository;
use Bulbula\Repositories\CustomerTelegramLinkRepository;
use Bulbula\Services\AuditService;
use Bulbula\Services\CustomerAuthService;
use Bulbula\Services\TelegramInitDataVerifier;

/** Never trusts the surface flag, username or initDataUnsafe; only fresh signed launch data. */
final class TelegramAuthController
{
    public function __construct(
        private readonly TelegramInitDataVerifier $verifier = new TelegramInitDataVerifier(),
        private readonly CustomerTelegramLinkRepository $links = new CustomerTelegramLinkRepository(),
        private readonly CustomerRepository $customers = new CustomerRepository(),
        private readonly CustomerAuthService $auth = new CustomerAuthService(),
        private readonly AuditService $audit = new AuditService()
    ) {}

    /** Revoke from any signed-in browser if Telegram access is lost. */
    public function unlinkFromAccount(Request $request): Response
    {
        $customer = $this->auth->currentCustomer();
        if ($customer === null) return Response::redirect('/auth/login');
        if (!Csrf::verify((string)$request->input('_csrf', '')) || (string)$request->input('confirm', '') !== 'UNLINK') {
            Session::flash('error', 'Type UNLINK exactly to disconnect Telegram.');
            return Response::redirect('/account#security');
        }
        $id = (int)$customer['id'];
        if (!$this->links->unlinkForCustomer($id)) {
            Session::flash('error', 'There is no Telegram link for this account.');
            return Response::redirect('/account#security');
        }
        $this->audit->logCustomerAction($id, 'CUSTOMER_TELEGRAM_UNLINKED', 'customer', $id, ['all_sessions_revoked'=>true]);
        (new AccountSessionRepository())->revokeAll('CUSTOMER', $id);
        $this->auth->logout();
        Session::flash('success', 'Telegram was disconnected and all devices were signed out.');
        return Response::redirect('/auth/login');
    }

    public function handle(Request $request): Response
    {
        if (!Csrf::verify((string)$request->input('_csrf', ''))) return Response::json(['state'=>'invalid_session'], 403);
        if (!RateLimiter::allow('telegram-auth-session:' . session_id(), 60, 600)) return Response::json(['state'=>'rate_limited'], 429);
        $raw = $request->input('init_data', '');
        $id = is_string($raw) ? $this->verifier->verify($raw) : null;
        if ($id === null) return Response::json(['state'=>'invalid_launch'], 403);

        $current = $this->auth->currentCustomer();
        $currentId = $current !== null && ($current['account_status'] ?? '') === 'ACTIVE' ? (int)$current['id'] : null;
        $operation = (string)$request->input('operation', 'status');
        $linkedId = $this->links->customerIdFor($id);

        if ($operation === 'link') {
            if ($currentId === null || empty($current['google_sub_id']) || (string)$request->input('confirm', '') !== 'yes') return Response::json(['state'=>'confirmation_required'], 403);
            if (!$this->links->link($id, $currentId)) return Response::json(['state'=>'already_linked_elsewhere'], 409);
            $this->audit->logCustomerAction($currentId, 'CUSTOMER_TELEGRAM_LINKED', 'customer', $currentId, []);
            return Response::json(['state'=>'linked']);
        }
        if ($operation === 'unlink') {
            if ($currentId === null || (string)$request->input('confirm', '') !== 'yes' || !$this->links->unlink($id, $currentId)) return Response::json(['state'=>'not_linked_here'], 409);
            $this->audit->logCustomerAction($currentId, 'CUSTOMER_TELEGRAM_UNLINKED', 'customer', $currentId, ['all_sessions_revoked'=>true]);
            // Existing Telegram-origin sessions cannot be distinguished from Google sessions.
            // Revoke all of this Customer's sessions rather than leave a removed link effective.
            (new AccountSessionRepository())->revokeAll('CUSTOMER', $currentId);
            $this->auth->logout();
            return Response::json(['state'=>'unlinked_signed_out']);
        }
        if (!in_array($operation, ['status', 'switch', 'resume'], true)) return Response::json(['state'=>'invalid_operation'], 400);
        if ($operation === 'status' && $currentId === null && Session::get('telegram_auto_signin_paused', false) === true) {
            $pausedTarget = $linkedId === null ? null : $this->customers->findById($linkedId);
            return Response::json(['state'=>'paused', 'can_resume'=>($pausedTarget['account_status'] ?? '') === 'ACTIVE']);
        }
        if ($linkedId === null) return Response::json(['state'=>'unlinked', 'can_link'=>$currentId !== null && !empty($current['google_sub_id']), 'already_linked'=>$currentId !== null && $this->links->isLinked($currentId)]);
        $target = $this->customers->findById($linkedId);
        if ($target === null || ($target['account_status'] ?? '') !== 'ACTIVE') return Response::json(['state'=>'unavailable'], 403);
        if ($currentId === $linkedId) return Response::json(['state'=>'current']);
        if ($current !== null && $currentId === null) return Response::json(['state'=>'unavailable'], 403);
        // Explicit logout must persist in this browser session. A signed Telegram launch
        // remains valid, but it cannot silently undo the Customer's sign-out decision.
        if ($operation === 'status' && Session::get('telegram_auto_signin_paused', false) === true) return Response::json(['state'=>'paused']);
        if ($operation === 'resume' && ($currentId !== null || (string)$request->input('confirm', '') !== 'yes')) return Response::json(['state'=>'confirmation_required'], 403);
        if ($currentId !== null && ($operation !== 'switch' || (string)$request->input('confirm', '') !== 'yes')) {
            return Response::json(['state'=>'switch_confirmation', 'target_name'=>(string)$target['display_name'], 'target_email'=>(string)$target['email']]);
        }
        if ($currentId === null && $operation === 'switch') return Response::json(['state'=>'confirmation_required'], 403);
        if ($currentId !== null) $this->auth->logout();
        if (!$this->auth->signInExistingTelegramCustomer($linkedId, $id)) return Response::json(['state'=>'unavailable'], 403);
        Session::forget('telegram_auto_signin_paused');
        return Response::json(['state'=>'signed_in']);
    }
}
