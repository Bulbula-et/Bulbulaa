# Incident follow-up: Staff enrollment must not take the public site offline

Production incident reported 2026-10-16 (Africa/Addis_Ababa): all routes returned 503, including the email editor and public homepage. `Config::productionProblems()` was empty. `ProductionGuard::problems()` identified an active Staff Operator pending first TOTP enrollment (enabled flag set, encrypted secret absent). `StaffRepository::createStaff()` deliberately creates this state, and `StaffAuthService::authenticate()` deliberately requires password plus TOTP enrollment before finalizing the Staff session. A global preflight that treats the pending state as fatal therefore takes the *whole site* offline whenever a new Staff user is created or an authenticator is reset.

## Containment and safety

A verified fresh backup was taken. The unenrolled Operator was temporarily suspended and any Staff device sessions revoked, restoring public availability without disabling the production guard, MFA or strict Staff login. The Administrator account remained active and enrolled. Do not leave the Operator suspended after the corrected guard is live; reactivate the account so they can finish enrollment at their next login.

## Corrective change

- In production, require the mandatory D-45 Staff MFA gate in the static configuration checks. Keep the active-administrator, database-readiness and known-pilot-password checks.
- Do **not** fail global application boot for an active Staff account awaiting first TOTP enrollment or re-enrollment. The Staff sign-in service already places that person in a pending session and does not grant a Staff session until a valid authenticator code is verified and the encrypted secret is stored.
- When an Administrator resets another Staff member's TOTP enrollment, revoke that person's existing Staff device sessions atomically with clearing the secret. A future login then requires password and new authenticator enrollment.
- Add isolated tests for pending operator/administrator enrollment, mandatory gate, pilot password and administrator availability. Run full CI and smoke public + Staff login routes. Do not run a live SMTP test.

## Operational verification

After CI and deployment, re-enable the temporarily suspended Operator only when the mandatory Staff MFA gate is confirmed true; verify the public homepage remains 200, the email editor redirects unauthenticated browsers to Staff login, and the operator is required to enroll MFA before accessing Staff routes. Do not fabricate or store an authenticator secret on their behalf.
