# P2 manual accessibility and Telegram acceptance protocol

**Status: NOT RUN.** This is a tester's script, not evidence of WCAG conformance or real-device acceptance. Record outcomes and defects before any gate is closed. Do not include OTPs, Telegram `initData`, session cookies, customer email addresses, or private listing evidence in screenshots or reports.

## Test setup and evidence

Use the production URL `https://discoverbulbula.arkeonethiopia.com` and note the Git commit, UTC time, tester, OS, browser or Telegram app version, viewport/device, assistive technology, and connection conditions. Use approved pilot data and a dedicated consented test Customer if account flows are exercised. Do not use destructive account deletion or paid-campaign activation in production. For each case record **PASS / FAIL / BLOCKED / NOT RUN**, exact steps, expected versus actual behavior, sanitized screenshot/video if useful, issue ID/severity/owner, and retest result. No check is passed merely because CI or a simulated bridge passed.

## Web manual accessibility matrix

Perform on representative routes: `/`, `/search?q=coffee`, `/categories`, `/areas`, a published `/b/.../...` Branch, `/auth/login`, `/account/saved` with a consented test account, and `/staff/login` or an approved Staff task. Include at least desktop keyboard-only, mobile touch, and one screen reader/browser pairing; add VoiceOver on iOS, TalkBack on Android, and NVDA on Windows where devices are available. Log unavailable combinations as **BLOCKED**, never pass.

| Case | Human action | Expected observation |
|---|---|---|
| A1 Keyboard/focus | From URL bar use Tab, Shift+Tab, Enter, Space, Escape through header, search, category cards, Branch actions, save button, sign-in, dialogs, and Staff login. | Focus is visible and logical; no keyboard trap; controls work without a pointer; focus returns appropriately after closing dialogs. |
| A2 Screen reader | Navigate headings, landmarks, forms, links, buttons, errors, save state and status announcements. | Names, roles, states, content order and feedback are understandable; no duplicated/conflicting instructions. |
| A3 Resize/reflow | Test 320 CSS px width and 200%/400% browser zoom; rotate mobile device, increase OS text size. | Core tasks remain available with no loss of content or two-dimensional scrolling except content that intrinsically needs it. |
| A4 Perception | Inspect text/icon contrast, focus-ring visibility, hover-only information, image alternative text, error explanations and target spacing. | Information is not color-only or motion-only; hover content is available to focus; errors identify a remedy. |
| A5 Reduced motion | Enable OS reduced motion and repeat navigation, save and open/close interactions. | Essential state feedback remains clear; non-essential motion is suppressed. |
| A6 End-to-end task | Find a business, open its Branch, use contact/directions, save/unsave via listing and detail, and return to saved places if authenticated. | Same Business state is communicated consistently, including a neutral outlined unsaved bookmark and orange filled saved state. |

These are focused exploratory checks. Use a competent reviewer and WCAG-EM-style coverage before claiming formal conformance; automated `tests/launch_assurance.php` only checks a subset of static properties.

## Telegram Mini App real-device matrix

Test **Telegram Android, Telegram iOS, and Telegram Desktop/macOS** where available, recording the actual client/version and bot launch entry point. The official bridge can expose an empty `initData` for some keyboard/inline launch modes. It is capability context only: never treat a platform flag, URL fragment, or raw launch data as verified Customer identity. Bot launch modes not configured by the owner are **BLOCKED**, not assumed tested.

| Case | Action | Expected observation |
|---|---|---|
| T1 Entry/viewport | Launch through the approved bot menu and, if configured, keyboard/inline entry; close/reopen, rotate and resize. | Responsive content usable without blank screen or obscured primary actions; native readiness/expand behavior does not block browsing even when launch data is empty. |
| T2 Discovery parity | Search, browse categories/areas, open published Branch and move back through Telegram/within-page navigation. | Same public routes and listing data as Web; no unexpected lost state or broken internal navigation. |
| T3 Share/contact | Trigger Branch share, phone, directions and website actions. | Shared link contains only the canonical public Branch URL, not session/launch data; capabilities fall back gracefully if unsupported or user cancels. |
| T4 Saves/identity | As guest try Save; with a dedicated verified Customer account sign in through approved method and save/unsave on listing and Branch detail. | Guest has a sign-in handoff; Unverified launch context never authenticates; after an explicit link on the primary Google-backed Customer, fresh server-verified Telegram launch data can authenticate that linked account. Confirm a different signed-in Customer is not silently switched or merged, and verified account's saved state is consistent with Web. Do not exercise OTP without owner consent. |
| T5 Interaction/accessibility | Enable device text enlargement/reduced motion and available screen reader; use desktop keyboard in Telegram. | Focus, labels, state changes, touch targets and layout remain usable; haptics are optional and never the only feedback. |
| T6 Web fallback | Open same link in standard mobile and desktop browsers without Telegram runtime. | Core flows remain available with Web Share or copy fallback and no Telegram-only dependency. |

## Gate sign-off

Record tester and owner review, device coverage, failures and retest, plus explicit acceptance or blocked reasons. **Do not mark T1–T6 or A1–A6 complete from the dependency-free adapter smoke test.** Outstanding legal/commercial gates, SMTP mailbox verification, Cloudflare purge, isolated production-backup restore, and the Customer-password scope decision are tracked separately in the V1 audit.

References: [W3C accessibility evaluation overview](https://www.w3.org/WAI/test-evaluate/), [W3C Easy Checks](https://www.w3.org/WAI/test-evaluate/preliminary/), and [Telegram Mini Apps platform and testing documentation](https://core.telegram.org/bots/webapps).

## New cross-device linked-account acceptance — NOT RUN

Use an approved non-destructive test Customer. On Telegram Android, complete Google browser handoff into the intended primary account and verify its email/name on Account settings. Check the explicit Telegram linking checkbox and link it. On Desktop using the **same Telegram user** but a different signed-in Bulbula Google Customer, verify the interface asks before switching and does not merge saves/reviews; confirm, then verify it opens the mobile primary Customer and its existing saves. On a fresh Telegram session verify automatic recognition. Check ordinary browser without signed Telegram data cannot bypass sign-in. Disconnect with `UNLINK` from Account settings and verify prior Telegram-origin sessions lose access; re-link only if required. Record PASS / FAIL / BLOCKED / NOT RUN separately for Android and Desktop. Do not test permanent deletion on production or expose initData/tokens in screenshots.
