<?php
declare(strict_types=1);

namespace Bulbula\Controllers\Public;

use Bulbula\Core\Request;
use Bulbula\Core\Response;
use Bulbula\Core\View;
use Bulbula\Services\AnalyticsService;
use Bulbula\Services\CustomerAuthService;
use Bulbula\Services\DiscoveryService;

/**
 * C-08 to C-17 Business & Branch Profile Controller.
 */
final class BranchProfileController
{
    public function __construct(
        private readonly DiscoveryService $discoveryService = new DiscoveryService(),
        private readonly CustomerAuthService $authService = new CustomerAuthService(),
        private readonly AnalyticsService $analyticsService = new AnalyticsService()
    ) {}

    public function showBusiness(Request $request): Response
    {
        $businessSlug = (string) $request->param('business_slug', '');
        return $this->renderProfile($request, $businessSlug, null);
    }

    public function showBranch(Request $request): Response
    {
        $businessSlug = (string) $request->param('business_slug', '');
        $branchSlug   = (string) $request->param('branch_slug', '');
        return $this->renderProfile($request, $businessSlug, $branchSlug);
    }

    /**
     * C-11, C-38 Server-side Contact / Share / Sponsored Click Event Recorder.
     */
    public function recordAction(Request $request): Response
    {
        $businessSlug = (string) $request->param('business_slug', '');
        $branchSlug   = (string) $request->param('branch_slug', '');
        $actionType   = (string) $request->input('action_type', '');
        $campaignId   = $request->input('campaign_id') ? (int) $request->input('campaign_id') : null;

        $payload = $this->discoveryService->getBranchProfilePayload($businessSlug, $branchSlug, $request->surface());
        if ($payload !== null) {
            $this->analyticsService->recordBranchContactOrShare(
                (int) $payload['branch']['branch_id'],
                $request->surface(),
                $actionType,
                $campaignId
            );
        }

        return Response::json(['ok' => true]);
    }

    private function renderProfile(Request $request, string $businessSlug, ?string $branchSlug): Response
    {
        $customer   = $this->authService->currentCustomer();
        $customerId = $customer ? (int) $customer['id'] : null;

        $payload = $this->discoveryService->getBranchProfilePayload(
            $businessSlug,
            $branchSlug,
            $request->surface(),
            $customerId
        );

        if ($payload === null) {
            return Response::html(
                View::renderStandaloneError(404, 'Branch Listing Not Found', 'This business or branch listing is not currently published.'),
                404
            );
        }

        $br = $payload['branch'];
        $html = View::render('public/branch-profile', [
            'pageTitle'            => $br['branch_name_en'] . ' (' . $br['area_name_en'] . ') — Bulbula.et',
            'metaDescription'      => ($br['brand_summary_en'] ?? ($br['branch_name_en'] . ' in ' . $br['area_name_en'] . ', Addis Ababa.')),
            'canonicalUrl'         => $br['canonical_url'],
            'request'              => $request,
            'customer'             => $customer,
            'branch'               => $br,
            'reviews'              => $payload['reviews'],
            'customerActiveReview' => $payload['customer_active_review'],
        ]);

        return Response::html($html);
    }
}
