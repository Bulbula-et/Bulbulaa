<?php
declare(strict_types=1);

/**
 * Bulbula.et V1 — Declarative Route Registry
 * Maps all 40 V1 capabilities (C-01 to C-40) across Public, Customer, and Staff surfaces.
 * Loaded dynamically by Router so changes never require a server restart.
 */
return [
    // =========================================================================
    // 1. PUBLIC DISCOVERY ROUTES (C-01 to C-18, C-37 SEO)
    // Shared between Mobile-first Web & Telegram Mini App
    // =========================================================================
    ['GET',  '/',                                    ['Public\HomeController', 'index']],               // C-01 Homepage
    ['GET',  '/search',                              ['Public\SearchController', 'index']],             // C-02 Search
    ['GET',  '/api/v1/autocomplete',                 ['Public\SearchController', 'autocomplete']],      // C-03 Search autocomplete
    ['GET',  '/categories',                          ['Public\CategoryController', 'index']],           // C-04 Category catalogue
    ['GET',  '/c/{category_slug}',                   ['Public\CategoryController', 'show']],            // C-04 Category / Subcategory page
    ['GET',  '/areas',                               ['Public\AreaController', 'index']],               // C-05 Location & area catalogue
    ['GET',  '/a/{area_slug}',                       ['Public\AreaController', 'show']],                // C-05 Area page
    ['GET',  '/a/{area_slug}/{category_slug}',       ['Public\CategoryController', 'categoryByArea']],  // C-06 Category-by-area destination
    ['GET',  '/nearby',                              ['Public\NearbyController', 'index']],             // C-07 Nearby & distance discovery
    ['GET',  '/b/{business_slug}',                   ['Public\BranchProfileController', 'showBusiness']], // C-08 Business profile (primary branch)
    ['GET',  '/b/{business_slug}/{branch_slug}',     ['Public\BranchProfileController', 'showBranch']], // C-08..C-13 Specific Branch profile
    ['POST', '/b/{business_slug}/{branch_slug}/act', ['Public\BranchProfileController', 'recordAction']], // C-11, C-38 Server-side contact/share metric
    ['POST', '/reports/submit',                      ['Public\ReportController', 'submit']],            // C-15 Report problem / suggest correction
    ['GET',  '/about',                               ['Public\PolicyPageController', 'about']],         // C-18 How Bulbula works
    ['GET',  '/policies/{policy_slug}',              ['Public\PolicyPageController', 'show']],          // C-18 Review, Sponsorship, Privacy policies
    ['GET',  '/sitemap.xml',                         ['Public\PolicyPageController', 'sitemap']],       // C-37 Dynamic XML Sitemap for SEO

    // =========================================================================
    // 2. CUSTOMER ACCOUNT ROUTES (C-13, C-14, C-30 to C-36)
    // Password-free access: Google Auth + Email OTP
    // =========================================================================
    ['GET',  '/auth/login',                          ['Customer\AuthController', 'showLogin']],         // C-30, C-31
    ['POST', '/auth/otp/request',                    ['Customer\AuthController', 'requestEmailOtp']],   // C-31 Request Email OTP
    ['POST', '/auth/otp/verify',                     ['Customer\AuthController', 'verifyEmailOtp']],    // C-31 Verify Email OTP
    ['POST', '/auth/google/callback',                ['Customer\AuthController', 'googleCallback']],    // C-30 Verify Google ID Token
    ['POST', '/auth/logout',                         ['Customer\AuthController', 'logout']],
    ['GET',  '/account',                             ['Customer\ProfileController', 'index']],          // C-33 Customer minimal profile
    ['POST', '/account/privacy-request',             ['Customer\ProfileController', 'requestDeletion']],// C-36 Account deletion & privacy rights
    ['GET',  '/account/saved',                       ['Customer\SaveController', 'index']],             // C-34 Saved businesses list
    ['POST', '/account/saved/toggle',                ['Customer\SaveController', 'toggle']],            // C-14, C-34 Save / unsave business
    ['GET',  '/account/reviews',                     ['Customer\ReviewController', 'index']],           // C-35 Customer review management
    ['POST', '/account/reviews/submit',              ['Customer\ReviewController', 'submit']],          // C-13 Write review (1-5 int, pre-mod)
    ['POST', '/account/reviews/{review_id}/edit',    ['Customer\ReviewController', 'edit']],            // C-13, C-35 Edit within 30 days
    ['POST', '/account/reviews/{review_id}/withdraw',['Customer\ReviewController', 'withdraw']],        // C-35 Withdraw own review

    // =========================================================================
    // 3. STAFF INTERNAL OPERATIONS CONSOLE (C-19 to C-29, Gate D-45)
    // Web-only internal console (Strong auth + RBAC + Audit Logs)
    // =========================================================================
    ['GET',  '/staff',                               ['Staff\DashboardController', 'index']],           // Console overview & pilot metrics
    ['POST', '/staff/system/flush-cache',            ['Staff\DashboardController', 'flushCache']],      // Zero-restart cache & OPcache flush
    ['POST', '/staff/gates/toggle',                  ['Staff\DashboardController', 'toggleGate']],      // D-31..D-45 Readiness Gate toggle
    ['GET',  '/staff/listings',                      ['Staff\ListingController', 'index']],             // C-19, C-20 Business & Branch directory
    ['POST', '/staff/listings/create',               ['Staff\ListingController', 'create']],            // C-19 Create prospect/draft + Permission
    ['POST', '/staff/listings/{branch_id}/update',   ['Staff\ListingController', 'update']],            // C-20 Edit Business/Branch attributes
    ['POST', '/staff/listings/{branch_id}/media',    ['Staff\ListingController', 'uploadMedia']],       // C-22 Permitted media management
    ['GET',  '/staff/verification',                  ['Staff\VerificationController', 'index']],        // C-21 Verification & QC queue
    ['POST', '/staff/verification/{branch_id}/qc',   ['Staff\VerificationController', 'recordQc']],     // C-21 Pass/Fail QC + Publish gate
    ['GET',  '/staff/taxonomy',                      ['Staff\TaxonomyController', 'index']],            // C-23 2-level Category & Alias manager
    ['POST', '/staff/taxonomy/save',                 ['Staff\TaxonomyController', 'save']],             // C-23 Reference-data taxonomy update
    ['GET',  '/staff/locations',                     ['Staff\LocationController', 'index']],            // C-24 Area & Sub-city manager
    ['POST', '/staff/locations/save',                ['Staff\LocationController', 'save']],             // C-24 Area update
    ['GET',  '/staff/moderation',                    ['Staff\ModerationController', 'index']],          // C-25 Pre-publication review queue
    ['POST', '/staff/moderation/{review_id}/decide', ['Staff\ModerationController', 'decide']],         // C-25 Approve / Reject / Appeal
    ['GET',  '/staff/reports',                       ['Staff\ReportQueueController', 'index']],         // C-26 Public report triage queue
    ['POST', '/staff/reports/{report_id}/resolve',   ['Staff\ReportQueueController', 'resolve']],       // C-26 Resolve / Escalate report
    ['GET',  '/staff/campaigns',                     ['Staff\CampaignController', 'index']],            // C-27 Staff-managed sponsorships
    ['POST', '/staff/campaigns/create',              ['Staff\CampaignController', 'create']],           // C-27 Operator records campaign
    ['POST', '/staff/campaigns/{id}/approve',        ['Staff\CampaignController', 'approve']],          // C-27 Admin approves (blocked by D-39 if off)
    ['GET',  '/staff/analytics',                     ['Staff\AnalyticsController', 'index']],           // C-28 Operational & zero-result analytics
    ['GET',  '/staff/audit-logs',                    ['Staff\AuditLogController', 'index']],            // C-29 Admin-only append-only audit trail
];
