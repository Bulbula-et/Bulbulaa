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
    ['GET',  '/robots.txt',                           ['Public\PolicyPageController', 'robots']],        // C-37 Origin-aware crawl rules

    // =========================================================================
    // 2. CUSTOMER ACCOUNT ROUTES (C-13, C-14, C-30 to C-36)
    // Password-free access: Google Auth + Email OTP
    // =========================================================================
    ['GET',  '/auth/login',                          ['Customer\AuthController', 'showLogin']],
    ['POST', '/auth/password/login',                 ['Customer\AuthController', 'passwordLogin']],
    ['GET',  '/auth/register',                       ['Customer\AuthController', 'showRegister']],
    ['POST', '/auth/register',                       ['Customer\AuthController', 'register']],
    ['GET',  '/auth/restore-account',                ['Customer\AuthController', 'showRestoreAccount']],
    ['POST', '/auth/restore-account',                ['Customer\AuthController', 'restoreAccount']],
    ['POST', '/auth/restore-account/delete',         ['Customer\AuthController', 'permanentlyDeleteAccount']],
    ['GET',  '/auth/forgot-password',                ['Customer\AuthController', 'showForgotPassword']],
    ['POST', '/auth/forgot-password',                ['Customer\AuthController', 'requestPasswordReset']],
    ['POST', '/auth/forgot-password/verify',         ['Customer\AuthController', 'verifyPasswordReset']],
    ['GET',  '/auth/reset-password',                 ['Customer\AuthController', 'showResetPassword']],
    ['POST', '/auth/reset-password',                 ['Customer\AuthController', 'resetPassword']],
    ['POST', '/auth/otp/request',                    ['Customer\AuthController', 'requestEmailOtp']],   // C-31 Request Email OTP
    ['POST', '/auth/otp/verify',                     ['Customer\AuthController', 'verifyEmailOtp']],    // C-31 Verify Email OTP
    ['POST', '/auth/google/callback',                ['Customer\AuthController', 'googleCallback']],    // C-30 Verify Google ID Token
    ['POST', '/auth/logout',                         ['Customer\AuthController', 'logout']],
    ['GET',  '/account',                             ['Customer\ProfileController', 'index']],
    ['POST', '/account/profile',                     ['Customer\ProfileController', 'updateProfile']],
    ['POST', '/account/security-flow/cancel',        ['Customer\ProfileController', 'cancelSecurityFlow']],
    ['POST', '/account/email/change',                ['Customer\ProfileController', 'beginEmailChange']],
    ['POST', '/account/email/change/current',        ['Customer\ProfileController', 'verifyCurrentEmailChange']],
    ['POST', '/account/email/change/complete',       ['Customer\ProfileController', 'completeEmailChange']],
    ['POST', '/account/sessions/others/revoke',      ['Customer\ProfileController', 'revokeOtherSessions']],
    ['POST', '/account/sessions/{id}/revoke',        ['Customer\ProfileController', 'revokeSession']],
    ['POST', '/account/email/verification/request',  ['Customer\ProfileController', 'requestEmailVerification']],
    ['POST', '/account/email/verification/verify',   ['Customer\ProfileController', 'verifyEmail']],
    ['POST', '/account/password',                    ['Customer\ProfileController', 'changePassword']],
    ['POST', '/account/privacy-request',             ['Customer\ProfileController', 'beginPrivacyConfirmation']],// C-36 Email-confirmed privacy rights
    ['POST', '/account/privacy-request/verify',      ['Customer\ProfileController', 'verifyPrivacyConfirmation']],
    ['GET',  '/account/saved',                       ['Customer\SaveController', 'index']],             // C-34 Saved businesses list
    ['POST', '/account/saved/toggle',                ['Customer\SaveController', 'toggle']],            // C-14, C-34 Save / unsave business
    ['GET',  '/account/reviews',                     ['Customer\ReviewController', 'index']],           // C-35 Customer review management
    ['POST', '/account/reviews/submit',              ['Customer\ReviewController', 'submit']],          // C-13 Write review (1-5 int, pre-mod)
    ['POST', '/account/reviews/{review_id}/edit',    ['Customer\ReviewController', 'edit']],            // C-13, C-35 Edit within 30 days
    ['POST', '/account/reviews/{review_id}/withdraw',['Customer\ReviewController', 'withdraw']],        // C-35 Withdraw own review
    ['POST', '/account/reviews/{review_id}/appeal',  ['Customer\ReviewController', 'appeal']],          // C-25 Customer moderation appeal

    // =========================================================================
    // 3. STAFF INTERNAL OPERATIONS CONSOLE (C-19 to C-29, Gate D-45)
    // Web-only internal console (Strong auth + RBAC + Audit Logs)
    // =========================================================================
    ['GET',  '/staff/login',                         ['Staff\StaffAuthController', 'showLogin'], ['access' => 'guest']],       // D-45 Bcrypt + rate-limited login
    ['POST', '/staff/login',                         ['Staff\StaffAuthController', 'login'], ['access' => 'guest']],           // D-45 Password step
    ['GET',  '/staff/2fa',                           ['Staff\StaffAuthController', 'showTwoFactor'], ['access' => 'guest']],   // D-45 RFC 6238 challenge/enrollment
    ['POST', '/staff/2fa',                           ['Staff\StaffAuthController', 'verifyTwoFactor'], ['access' => 'guest']], // D-45 TOTP verification
    ['POST', '/staff/logout',                        ['Staff\StaffAuthController', 'logout'], ['access' => 'authenticated']],          // D-45 Audited logout
    ['GET',  '/staff',                               ['Staff\DashboardController', 'index'], ['access' => 'authenticated']],           // Console overview & pilot metrics
    ['GET',  '/staff/profile',                       ['Staff\ProfileController', 'index'], ['access' => 'authenticated']],             // Staff self-service profile and security
    ['POST', '/staff/profile',                       ['Staff\ProfileController', 'updateProfile'], ['access' => 'authenticated']],     // Staff personal identity
    ['POST', '/staff/profile/password',              ['Staff\ProfileController', 'changePassword'], ['access' => 'authenticated']],    // Staff self-service password change
    ['POST', '/staff/profile/sessions/others/revoke', ['Staff\ProfileController', 'revokeOtherSessions'], ['access' => 'authenticated']],
    ['POST', '/staff/profile/sessions/{id}/revoke',  ['Staff\ProfileController', 'revokeSession'], ['access' => 'authenticated']],
    ['POST', '/staff/profile/authenticator/replace', ['Staff\ProfileController', 'beginAuthenticatorReplacement'], ['access' => 'authenticated']],
    ['POST', '/staff/profile/authenticator/confirm', ['Staff\ProfileController', 'completeAuthenticatorReplacement'], ['access' => 'authenticated']],
    ['POST', '/staff/system/flush-cache',            ['Staff\DashboardController', 'flushCache'], ['access' => 'administrator']],      // Zero-restart cache & OPcache flush
    ['POST', '/staff/gates/toggle',                  ['Staff\DashboardController', 'toggleGate'], ['access' => 'administrator']],      // D-31..D-45 Readiness Gate toggle
    ['GET',  '/staff/listings',                      ['Staff\ListingController', 'index'], ['access' => 'authenticated']],             // C-19, C-20 Business & Branch directory
    ['POST', '/staff/listings/create',               ['Staff\ListingController', 'create'], ['access' => 'authenticated']],            // C-19 Create prospect/draft + Permission
    ['POST', '/staff/listings/{branch_id}/update',   ['Staff\ListingController', 'update'], ['access' => 'authenticated']],            // C-20 Edit Business/Branch attributes
    ['POST', '/staff/listings/{branch_id}/lifecycle',['Staff\ListingController', 'lifecycle'], ['access' => 'authenticated']],         // C-20 Audited close/unpublish/reopen workflow
    ['POST', '/staff/listings/{branch_id}/media',    ['Staff\ListingController', 'uploadMedia'], ['access' => 'authenticated']],       // C-22 Replace permitted Branch cover
    ['POST', '/staff/listings/{branch_id}/media/{media_id}/remove', ['Staff\ListingController', 'removeMedia'], ['access' => 'authenticated']], // C-22 Audited media removal
    ['POST', '/staff/listings/{branch_id}/permission/revoke', ['Staff\ListingController', 'revokePermission'], ['access' => 'authenticated']], // C-22 Permission withdrawal cleanup
    ['GET',  '/staff/verification',                  ['Staff\VerificationController', 'index'], ['access' => 'authenticated']],        // C-21 Verification & QC queue
    ['POST', '/staff/verification/{branch_id}/qc',   ['Staff\VerificationController', 'recordQc'], ['access' => 'authenticated']],     // C-21 Pass/Fail QC + Publish gate
    ['GET',  '/staff/taxonomy',                      ['Staff\TaxonomyController', 'index'], ['access' => 'authenticated']],            // C-23 2-level Category & Alias manager
    ['POST', '/staff/taxonomy/save',                 ['Staff\TaxonomyController', 'save'], ['access' => 'administrator']],             // C-23 Reference-data taxonomy update
    ['GET',  '/staff/locations',                     ['Staff\LocationController', 'index'], ['access' => 'authenticated']],            // C-24 Area & Sub-city manager
    ['POST', '/staff/locations/save',                ['Staff\LocationController', 'save'], ['access' => 'administrator']],             // C-24 Area update
    ['GET',  '/staff/moderation',                    ['Staff\ModerationController', 'index'], ['access' => 'authenticated']],          // C-25 Pre-publication review queue
    ['POST', '/staff/moderation/{review_id}/decide', ['Staff\ModerationController', 'decide'], ['access' => 'authenticated']],         // C-25 Initial moderation
    ['GET',  '/staff/review-appeals',                ['Staff\ReviewAppealController', 'index'], ['access' => 'administrator']],         // C-25 Administrator appeal queue
    ['POST', '/staff/review-appeals/{appeal_id}/decide', ['Staff\ReviewAppealController', 'decide'], ['access' => 'administrator']],     // C-25 Final appeal decision
    ['GET',  '/staff/reports',                       ['Staff\ReportQueueController', 'index'], ['access' => 'authenticated']],         // C-26 Public report triage queue
    ['POST', '/staff/reports/{report_id}/resolve',   ['Staff\ReportQueueController', 'resolve'], ['access' => 'authenticated']],       // C-26 Resolve / Escalate report
    ['GET',  '/staff/campaigns',                     ['Staff\CampaignController', 'index'], ['access' => 'authenticated']],            // C-27 Staff-managed sponsorships
    ['POST', '/staff/campaigns/create',              ['Staff\CampaignController', 'create'], ['access' => 'authenticated']],           // C-27 Operator records campaign
    ['POST', '/staff/campaigns/{id}/approve',        ['Staff\CampaignController', 'approve'], ['access' => 'administrator']],          // C-27 Admin approves (blocked by D-39 if off)
    ['POST', '/staff/campaigns/{id}/terminate',      ['Staff\CampaignController', 'terminate'], ['access' => 'administrator']],        // C-27 Immediate audited termination
    ['GET',  '/staff/analytics',                     ['Staff\AnalyticsController', 'index'], ['access' => 'authenticated']],           // C-28 Operational & zero-result analytics
    ['GET',  '/staff/audit-logs',                    ['Staff\AuditLogController', 'index'], ['access' => 'administrator']],            // C-29 Admin-only append-only audit trail
    ['GET',  '/staff/privacy-requests',               ['Staff\PrivacyRequestController', 'index'], ['access' => 'administrator']],
    ['POST', '/staff/privacy-requests/{id}/resolve',  ['Staff\PrivacyRequestController', 'resolve'], ['access' => 'administrator']],
    ['GET',  '/staff/users',                         ['Staff\StaffUserController', 'index'], ['access' => 'administrator']],           // D-45 Admin Staff/RBAC manager
    ['POST', '/staff/users/create',                  ['Staff\StaffUserController', 'create'], ['access' => 'administrator']],          // D-45 Create staff account
    ['POST', '/staff/users/{id}/toggle',             ['Staff\StaffUserController', 'toggleActive'], ['access' => 'administrator']],    // D-45 Suspend/activate staff
    ['POST', '/staff/users/{id}/password',           ['Staff\StaffUserController', 'resetPassword'], ['access' => 'administrator']],   // D-45 Admin password reset
    ['POST', '/staff/users/{id}/2fa-reset',          ['Staff\StaffUserController', 'resetTotp'], ['access' => 'administrator']],       // D-45 Admin-assisted TOTP recovery
    ['GET',  '/staff/settings',                      ['Staff\SettingsController', 'index'], ['access' => 'administrator']],            // Auth/SMTP/System settings
    ['POST', '/staff/settings/save',                 ['Staff\SettingsController', 'save'], ['access' => 'administrator']],             // Zero-restart config hot reload
    ['POST', '/staff/settings/test-email',           ['Staff\SettingsController', 'testEmail'], ['access' => 'administrator']],        // SMTP live delivery test
];
