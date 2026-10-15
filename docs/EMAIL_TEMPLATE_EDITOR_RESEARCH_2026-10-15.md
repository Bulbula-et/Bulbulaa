# Bulbula.et — visual email template editor research
Date: 2026-10-15 (Africa/Addis_Ababa)
Status: owner approved **all transactional emails** for the first integration; no live SMTP test authorized.

## Decision

**Recommend GrapesJS core + the official `grapesjs-preset-newsletter` plugin**, self-hosted as pinned static browser assets. Keep Bulbula’s PHP/PHPMailer transport, database and session/authentication; do **not** install a Node runtime or a PHP application framework on shared hosting. Add a separately reviewed server-side HTML sanitizer such as `ezyang/htmlpurifier` (pin version/lockfile; test with email CSS) before a saved design can become a published email. This is a recommendation, **not a claim that the editor itself makes arbitrary HTML safe**.

Why: GrapesJS is a long-lived, actively maintained, framework-independent browser editor. Upstream published core v0.23.8 on 2026-10-15 and its source license is BSD-3-Clause [1](https://github.com/GrapesJS/grapesjs) [2](https://github.com/GrapesJS/grapesjs/blob/dev/packages/core/LICENSE). The official newsletter preset provides table/section/text/button/image blocks and an inlined-HTML export command, and its repository was updated in October 2026 [3](https://github.com/GrapesJS/preset-newsletter). The last **published preset release appears to remain 1.0.2 (June 2023)**; review its compatibility with the pinned core before committing [4](https://www.npmjs.com/package/grapesjs-preset-newsletter). Upstream documents storing a project JSON for re-editing, **separately** from email HTML for sending [5](https://grapesjs.com/docs/modules/Storage.html).

### Why not the alternatives?

| Candidate | Finding | Fit |
| --- | --- | --- |
| GrapesJS + official newsletter preset | Vanilla JavaScript editor, HTML/CSS inline export, build-time npm only; old published preset needs a compatibility test [3](https://github.com/GrapesJS/preset-newsletter) [4](https://www.npmjs.com/package/grapesjs-preset-newsletter). | **Preferred** for existing PHP + vanilla JS frontend. |
| GrapesJS + MJML plugin | Better responsive email semantics and actively maintained official plugin (1.0.8, March 2026), but browser MJML compiler makes bundle notably heavier; test output/size before choosing it [6](https://github.com/GrapesJS/mjml) [7](https://www.npmjs.com/package/grapesjs-mjml). | Optional enhancement if newsletter preset cannot satisfy email-client compatibility. |
| Easy Email | React/MJML editor; upstream states open-source version is Chrome-only, does not support React 19, and its responsive UI and advanced assets are Pro-only [8](https://github.com/zalify/easy-email-editor). | Conflicts with framework-free, low-dependency integration goal. |
| EmailBuilder.js | MIT React component with several runtime dependencies, good block editor but introduces React runtime into this existing vanilla frontend [9](https://github.com/usewaypoint/email-builder-js/blob/main/package.json). | Not first choice. |
| Mosaico | Mature visual product, but upstream site says GPL, and using its full stack/image backend would require additional deployment and legal review [10](https://mosaico.io/). | Not a low-friction shared-host embedding. |
| Unlayer / commercial Studio SDK | Do not confuse an embeddable, possibly hosted/commercial editor with fully open-source self-hosted GrapesJS. | Exclude from open-source-only requirement. |

## Security assessment, not an endorsement of arbitrary markup

GrapesJS has had historical XSS advisories affecting *older* versions, including a live-preview issue before 0.17.25 [11](https://security.snyk.io/vuln/SNYK-JS-GRAPESJS-1020496). Pin a reviewed current release; run `npm audit` against the exact lockfile and test the complete editor bundle/plugins before release. Sanitization and proper context-sensitive encoding are still required even when a visual editor is trusted: OWASP explicitly distinguishes encoding text from sanitizing HTML [12](https://cheatsheetseries.owasp.org/cheatsheets/Cross_Site_Scripting_Prevention_Cheat_Sheet.html). HTML Purifier has current PHP-compatible releases; the upstream v4.19.1 was published in September 2026 [13](https://github.com/ezyang/htmlpurifier/releases/tag/v4.19.1). Confirm CSS/Outlook compatibility with a restrictive allowlist; if sanitization damages essential markup, fail publication rather than weaken the policy silently.

**Integration boundaries:**
1. Administrator-only `/staff` screen: catalog of known message IDs (not arbitrary event code), select one, edit its subject and design, preview sample *fake* values, save draft, review, then publish. Keep versions and rollback; leave existing static messages as fallbacks if a template is absent or invalid. Do not modify Staff password/authorization rules.
2. Store editor **project JSON** to allow future editing, plus a server-validated, sanitized, bounded HTML snapshot for sending. Limit payload size/complexity, images to approved HTTPS Bulbula-hosted assets (no tracking pixel, data URI, SVG, script or remote CSS), URLs to approved destinations. Sandbox the preview separately from the Staff Console; never render arbitrary draft markup in the app origin. Enforce Staff admin authorization + CSRF + audit records on save/publish/rollback. Do not expose draft HTML on public routes.
3. Allow typed, purpose-specific placeholders only, such as `{{code}}`, `{{minutes}}`, `{{name}}`. Never evaluate PHP, JavaScript or arbitrary template expressions. Escape values for HTML/text/subject/URL contexts. For password recovery and registration, require the server-generated one-time code and expiry to be present in the **published** template; publish validation and a non-editable security footer prevent an editor from making recovery unusable or misleading. Never store a real code in a draft, a revision or a preview; fake values only.
4. Send multipart `text/html` + `text/plain` via the existing PHPMailer adapter. Preserve the six-digit, short-lived, single-use OTP verification and production delivery-failure behavior. HTML decoration cannot change code creation, delivery purpose, attempt caps, or account creation order. An editing/publishing event should never automatically send a real email; test sends only upon explicit user action.
5. No Node/MJML server process in production. Build pinned JS/CSS locally and ship compiled assets; PHP handles runtime template selection/rendering. Verify Content Security Policy on the Staff editor and no third-party CDN calls. Check desktop/mobile editor usability and render samples in Gmail, Outlook and Apple Mail; web preview is not an inbox compatibility guarantee.

## Existing mail inventory found in the application

- `CustomerRegistrationService::deliver`: plain-text six-digit registration code (currently separate).
- `CustomerAuthService::issueEmailOtp`: shared sign-in, password reset, verify email and sensitive-action codes, currently static HTML/text.
- `CustomerSecurityMailer`: password/email change notices.
- `CustomerLifecycleMailer`: deletion scheduled, restored and permanently deleted.
- `CustomerReviewMailer`: review submission, moderation and appeal outcomes.
- `StaffCampaignMailer`: operational campaign termination notice.
- `SettingsController::testEmail`: explicit SMTP diagnostic; **do not automatically trigger**.

## Proposed verification gates before production

- Lock versions and confirm upstream license/dependency footprint; run `npm audit` / `composer audit`; review project/plugin compatibility and self-hosted bundle size.
- PHPUnit: authorization, CSRF, draft/publish/revision/rollback, concurrent versioning, XSS payloads, unsafe CSS/URL rejection, missing required placeholders, correct escaped substitutions, no plaintext OTP in storage or logs, text fallback, invalid template fallback, and no live-send side effects.
- Browser/editor checks for drag-and-drop, preview isolation, saved project roundtrip, CSP, keyboard accessibility. CI MariaDB/SQLite migration and email send-stub tests. Production DB+file backup and rollback before deployment. **Owner alone tests production SMTP.**

## Owner decision needed

Owner selected **all transactional types in the inventory**. Integration keeps Administrator-only editing and draft/preview/publish/rollback.
