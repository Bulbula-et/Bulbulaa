# Bulbula.et V1 Completion Audit

**Audit date:** 2026-10-15  
**Specification:** `Bulbula_et_SaaS_Product_Features_V1.pdf` (40 capabilities, C-01–C-40)  
**Audited code:** `main` at `dec3207`
**Method:** specification-to-route, controller, service, repository, schema, view, JavaScript, test, deployment, and production-smoke comparison.

## Executive result

| Classification | Count | Meaning |
|---|---:|---|
| Implemented and materially evidenced | 37 | Primary V1 behavior exists with supporting code and/or tests. |
| Implemented with a material completion gap | 0 | Useful implementation exists, but one or more explicit acceptance rules remain incomplete. |
| Implemented but launch-gated | 3 | Product code exists, but counsel/commercial or operational approval remains required. |
| Wholly absent | 0 | Every capability has at least an implementation foundation. |

**Overall:** Bulbula.et has broad V1 coverage, but route presence is not the same as launch readiness. The highest remaining risks are real-device Telegram acceptance and outstanding legal, commercial, billing, tax, and operational launch gates.

## Capability matrix

| ID | Capability | Status | Evidence and remaining gap |
|---|---|---|---|
| C-01 | Homepage | Implemented | Search-first public homepage, curated category/area entry points, gated sponsored slots. |
| C-02 | Search | Implemented | Organic branch search includes business, category, area, aliases, and published-status filtering. |
| C-03 | Search autocomplete | Implemented | `/api/v1/autocomplete`, controlled taxonomy/area/branch suggestions, original-query submission remains available. |
| C-04 | Category/subcategory browsing | Implemented | Two-level browse routes and primary/secondary category query support. |
| C-05 | Location/area browsing | Implemented | Area catalogue and area pages exist; controlled reference data is used. |
| C-06 | Category-by-area pages | Implemented | Stable `/a/{area}/{category}` destination and published listing filtering. |
| C-07 | Nearby discovery | Implemented | Coordinates, radius controls, geolocation enhancement, and area fallback exist without mandatory location access. |
| C-08 | Business profiles | Implemented | Business and branch context, multi-branch switching, media, categories, reviews, and actions exist. |
| C-09 | Hours/open status | Implemented | Branch-level hours and explicit unknown/by-appointment handling. |
| C-10 | Maps/directions | Implemented | Maps handoff and location presentation exist. |
| C-11 | Contact actions | Implemented | Conditional call, email, website, Telegram, and directions actions with server-side action metrics. |
| C-12 | Trust indicators | Implemented | Verification presentation is operationally derived and separate from sponsorship. |
| C-13 | Reviews | Implemented | Integer ratings, optional text, one active review, 30-day edit window, moderation, newest-first public display, and unweighted aggregate. |
| C-14 | Save a Business | Implemented | Listing/detail save controls, guest sign-in handoff, Customer-bound saved state. |
| C-15 | Reports/corrections | Implemented | Guest listing reports, authenticated review-report rule, rate limiting, and Staff queue. |
| C-16 | Sponsored placements | Implemented | Three formats, visible labels, organic separation, D-39 activation gate, first-party metrics. |
| C-17 | Share a Business | Implemented | Canonical sharing uses the Telegram-native handoff when Telegram-provided Mini App runtime context is present, Web Share where supported, and copy fallback otherwise. The official bridge loads before the adapter under the centralized CSP, while every native capability call remains guarded. |
| C-18 | Policy pages | Launch-gated | Product pages exist; legal wording and publication readiness still require counsel/launch approval as the specification states. |
| C-19 | Listing creation | Implemented | Evidence-backed, transactional onboarding validates active reference data and representative details, rejects deterministic normalized duplicates under a concurrency lock, never fabricates missing contact values, and records a minimal audit event. |
| C-20 | Listing maintenance | Implemented | Staff can correct Business-level identity/summary and Branch-level address/contact/hours through their proper records. Corrections to live data return affected listings to QC; temporary/permanent closure, unpublish, and reopen transitions are reasoned, transactional, audited, and cannot bypass reverification. |
| C-21 | Verification/QC | Implemented | Preparation attribution is preserved separately from reviewer identity; independent review is enforced whenever another active Staff reviewer exists. Controlled same-person fallback requires no available reviewer, explicit justification, and audit evidence. Staff submit the real required checklist and decision; failed/returned reviews retain checklist evidence and a mandatory reason. QC history, publication state, and audit evidence are committed transactionally, and permission plus PASSED QC remains the authoritative publication gate. |
| C-22 | Media management | Implemented | Staff can add or replace the single active Branch cover and remove active media with a mandatory reason. Uploads retain byte MIME validation, GD decode/re-encode, metadata stripping, dimensional limits, randomized names, CSRF/RBAC, and audit evidence. Replacement/removal quarantine old files outside the webroot before transactional database changes and restore them on failure. Permission withdrawal atomically revokes authorization, unpublishes every Business Branch, clears verification/linkage, deactivates affected assets, and safely purges their files. |
| C-23 | Category management | Implemented | Administrator-only create/edit supports the controlled two-level hierarchy, bilingual labels, stable slugs, ordering, activation, and language-tagged search aliases. Mutations reject duplicate names/slugs and globally ambiguous aliases, prevent invalid parent depth or self-parenting, and block deactivation while Business assignments or active children remain. Changes and aliases commit transactionally, record before/after audit evidence, and invalidate tagged caches after commit. |
| C-24 | Location management | Implemented | Administrator-only area create/edit supports bilingual area/sub-city labels, stable slugs, validated map centers, ordering, launch-boundary state, activation, and language-tagged aliases. Duplicate/ambiguous terms are rejected and assigned Areas cannot be deactivated until Branches are reassigned. Area and alias changes commit transactionally with before/after audit evidence and post-commit tagged cache invalidation. |
| C-25 | Review moderation | Implemented | Human pre-publication moderation supports publish, reject, and Staff removal with recorded reasons and Customer notices. Eligible rejected/removed reviews expose a 180-day Customer appeal with immutable snapshots of the original decision and additional context. Appeal outcomes are separate append-only records, Administrator-only, independently reviewed when another Administrator exists, reasoned, audited, notified, and transactionally restore the review/rating aggregate only when uniqueness permits. |
| C-26 | Report management | Implemented | Staff triage/resolution/escalation and audit logging exist; review reports route into the review domain. |
| C-27 | Campaign management | Launch-gated | Creation, fixed-price terms, approved formats, targeting, Administrator approval, dates, and first-party delivery metrics are implemented behind D-39. Administrator-only early termination immediately removes active/scheduled inventory, requires a reason, preserves campaign history/metrics, rejects stale post-termination metrics, audits the transition, and notifies the campaign creator without asserting billing/refund consequences. External commercial, billing, tax, and legal gates remain intentionally closed. |
| C-28 | Operational analytics | Implemented | Server-side search/profile/action and queue summaries exist, including zero-result and campaign delivery reporting. |
| C-29 | Audit logs | Implemented | Administrator-only audit viewer and broad consequential-action recording exist; sensitive values are excluded by policy. |
| C-30 | Google authentication | Implemented | Official Google client adapter verifies server-side; email OTP fallback and guest browsing remain available. |
| C-31 | Email OTP | Implemented | Short-lived, single-use, hashed, purpose-bound, rate-limited OTP flow using PHPMailer. |
| C-32 | Unified identity | Implemented | Web and Telegram use the same responsive frontend, Customer records, sessions, saves, and reviews. The official Mini App bridge initializes runtime adaptation on mobile/desktop Telegram, but `initData` remains capability context only and is never accepted as Customer authentication. Standard verified Customer sign-in remains authoritative. |
| C-33 | Customer profile | Implemented | Minimal profile, saved/review/privacy/session controls exist. Review the optional phone field against minimisation before launch. |
| C-34 | Save management | Implemented | Saved list, removal, and unavailable-business handling exist. |
| C-35 | Review management | Implemented | Own-review list/status, edit-window enforcement, moderation reset, and withdrawal exist. |
| C-36 | Account deletion/privacy controls | Implemented | Self-service verified export/deletion, 30-day recovery, permanent deletion, Staff exceptional route, auditing, sessions, and notifications. |
| C-37 | SEO | Implemented | SSR, canonical URLs, metadata, dynamic sitemap, and LocalBusiness structured data exist. |
| C-38 | Product analytics | Implemented | First-party server-side measurement; no third-party behavioral tracker is present. |
| C-39 | Notifications | Implemented | PHPMailer-backed transactional notifications cover OTP and account/security lifecycle events plus review submission, edit, publication, rejection, and Staff removal. Review mail excludes review text and private moderation notes; delivery success/failure is recorded as a non-sensitive Customer audit event and transport failure cannot roll back the completed review action. |
| C-40 | Privacy/data handling | Launch-gated | Minimisation controls, export/deletion, access control, and no ad-tech are implemented. Retention, cross-border/data-location, and final legal handling remain counsel decisions; fabricated permission contact data must be removed. |

## Scope deviation requiring a product decision

The approved catalogue says Customer **password login is not V1**, but the deployed application now supports registration, password login, password recovery, and password changes in addition to Google and email OTP. This was intentionally built during later account work, but it is a scope deviation and increases authentication, recovery, notification, and support obligations.

Do not remove it silently. Record one of these decisions before launch:

1. Approve password authentication as a formal V1 scope amendment; or
2. Hide/disable it while retaining migration compatibility; or
3. Move it to a post-V1 feature and preserve OTP/Google as the only V1 methods.

## Prioritized completion roadmap

### P0 — Launch integrity

1. **Scope decision: Customer passwords**
   - Obtain and record explicit product approval or disable the out-of-scope surface.

### P1 — Required capability completion


### P2 — Launch assurance

11. Final counsel approval for policy wording, retention, deletion, cross-border/data-location, and campaign terms.
12. Telegram mobile/desktop acceptance testing.
13. Accessibility, performance, SEO, email-deliverability, backup/restore, and shared-hosting disaster-recovery exercises.
14. Operational pilot with real permission, QC, report, moderation, and closure cases.

## Immediate next implementation milestone

All materially incomplete software capabilities identified by this audit are now implemented. Proceed with **P2 launch assurance** next: automated accessibility/performance/SEO checks, SMTP deliverability verification, backup/restore rehearsal, shared-hosting disaster-recovery evidence, and real-device Telegram acceptance. The Customer-password scope decision and legal/commercial launch gates require explicit human approval and must not be silently inferred by implementation.

## P2 launch assurance evidence — 2026-10-15

- Automated CI baseline checks representative public routes for HTTP 200, semantic landmarks, language, headings, metadata, image alternative-text declarations, icon-button names, absolute configured canonicals, parseable JSON-LD, sitemap XML/uniqueness/origin, robots exclusions/declaration, and conservative 750 KB / 2-second local-response budgets. These are partial automated signals, **not** WCAG 2.2 conformance or field Core Web Vitals evidence. CI `38755221793` passed on PHP 8.2/8.3/8.4 and MariaDB 10.11 after the restore-test bootstrap fix in `c2fe844`.
- Isolated SQLite CI backup/restore rehearsal verifies `PRAGMA integrity_check` and table-row parity. CI `38757350642` additionally passed a **synthetic MariaDB 10.11** logical backup/restore rehearsal against a separate short-lived CI database: SHA-256 check, all-table schema-name/row-count parity, per-table `CHECK TABLE`, migration status, and source isolation. The MariaDB shared-host procedure and evidence format are in `docs/SHARED_HOST_RECOVERY.md`; **an isolated restore of an actual production backup and media has not yet been executed**. Retention, off-host copies, RPO/RTO, and cadence require owner approval.
- Production backup before deployment: `/home/arkeonet/backups/launch-assurance-20261015-145351` (application archive plus MariaDB dump, both SHA-256 verified). Deployed `c2fe844` after green CI; `/`, `/staff/login`, `/sitemap.xml`, `/robots.txt`, and a representative branch returned HTTP 200. Branch canonical and JSON-LD used the configured production origin. **Open operational follow-up:** unparameterized `/robots.txt` remains inconsistent across Cloudflare edges even after publishing the correct origin-aware static file: PDX returns the new file, while SEA returns an October 14 cached copy with `cf-cache-status: HIT`, `Cache-Control: public, max-age=2592000`, and no `Sitemap:` line. This is edge-cache staleness, **not** a PHP OPcache issue. A five-minute origin TTL is configured for future responses, but the stale SEA object requires a Cloudflare single-URL cache purge (or expiration), then repeated edge checks. Do not claim the public robots declaration is fully verified before purging.
- Rehearsal evidence: `664c6d3` passed CI `38757350642` across PHP 8.2/8.3/8.4 and MariaDB 10.11; audit follow-up `5060633` passed CI `38757580379`. Before deploying `5060633`, production application and MariaDB were archived at `/home/arkeonet/backups/mariadb-rehearsal-20261015-150916`, both SHA-256 checks passed; deployment and `/`, `/staff/login`, `/sitemap.xml` HTTP 200 smoke checks succeeded. This is **synthetic CI restore evidence**, not a restore of that production backup.
- SMTP acceptance procedure is in `docs/MAIL_DELIVERABILITY_ACCEPTANCE.md`. The Administrator test now validates recipients server-side, limits attempted sends to three per hour per Staff member, audits only a reference/transport acceptance outcome, and distinguishes handoff from delivery. `5a01d12` passed CI `38760998726` across PHP 8.2/8.3/8.4 and MariaDB 10.11. Pre-deployment application/MariaDB backup `/home/arkeonet/backups/mail-assurance-20261015-153211` passed both SHA-256 checks; production `/`, `/staff/login`, and `/sitemap.xml` returned 200; unauthenticated mail-test POST redirected without a send. Production SMTP configuration is present; the From domain has SPF and DMARC TXT records, but DKIM, alignment, mailbox placement, and OTP completion have **not** been verified by a recipient. No live test email was sent by this deployment.
- Telegram Android and Desktop users reported a blank `accounts.google.com/gsi/transform` page after choosing Google while ordinary browser Google sign-in works. The embedded Google popup cannot reliably complete or return an authenticated Mini App session. A short-lived, session-bound external-browser handoff is implemented and specified in `docs/GOOGLE_TELEGRAM_BROWSER_HANDOFF.md`; simulated isolation tests and CI are required before deployment, and **actual Android/Desktop acceptance remains open**.
- Manual accessibility and Telegram acceptance scripts are in `docs/MANUAL_ACCESSIBILITY_TELEGRAM_ACCEPTANCE.md` with NOT RUN statuses and sanitized evidence fields. The bridge capability fix `0c50199` supports Telegram launches with empty `initData` and a platform indicator without granting Customer identity; dependency-free adapter regression checks passed CI `38768661919` on PHP 8.2/8.3/8.4 and MariaDB 10.11. Pre-deployment backup `/home/arkeonet/backups/telegram-acceptance-20261015-162514` passed application and MariaDB SHA-256 checks; deployed `/`, `/staff/login`, and a Branch route returned 200 and the updated adapter asset was served. These tests do **not** substitute for real devices. Manual keyboard and screen-reader testing, responsive real-device checks, SMTP deliverability to an owner-controlled mailbox, real-device Telegram acceptance, legal/commercial approval, production-backup MariaDB/media restore, and the Customer-password scope decision remain open human/operational gates. SMTP proof requires an owner-designated test mailbox and consent before sending; automated transport success alone cannot establish inbox placement. No automated result silently closes them.

## Verification basis

- `config/routes.php` route inventory.
- Public, Customer, and Staff controllers.
- Domain policies and services.
- Repositories and baseline/incremental migrations.
- Public/Customer/Staff views and frontend modules.
- Existing unit, E2E, production-preflight, security-header, concurrency, and parity tests.
- Successful production smoke checks and CI through deployed `45eab95`.

This audit measures software completion against the approved catalogue. It does not declare unresolved counsel, tax, billing, campaign-integrity, provider, or operational decisions complete.
