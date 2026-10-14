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
 * C-04 Category & Subcategory Browsing + C-06 Category-by-Area Pages Controller.
 */
final class CategoryController
{
    public function __construct(
        private readonly TaxonomyRepository $taxonomyRepo = new TaxonomyRepository(),
        private readonly AreaRepository $areaRepo = new AreaRepository(),
        private readonly DiscoveryService $discoveryService = new DiscoveryService(),
        private readonly CustomerAuthService $authService = new CustomerAuthService()
    ) {}

    public function index(Request $request): Response
    {
        $customer = $this->authService->currentCustomer();

        $html = View::render('public/categories', [
            'pageTitle'       => 'Browse Categories & Subcategories — Bulbula.et Directory',
            'metaDescription' => 'Explore Bole Bulbula businesses by controlled 2-level categories and subcategories in English and Amharic.',
            'canonicalUrl'    => '/categories',
            'request'         => $request,
            'customer'        => $customer,
            'categories'      => $this->taxonomyRepo->getFullTree(true),
            'areas'           => $this->areaRepo->listAll(true),
        ]);

        return Response::html($html);
    }

    public function show(Request $request): Response
    {
        $slug       = (string) $request->param('category_slug', '');
        $customer   = $this->authService->currentCustomer();
        $customerId = $customer ? (int) $customer['id'] : null;

        $payload = $this->discoveryService->getCategoryDestination($slug, null, $request->surface(), $customerId);
        if ($payload === null) {
            return Response::html(View::renderStandaloneError(404, 'Category Not Found', 'The requested category is not in the Bulbula.et catalogue.'), 404);
        }

        $cat = $payload['category'];
        $html = View::render('public/category-area', [
            'pageTitle'        => $cat['name_en'] . ' in Bole Bulbula — Bulbula.et Directory',
            'metaDescription'  => ($cat['description_en'] ?? ('Verified ' . $cat['name_en'] . ' branches in Bole Bulbula, Addis Ababa.')),
            'canonicalUrl'     => '/c/' . $cat['slug'],
            'request'          => $request,
            'customer'         => $customer,
            'category'         => $cat,
            'area'             => null,
            'organicResults'   => $payload['organic_results'],
            'sponsoredResults' => $payload['sponsored_results'],
            'allAreas'         => $payload['all_areas'],
            'allCategories'    => $payload['all_categories'],
        ]);

        return Response::html($html);
    }

    /**
     * C-06 Dedicated Category-by-Area indexable destination (`/a/{area_slug}/{category_slug}`).
     */
    public function categoryByArea(Request $request): Response
    {
        $areaSlug     = (string) $request->param('area_slug', '');
        $categorySlug = (string) $request->param('category_slug', '');
        $customer     = $this->authService->currentCustomer();
        $customerId   = $customer ? (int) $customer['id'] : null;

        $payload = $this->discoveryService->getCategoryDestination($categorySlug, $areaSlug, $request->surface(), $customerId);
        if ($payload === null || $payload['area'] === null) {
            return Response::html(View::renderStandaloneError(404, 'Category or Area Not Found', 'The requested Category-by-Area destination does not exist.'), 404);
        }

        $cat  = $payload['category'];
        $area = $payload['area'];

        $html = View::render('public/category-area', [
            'pageTitle'        => $cat['name_en'] . ' in ' . $area['name_en'] . ' (' . $area['sub_city_en'] . ') — Bulbula.et',
            'metaDescription'  => 'Discover verified ' . $cat['name_en'] . ' branches operating in ' . $area['name_en'] . ', Addis Ababa.',
            'canonicalUrl'     => '/a/' . $area['slug'] . '/' . $cat['slug'],
            'request'          => $request,
            'customer'         => $customer,
            'category'         => $cat,
            'area'             => $area,
            'organicResults'   => $payload['organic_results'],
            'sponsoredResults' => $payload['sponsored_results'],
            'allAreas'         => $payload['all_areas'],
            'allCategories'    => $payload['all_categories'],
        ]);

        return Response::html($html);
    }
}
