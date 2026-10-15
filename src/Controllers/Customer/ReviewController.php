<?php
declare(strict_types=1);

namespace Bulbula\Controllers\Customer;

use Bulbula\Core\Csrf;
use Bulbula\Core\Request;
use Bulbula\Core\Response;
use Bulbula\Core\Session;
use Bulbula\Core\View;
use Bulbula\Domain\Policies\ReviewPolicy;
use Bulbula\Repositories\ReviewRepository;
use Bulbula\Services\AuditService;
use Bulbula\Services\CustomerAuthService;
use Bulbula\Services\ReviewNotificationService;
use Bulbula\Services\ReviewAppealService;

/**
 * C-13 Read/Write Branch Reviews & C-35 Customer Review Management Controller.
 *
 * Enforces settled V1 Review Rules:
 * - Required integer rating 1..5; optional text.
 * - One active Review per Customer per Branch.
 * - Editable within 30 days (re-enters PENDING_MODERATION).
 * - Author can withdraw own Review so it stops being publicly visible.
 */
final class ReviewController
{
    public function __construct(
        private readonly CustomerAuthService $authService = new CustomerAuthService(),
        private readonly ReviewRepository $reviewRepo = new ReviewRepository(),
        private readonly AuditService $auditService = new AuditService(),
        private readonly ReviewNotificationService $notificationService = new ReviewNotificationService(),
        private readonly ReviewAppealService $appealService = new ReviewAppealService()
    ) {}

    public function index(Request $request): Response
    {
        $customer = $this->authService->currentCustomer();
        if ($customer === null) {
            return Response::redirect('/auth/login?return_to=%2Faccount%2Freviews');
        }

        $reviews = $this->reviewRepo->listByCustomer((int) $customer['id']);

        $html = View::render('customer/reviews', [
            'pageTitle'    => 'My Branch Reviews — Bulbula.et',
            'canonicalUrl' => '/account/reviews',
            'request'      => $request,
            'customer'     => $customer,
            'reviews'      => $reviews,
        ]);

        return Response::html($html);
    }

    public function submit(Request $request): Response
    {
        $returnTo = (string) $request->input('return_to', '/account/reviews');
        if (!str_starts_with($returnTo, '/')) {
            $returnTo = '/account/reviews';
        }

        $customer = $this->authService->currentCustomer();
        if ($customer === null) {
            Session::flash('error', 'Please sign in to submit a branch review.');
            return Response::redirect('/auth/login?return_to=' . rawurlencode($returnTo));
        }

        if (!Csrf::verify((string) $request->input('_csrf', ''))) {
            Session::flash('error', 'Security token expired.');
            return Response::redirect($returnTo);
        }
        if (empty($customer['email_verified_at'])) {
            Session::flash('error', 'Verify your email before submitting a review.');
            return Response::redirect('/account');
        }

        $branchId   = (int) $request->input('branch_id', 0);
        $customerId = (int) $customer['id'];
        $rawText    = (string) ($request->input('review_text') ?? $request->input('body', ''));

        if (!$this->reviewRepo->branchExists($branchId)) {
            Session::flash('error', 'The selected branch is unavailable. Please reopen the listing and try again.');
            return Response::redirect($returnTo);
        }

        $validation = ReviewPolicy::validateSubmission($request->input('rating'), $rawText);
        if (!$validation['valid']) {
            Session::flash('error', implode(' ', $validation['errors']));
            return Response::redirect($returnTo);
        }

        // Enforce 1 active review per Customer per Branch (C-13)
        $existingActive = $this->reviewRepo->findActiveCustomerReviewForBranch($branchId, $customerId);
        if ($existingActive !== null) {
            $editCheck = ReviewPolicy::canCustomerEdit($existingActive, $customerId);
            if ($editCheck['can_edit']) {
                $this->reviewRepo->updateAndReturnToModeration(
                    (int) $existingActive['id'],
                    $branchId,
                    $validation['rating'],
                    $validation['sanitized_text']
                );
                $reviewId = (int)$existingActive['id'];
                $this->auditService->logCustomerAction($customerId, 'REVIEW_EDITED_PENDING', 'review', $reviewId, ['branch_id'=>$branchId]);
                $this->notificationService->notify($reviewId, 'EDITED');
                Session::flash('success', 'Your existing review was updated and submitted for moderation.');
                return Response::redirect($returnTo);
            }

            Session::flash('error', 'You already have an active review for this branch, and the 30-day edit window has passed.');
            return Response::redirect($returnTo);
        }

        $reviewId = $this->reviewRepo->createPendingReview(
            $branchId,
            $customerId,
            $validation['rating'],
            $validation['sanitized_text']
        );

        $this->auditService->logCustomerAction(
            $customerId,
            'REVIEW_SUBMITTED_PENDING',
            'review',
            $reviewId,
            ['branch_id' => $branchId, 'rating' => $validation['rating']]
        );

        $this->notificationService->notify($reviewId, 'SUBMITTED');
        Session::flash('success', 'Thank you! Your review has been submitted and will appear once approved by Bulbula moderation.');
        return Response::redirect($returnTo);
    }

    public function edit(Request $request): Response
    {
        $customer = $this->authService->currentCustomer();
        if ($customer === null) {
            return Response::redirect('/auth/login');
        }

        if (!Csrf::verify((string) $request->input('_csrf', ''))) {
            Session::flash('error', 'Security token expired.');
            return Response::redirect('/account/reviews');
        }

        $reviewId = (int) $request->param('review_id', 0);
        $review   = $this->reviewRepo->findById($reviewId);
        if ($review === null) {
            Session::flash('error', 'Review not found.');
            return Response::redirect('/account/reviews');
        }

        $editCheck = ReviewPolicy::canCustomerEdit($review, (int) $customer['id']);
        if (!$editCheck['can_edit']) {
            Session::flash('error', (string) $editCheck['reason']);
            return Response::redirect('/account/reviews');
        }

        $validation = ReviewPolicy::validateSubmission($request->input('rating'), (string) $request->input('review_text', ''));
        if (!$validation['valid']) {
            Session::flash('error', implode(' ', $validation['errors']));
            return Response::redirect('/account/reviews');
        }

        $this->reviewRepo->updateAndReturnToModeration(
            $reviewId,
            (int) $review['branch_id'],
            $validation['rating'],
            $validation['sanitized_text']
        );

        $this->auditService->logCustomerAction((int)$customer['id'], 'REVIEW_EDITED_PENDING', 'review', $reviewId, ['branch_id'=>(int)$review['branch_id']]);
        $this->notificationService->notify($reviewId, 'EDITED');
        Session::flash('success', 'Your review was updated and returned to pending moderation.');
        return Response::redirect('/account/reviews');
    }

    public function appeal(Request $request): Response
    {
        $customer=$this->authService->currentCustomer();if($customer===null)return Response::redirect('/auth/login');
        if(!Csrf::verify((string)$request->input('_csrf',''))){Session::flash('error','Security token expired.');return Response::redirect('/account/reviews');}
        $result=$this->appealService->submit((int)$request->param('review_id',0),(int)$customer['id'],(string)$request->input('appeal_statement',''));
        Session::flash($result['ok']?'success':'error',$result['ok']?'Your appeal was recorded for Administrator review.':(string)$result['error']);
        return Response::redirect('/account/reviews');
    }

    public function withdraw(Request $request): Response
    {
        $customer = $this->authService->currentCustomer();
        if ($customer === null) {
            return Response::redirect('/auth/login');
        }

        if (!Csrf::verify((string) $request->input('_csrf', ''))) {
            Session::flash('error', 'Security token expired.');
            return Response::redirect('/account/reviews');
        }

        $reviewId = (int) $request->param('review_id', 0);
        $review   = $this->reviewRepo->findById($reviewId);

        if ($review !== null && (int) $review['customer_id'] === (int) $customer['id']) {
            $this->reviewRepo->withdrawByAuthor($reviewId, (int) $review['branch_id'], (int) $customer['id']);
            $this->auditService->logCustomerAction((int) $customer['id'], 'REVIEW_WITHDRAWN_BY_AUTHOR', 'review', $reviewId);
            Session::flash('success', 'Your review has been withdrawn and is no longer publicly visible.');
        }

        return Response::redirect('/account/reviews');
    }
}
