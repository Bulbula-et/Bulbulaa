<?php
declare(strict_types=1);

namespace Bulbula\Services;

use Bulbula\Domain\Enums\CampaignStatus;
use Bulbula\Domain\ValueObjects\Coordinates;
use Bulbula\Domain\ValueObjects\OpeningHours;
use Bulbula\Repositories\AnalyticsRepository;
use Bulbula\Repositories\AreaRepository;
use Bulbula\Repositories\BranchRepository;
use Bulbula\Repositories\CustomerRepository;
use Bulbula\Repositories\GateRepository;
use Bulbula\Repositories\ReviewRepository;
use Bulbula\Repositories\SponsorshipRepository;
use Bulbula\Repositories\TaxonomyRepository;

/**
 * Public Discovery Service (C-01 to C-18, C-37, C-38).
 * Orchestrates repositories,OpeningHours evaluation, Coordinates handoff,
 * Sponsored slot separation, and first-party server-side analytics.
 */
final class DiscoveryService
{
    public function __construct(
        private readonly TaxonomyRepository $taxonomyRepo = new TaxonomyRepository(),
        private readonly AreaRepository $areaRepo = new AreaRepository(),
        private readonly BranchRepository $branchRepo = new BranchRepository(),
        private readonly ReviewRepository $reviewRepo = new ReviewRepository(),
        private readonly SponsorshipRepository $sponsorshipRepo = new SponsorshipRepository(),
        private readonly GateRepository $gateRepo = new GateRepository(),
        private readonly AnalyticsRepository $analyticsRepo = new AnalyticsRepository(),
        private readonly CustomerRepository $customerRepo = new CustomerRepository()
    ) {}

    /**
     * C-01 Homepage payload: Taxonomy tree, Launch Areas, Verified Branches, and separated Sponsored Spotlight.
     */
    public function getHomePayload(string $surface, ?int $customerId = null): array
    {
        $categories = $this->taxonomyRepo->getFullTree(true);
        $areas      = $this->areaRepo->listAll(true);
        $branches   = $this->enrichBranches($this->branchRepo->listPublishedForHome(9), $customerId);

        $sponsoredHome = [];
        if ($this->gateRepo->isEnabled('D_39_PAID_CAMPAIGNS_ENABLED', true)) {
            $rawSponsored = $this->sponsorshipRepo->findActivePlacements(CampaignStatus::FORMAT_HOMEPAGE_PROMOTION, null, null, 2);
            foreach ($rawSponsored as $sp) {
                $this->sponsorshipRepo->incrementMetric((int) $sp['campaign_id'], $surface, 'IMPRESSION');
            }
            $sponsoredHome = $this->enrichBranches($rawSponsored, $customerId);
        }

        return [
            'categories'     => $categories,
            'areas'          => $areas,
            'branches'       => $branches,
            'sponsored_home' => $sponsoredHome,
        ];
    }

    /**
     * C-02 Search payload with separated Sponsored Search Placement (C-16) and server-side query logging (C-28, C-38).
     */
    public function search(
        string $rawQuery,
        ?string $categorySlug,
        ?string $areaSlug,
        bool $verifiedOnly,
        string $surface,
        ?int $customerId = null
    ): array {
        $selectedCategory = $categorySlug ? $this->taxonomyRepo->findBySlug($categorySlug) : null;
        $selectedArea     = $areaSlug ? $this->areaRepo->findBySlug($areaSlug) : null;

        $categoryId = $selectedCategory ? (int) $selectedCategory['id'] : null;
        $areaId     = $selectedArea ? (int) $selectedArea['id'] : null;

        $organicRows = $this->branchRepo->searchOrganic($rawQuery, $categoryId, $areaId, $verifiedOnly, 30);
        $organic     = $this->enrichBranches($organicRows, $customerId);

        // Log search query server-side (C-28, C-38: tracks zero-result queries for pilot ops)
        $normQuery = mb_strtolower(trim($rawQuery), 'UTF-8');
        if ($normQuery !== '' || $categoryId !== null || $areaId !== null) {
            $this->analyticsRepo->logSearchQuery(
                $surface,
                $normQuery !== '' ? $normQuery : '[filter_only]',
                $areaId,
                $categoryId,
                count($organic)
            );
        }

        // C-16 Sponsored Search Placement (strictly separated from organic results)
        $sponsoredSearch = [];
        if ($this->gateRepo->isEnabled('D_39_PAID_CAMPAIGNS_ENABLED', true)) {
            $rawSponsored = $this->sponsorshipRepo->findActivePlacements(
                CampaignStatus::FORMAT_SEARCH_PLACEMENT,
                $categoryId,
                $areaId,
                2
            );
            foreach ($rawSponsored as $sp) {
                $this->sponsorshipRepo->incrementMetric((int) $sp['campaign_id'], $surface, 'IMPRESSION');
            }
            $sponsoredSearch = $this->enrichBranches($rawSponsored, $customerId);
        }

        return [
            'query'             => trim($rawQuery),
            'selected_category' => $selectedCategory,
            'selected_area'     => $selectedArea,
            'verified_only'     => $verifiedOnly,
            'organic_results'   => $organic,
            'sponsored_results' => $sponsoredSearch,
            'categories'        => $this->taxonomyRepo->getFullTree(true),
            'areas'             => $this->areaRepo->listAll(true),
        ];
    }

    /**
     * C-04 & C-06 Category / Subcategory / Category-by-Area page payload.
     */
    public function getCategoryDestination(
        string $categorySlug,
        ?string $areaSlug,
        string $surface,
        ?int $customerId = null
    ): ?array {
        $category = $this->taxonomyRepo->findBySlug($categorySlug);
        if ($category === null) {
            return null;
        }

        $area = null;
        if ($areaSlug !== null) {
            $area = $this->areaRepo->findBySlug($areaSlug);
            if ($area === null) {
                return null;
            }
        }

        $categoryId = (int) $category['id'];
        $areaId     = $area ? (int) $area['id'] : null;

        $organic = $this->enrichBranches(
            $this->branchRepo->searchOrganic('', $categoryId, $areaId, false, 40),
            $customerId
        );

        $sponsoredCategory = [];
        if ($this->gateRepo->isEnabled('D_39_PAID_CAMPAIGNS_ENABLED', true)) {
            $rawSponsored = $this->sponsorshipRepo->findActivePlacements(
                CampaignStatus::FORMAT_CATEGORY_SPONSORSHIP,
                $categoryId,
                $areaId,
                2
            );
            foreach ($rawSponsored as $sp) {
                $this->sponsorshipRepo->incrementMetric((int) $sp['campaign_id'], $surface, 'IMPRESSION');
            }
            $sponsoredCategory = $this->enrichBranches($rawSponsored, $customerId);
        }

        return [
            'category'          => $category,
            'area'              => $area,
            'organic_results'   => $organic,
            'sponsored_results' => $sponsoredCategory,
            'all_areas'         => $this->areaRepo->listAll(true),
            'all_categories'    => $this->taxonomyRepo->getFullTree(true),
        ];
    }

    /**
     * C-07 Nearby & Distance Discovery with Area-Browsing Fallback when location access is denied/omitted.
     */
    public function getNearbyPayload(?float $lat, ?float $lng, int $radiusMeters = 5000, ?int $customerId = null): array
    {
        $hasCoordinates = ($lat !== null && $lng !== null && $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180);
        $nearbyBranches = [];

        if ($hasCoordinates) {
            $coords = new Coordinates($lat, $lng);
            $nearbyBranches = $this->enrichBranches(
                $this->branchRepo->findNearby($coords, $radiusMeters, 25),
                $customerId
            );
        }

        return [
            'has_coordinates' => $hasCoordinates,
            'latitude'        => $lat,
            'longitude'       => $lng,
            'radius_meters'   => $radiusMeters,
            'nearby_branches' => $nearbyBranches,
            'fallback_areas'  => $this->areaRepo->listAll(true),
            'default_branches'=> !$hasCoordinates ? $this->enrichBranches($this->branchRepo->listPublishedForHome(6), $customerId) : [],
        ];
    }

    /**
     * C-08..C-17 Full Branch Profile payload + server-side profile view metric (C-38).
     */
    public function getBranchProfilePayload(
        string $businessSlug,
        ?string $branchSlug,
        string $surface,
        ?int $customerId = null
    ): ?array {
        $branch = $this->branchRepo->findPublicBranchProfile($businessSlug, $branchSlug);
        if ($branch === null) {
            return null;
        }

        $branchId = (int) $branch['branch_id'];
        $this->analyticsRepo->incrementBranchAction($branchId, $surface, 'profile_views');

        $enriched = $this->enrichSingleBranch($branch, $customerId);
        $reviews  = $this->reviewRepo->listPublishedForBranch($branchId, 30);

        $customerActiveReview = null;
        if ($customerId !== null) {
            $customerActiveReview = $this->reviewRepo->findActiveCustomerReviewForBranch($branchId, $customerId);
        }

        return [
            'branch'                 => $enriched,
            'reviews'                => $reviews,
            'customer_active_review' => $customerActiveReview,
        ];
    }

    /**
     * Enriches branch records with OpeningHours live state (C-09), Google Maps URLs (C-10),
     * decoded services list (D-44), and Customer saved state (C-14).
     */
    public function enrichBranches(array $rows, ?int $customerId = null): array
    {
        return array_map(fn(array $row): array => $this->enrichSingleBranch($row, $customerId), $rows);
    }

    public function enrichSingleBranch(array $row, ?int $customerId = null): array
    {
        $hoursVo = OpeningHours::fromBranchRow($row);
        $row['open_now']       = $hoursVo->evaluateNow();
        $row['weekly_hours']   = $hoursVo->formattedWeeklyTable();

        if (!empty($row['latitude']) && !empty($row['longitude'])) {
            $coords = new Coordinates((float) $row['latitude'], (float) $row['longitude']);
            $row['directions_url'] = $coords->googleMapsDirectionsUrl();
        } else {
            $row['directions_url'] = null;
        }

        $services = [];
        if (!empty($row['services_products_json'])) {
            $decoded = is_string($row['services_products_json'])
                ? json_decode($row['services_products_json'], true)
                : $row['services_products_json'];
            if (is_array($decoded)) {
                $services = $decoded;
            }
        }
        $row['services_list'] = $services;

        $row['canonical_url'] = '/b/' . $row['business_slug'] . '/' . $row['branch_slug'];
        $row['is_saved']      = ($customerId !== null && !empty($row['business_id']))
            ? $this->customerRepo->isBusinessSavedByCustomer($customerId, (int) $row['business_id'])
            : false;

        return $row;
    }
}
