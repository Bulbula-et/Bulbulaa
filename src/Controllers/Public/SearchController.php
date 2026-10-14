<?php
declare(strict_types=1);

namespace Bulbula\Controllers\Public;

use Bulbula\Core\Request;
use Bulbula\Core\Response;
use Bulbula\Core\View;
use Bulbula\Repositories\BranchRepository;
use Bulbula\Services\CustomerAuthService;
use Bulbula\Services\DiscoveryService;

/**
 * C-02 Search & C-03 Search Autocomplete Controller.
 */
final class SearchController
{
    public function __construct(
        private readonly DiscoveryService $discoveryService = new DiscoveryService(),
        private readonly BranchRepository $branchRepo = new BranchRepository(),
        private readonly CustomerAuthService $authService = new CustomerAuthService()
    ) {}

    public function index(Request $request): Response
    {
        $customer     = $this->authService->currentCustomer();
        $customerId   = $customer ? (int) $customer['id'] : null;
        $q            = trim((string) $request->query('q', ''));
        $categorySlug = trim((string) $request->query('category', '')) ?: null;
        $areaSlug     = trim((string) $request->query('area', '')) ?: null;
        $verifiedOnly = (string) $request->query('verified', '') === '1';

        $payload = $this->discoveryService->search(
            $q,
            $categorySlug,
            $areaSlug,
            $verifiedOnly,
            $request->surface(),
            $customerId
        );

        $titleParts = ['Search Local Businesses'];
        if ($q !== '') {
            $titleParts[] = '"' . $q . '"';
        }
        if ($payload['selected_category']) {
            $titleParts[] = $payload['selected_category']['name_en'];
        }
        if ($payload['selected_area']) {
            $titleParts[] = 'in ' . $payload['selected_area']['name_en'];
        }

        $html = View::render('public/search', [
            'pageTitle'        => implode(' — ', $titleParts) . ' | Bulbula.et',
            'metaDescription'  => 'Search verified local business branches, categories, and neighborhood areas in Bole Bulbula, Addis Ababa.',
            'canonicalUrl'     => '/search',
            'request'          => $request,
            'customer'         => $customer,
            'query'            => $payload['query'],
            'selectedCategory' => $payload['selected_category'],
            'selectedArea'     => $payload['selected_area'],
            'verifiedOnly'     => $payload['verified_only'],
            'organicResults'   => $payload['organic_results'],
            'sponsoredResults' => $payload['sponsored_results'],
            'categories'       => $payload['categories'],
            'areas'            => $payload['areas'],
        ]);

        return Response::html($html);
    }

    /**
     * C-03 Fast JSON Autocomplete endpoint (`GET /api/v1/autocomplete?q=...`).
     */
    public function autocomplete(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return Response::json(['query' => '', 'suggestions' => []]);
        }

        $suggestions = $this->branchRepo->autocompleteSuggestions($q, 8);
        return Response::json([
            'query'       => $q,
            'suggestions' => $suggestions,
        ]);
    }
}
