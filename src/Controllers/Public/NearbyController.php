<?php
declare(strict_types=1);

namespace Bulbula\Controllers\Public;

use Bulbula\Core\Request;
use Bulbula\Core\Response;
use Bulbula\Core\View;
use Bulbula\Services\CustomerAuthService;
use Bulbula\Services\DiscoveryService;

/**
 * C-07 Nearby & Distance Discovery Controller.
 * Answers "What is near me?" without making location permission mandatory for the product.
 */
final class NearbyController
{
    public function __construct(
        private readonly DiscoveryService $discoveryService = new DiscoveryService(),
        private readonly CustomerAuthService $authService = new CustomerAuthService()
    ) {}

    public function index(Request $request): Response
    {
        $customer   = $this->authService->currentCustomer();
        $customerId = $customer ? (int) $customer['id'] : null;

        $rawLat = $request->query('lat');
        $rawLng = $request->query('lng');
        $radius = (int) $request->query('radius', 3500);

        $lat = is_numeric($rawLat) ? (float) $rawLat : null;
        $lng = is_numeric($rawLng) ? (float) $rawLng : null;

        $payload = $this->discoveryService->getNearbyPayload($lat, $lng, max(500, min(15000, $radius)), $customerId);

        $html = View::render('public/nearby', [
            'pageTitle'       => 'Nearby Businesses & Distance Discovery — Bulbula.et',
            'metaDescription' => 'Find verified branches near your location in Bole Bulbula or browse by neighborhood area without sharing GPS.',
            'canonicalUrl'    => '/nearby',
            'request'         => $request,
            'customer'        => $customer,
            'payload'         => $payload,
        ]);

        return Response::html($html);
    }
}
