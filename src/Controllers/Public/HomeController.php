<?php
declare(strict_types=1);

namespace Bulbula\Controllers\Public;

use Bulbula\Core\Request;
use Bulbula\Core\Response;
use Bulbula\Core\View;
use Bulbula\Services\CustomerAuthService;
use Bulbula\Services\DiscoveryService;

/**
 * C-01 Homepage Controller (Search-first entry point + Curated Categories/Areas + Labelled Sponsored Slot).
 */
final class HomeController
{
    public function __construct(
        private readonly DiscoveryService $discoveryService = new DiscoveryService(),
        private readonly CustomerAuthService $authService = new CustomerAuthService()
    ) {}

    public function index(Request $request): Response
    {
        $customer   = $this->authService->currentCustomer();
        $customerId = $customer ? (int) $customer['id'] : null;
        $payload    = $this->discoveryService->getHomePayload($request->surface(), $customerId);

        $html = View::render('public/home', [
            'pageTitle'      => 'Bulbula.et — Verified Local Business Directory in Bole Bulbula, Addis Ababa',
            'metaDescription'=> 'Discover verified clinics, pharmacies, specialty coffee, supermarkets, and local services in Bole Bulbula, Addis Ababa.',
            'canonicalUrl'   => '/',
            'request'        => $request,
            'customer'       => $customer,
            'categories'     => $payload['categories'],
            'areas'          => $payload['areas'],
            'branches'       => $payload['branches'],
            'sponsoredHome'  => $payload['sponsored_home'],
        ]);

        return Response::html($html);
    }
}
