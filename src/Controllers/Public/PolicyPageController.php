<?php
declare(strict_types=1);

namespace Bulbula\Controllers\Public;

use Bulbula\Core\Config;
use Bulbula\Core\Request;
use Bulbula\Core\Response;
use Bulbula\Core\View;
use Bulbula\Repositories\AreaRepository;
use Bulbula\Repositories\BranchRepository;
use Bulbula\Repositories\TaxonomyRepository;
use Bulbula\Services\CustomerAuthService;

/**
 * C-18 Information & Policy Pages + C-37 Dynamic XML Sitemap Controller.
 * Explains how Bulbula works, how permissioned listings are collected, review rules, sponsorship boundaries, and privacy rights.
 */
final class PolicyPageController
{
    public function __construct(
        private readonly CustomerAuthService $authService = new CustomerAuthService(),
        private readonly BranchRepository $branchRepo = new BranchRepository(),
        private readonly TaxonomyRepository $taxonomyRepo = new TaxonomyRepository(),
        private readonly AreaRepository $areaRepo = new AreaRepository()
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

    /**
     * C-37 Indexable Destination Sitemap (`GET /sitemap.xml`).
     */
    public function sitemap(Request $request): Response
    {
        $baseUrl    = rtrim((string) Config::get('app.url', 'https://bulbula.et'), '/');
        $categories = $this->taxonomyRepo->getFullTree(true);
        $areas      = $this->areaRepo->listAll(true);
        $branches   = $this->branchRepo->listPublishedForHome(200);

        $paths = ['/', '/search', '/categories', '/areas', '/nearby', '/about'];
        foreach ($categories as $cat) {
            $paths[] = '/c/' . $cat['slug'];
            foreach ($cat['subcategories'] ?? [] as $sub) {
                $paths[] = '/c/' . $sub['slug'];
            }
        }
        foreach ($areas as $a) {
            $paths[] = '/a/' . $a['slug'];
            foreach ($categories as $cat) {
                $paths[] = '/a/' . $a['slug'] . '/' . $cat['slug'];
            }
        }
        foreach ($branches as $b) {
            $paths[] = '/b/' . $b['business_slug'] . '/' . $b['branch_slug'];
        }

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
        foreach (array_unique($paths) as $p) {
            $loc = htmlspecialchars($baseUrl . $p, ENT_XML1);
            $xml .= "  <url><loc>{$loc}</loc><changefreq>daily</changefreq></url>\n";
        }
        $xml .= "</urlset>\n";

        return new Response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8']);
    }

    private function renderPolicyPage(Request $request, string $slug): Response
    {
        $customer = $this->authService->currentCustomer();

        $titles = [
            'about'        => 'How Bulbula.et Works — Permissioned Local Discovery',
            'editorial'    => 'Editorial & Permissioned Listing Standards',
            'verification' => 'Field Verification & Quality Control Policy',
            'reviews'      => 'Review Participation & Pre-Publication Moderation Policy',
            'sponsorship'  => 'Commercial Integrity & Sponsorship Labelling Policy',
            'sponsored'    => 'Commercial Integrity & Sponsorship Labelling Policy',
            'privacy'      => 'Privacy, Data Minimisation & Account Rights Policy',
            'terms'        => 'Terms of Use & Community Guidelines',
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
