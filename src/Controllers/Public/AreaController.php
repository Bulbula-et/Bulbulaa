<?php
declare(strict_types=1);

namespace Bulbula\Controllers\Public;

use Bulbula\Core\Request;
use Bulbula\Core\Response;
use Bulbula\Core\View;
use Bulbula\Repositories\AreaRepository;
use Bulbula\Repositories\TaxonomyRepository;
use Bulbula\Services\CustomerAuthService;
use Bulbula\Services\DiscoveryService;

/**
 * C-05 Location & Area Browsing Controller.
 */
final class AreaController
{
    public function __construct(
        private readonly AreaRepository $areaRepo = new AreaRepository(),
        private readonly TaxonomyRepository $taxonomyRepo = new TaxonomyRepository(),
        private readonly DiscoveryService $discoveryService = new DiscoveryService(),
        private readonly CustomerAuthService $authService = new CustomerAuthService()
    ) {}

    public function index(Request $request): Response
    {
        $customer = $this->authService->currentCustomer();

        $html = View::render('public/areas', [
            'pageTitle'       => 'Local Areas in Bole Bulbula — Bulbula.et Directory',
            'metaDescription' => 'Browse local businesses across Bole Bulbula, 93 Mazoria, St. Mary (Mariam), and Bulbula Condominium.',
            'canonicalUrl'    => '/areas',
            'request'         => $request,
            'customer'        => $customer,
            'areas'           => $this->areaRepo->listAll(true),
            'categories'      => $this->taxonomyRepo->getFullTree(true),
        ]);

        return Response::html($html);
    }

    public function show(Request $request): Response
    {
        $slug       = (string) $request->param('area_slug', '');
        $area       = $this->areaRepo->findBySlug($slug);
        if ($area === null) {
            return Response::html(View::renderStandaloneError(404, 'Area Not Found', 'The requested local area is not in the Bulbula.et catalogue.'), 404);
        }

        $customer   = $this->authService->currentCustomer();
        $customerId = $customer ? (int) $customer['id'] : null;
        $search     = $this->discoveryService->search('', null, $slug, false, $request->surface(), $customerId);

        $html = View::render('public/areas', [
            'pageTitle'       => 'Businesses in ' . $area['name_en'] . ' (' . $area['sub_city_en'] . ') — Bulbula.et',
            'metaDescription' => 'Explore verified businesses, opening hours, and directions in ' . $area['name_en'] . ', Addis Ababa.',
            'canonicalUrl'    => '/a/' . $area['slug'],
            'request'         => $request,
            'customer'        => $customer,
            'selectedArea'    => $area,
            'areaBranches'    => $search['organic_results'],
            'areas'           => $this->areaRepo->listAll(true),
            'categories'      => $this->taxonomyRepo->getFullTree(true),
        ]);

        return Response::html($html);
    }
}
