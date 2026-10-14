<?php
declare(strict_types=1);

namespace Bulbula\Controllers\Public;

use Bulbula\Core\Request;
use Bulbula\Core\Response;
use Bulbula\Core\View;
use Bulbula\Services\CustomerAuthService;

/**
 * C-18 Information & Policy Pages Controller.
 * Explains how Bulbula works, how permissioned listings are collected, review rules, sponsorship boundaries, and privacy rights.
 */
final class PolicyPageController
{
    public function __construct(
        private readonly CustomerAuthService $authService = new CustomerAuthService()
    ) {}

    public function about(Request $request): Response
    {
        return $this->renderPolicyPage($request, 'about');
    }

    public function show(Request $request): Response
    {
        $slug = (string) $request->param('policy_slug', 'about');
        return $this->renderPolicyPage($request, $slug);
    }

    private function renderPolicyPage(Request $request, string $slug): Response
    {
        $customer = $this->authService->currentCustomer();

        $titles = [
            'about'       => 'How Bulbula.et Works — Permissioned Local Discovery',
            'reviews'     => 'Review Participation & Pre-Publication Moderation Policy (C-13)',
            'sponsorship' => 'Commercial Integrity & Sponsorship Labelling Policy (C-16)',
            'privacy'     => 'Privacy, Data Minimisation & Account Rights Policy (C-36, C-40)',
        ];

        $selectedSlug = isset($titles[$slug]) ? $slug : 'about';

        $html = View::render('public/policy', [
            'pageTitle'       => $titles[$selectedSlug] . ' | Bulbula.et',
            'metaDescription' => 'Learn how Bulbula Staff collect, verify, and maintain permissioned local business listings in Bole Bulbula, Addis Ababa.',
            'canonicalUrl'    => $selectedSlug === 'about' ? '/about' : '/policies/' . $selectedSlug,
            'request'         => $request,
            'customer'        => $customer,
            'policySlug'      => $selectedSlug,
        ]);

        return Response::html($html);
    }
}
