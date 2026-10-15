# Bulbula.et V1 Completion Audit

**Audit date:** 2026-10-15  
**Specification:** `Bulbula_et_SaaS_Product_Features_V1.pdf` (40 capabilities, C-01–C-40)  
**Audited code:** `main` at `bf6380b`
**Method:** specification-to-route, controller, service, repository, schema, view, JavaScript, test, deployment, and production-smoke comparison.

## Executive result

| Classification | Count | Meaning |
|---|---:|---|
| Implemented and materially evidenced | 34 | Primary V1 behavior exists with supporting code and/or tests. |
| Implemented with a material completion gap | 3 | Useful implementation exists, but one or more explicit acceptance rules remain incomplete. |
| Implemented but launch-gated | 3 | Product code exists, but counsel/commercial or operational approval remains required. |
| Wholly absent | 0 | Every capability has at least an implementation foundation. |

**Overall:** Bulbula.et has broad V1 coverage, but route presence is not the same as launch readiness. The highest remaining risks are incomplete reference-data maintenance, review appeals, campaign termination, real-device Telegram acceptance, and outstanding legal/operational launch gates.

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
| C-23 | Category management | Partial | Administrator can add level-1/2 records. Editing, controlled alias maintenance, activation/deactivation, and duplicate-safe validation are incomplete. |
| C-24 | Location management | Partial | Administrator can add areas. Editing, aliases, activation/boundary maintenance, hierarchy controls, and assignment maintenance are incomplete. |
| C-25 | Review moderation | Partial | Pending queue and approve/reject/remove decisions exist. A distinct Administrator appeal workflow is not implemented. |
| C-26 | Report management | Implemented | Staff triage/resolution/escalation and audit logging exist; review reports route into the review domain. |
| C-27 | Campaign management | Launch-gated / Partial | Creation, Administrator approval, dates, fixed price, formats, metrics, and D-39 gate exist. Early termination with a mandatory reason is absent. External commercial, billing, tax, and legal gates remain intentionally closed. |
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

4. **C-23/C-24 reference-data maintenance** — edit, aliases, activation, validation, and safe cache invalidation.
5. **C-25 appeals** — Administrator-only appeal review and audited outcome.
6. **C-27 early campaign termination** — Administrator action with mandatory reason and immediate inventory removal.

### P2 — Launch assurance

11. Final counsel approval for policy wording, retention, deletion, cross-border/data-location, and campaign terms.
12. Telegram mobile/desktop acceptance testing.
13. Accessibility, performance, SEO, email-deliverability, backup/restore, and shared-hosting disaster-recovery exercises.
14. Operational pilot with real permission, QC, report, moderation, and closure cases.

## Immediate next implementation milestone

Proceed with **C-23/C-24 reference-data maintenance** next while the Customer-password scope decision awaits explicit product approval. Add duplicate-safe edit, alias, activation/deactivation, and hierarchy/assignment protections for Categories and Areas, with transactional cache invalidation and audited Administrator-only mutations.

## Verification basis

- `config/routes.php` route inventory.
- Public, Customer, and Staff controllers.
- Domain policies and services.
- Repositories and baseline/incremental migrations.
- Public/Customer/Staff views and frontend modules.
- Existing unit, E2E, production-preflight, security-header, concurrency, and parity tests.
- Successful production smoke checks and CI through deployed `45eab95`.

This audit measures software completion against the approved catalogue. It does not declare unresolved counsel, tax, billing, campaign-integrity, provider, or operational decisions complete.
