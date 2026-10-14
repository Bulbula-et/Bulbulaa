# Bulbula.et Engineering Rules

## Mature Open-Source Integration First (Locked — 2026-10-14)

For security-sensitive, protocol-heavy, infrastructure, interoperability, and standards-based functionality, Bulbula.et must prefer a mature, actively maintained, widely adopted, security-reviewed open-source library over a custom implementation.

This applies especially to:

- SMTP and transactional email
- OAuth 2.0, OpenID Connect, Google Identity, and social login
- TOTP / multi-factor authentication
- Cryptography, JWT/JWK verification, signatures, and key handling
- File/image processing and MIME validation
- Telegram Mini App authentication
- Payments, webhooks, and external API clients
- HTML sanitization and other security boundaries

### Required selection checks

Before adopting a dependency, engineering must review:

1. Official or reputable upstream ownership.
2. Active maintenance and recent releases.
3. Security advisories and responsible disclosure history.
4. Broad production adoption.
5. PHP/runtime and shared-hosting compatibility.
6. Dependency weight and transitive dependency risk.
7. License compatibility.
8. Clear upgrade and rollback path.
9. Official documentation and test coverage.

### Implementation rule

- Do not hand-roll a protocol merely to avoid dependencies.
- Custom code may wrap a mature library behind a small Bulbula.et adapter/service so the application remains maintainable and replaceable.
- Secrets must remain in protected server configuration and must never be committed.
- Pin compatible dependency versions with `composer.lock` and run security auditing before deployment.
- If no suitable mature package exists, document the evaluation and receive explicit approval before implementing custom security-sensitive protocol code.

### Immediate remediation decision

The initial custom SMTP socket implementation and direct Google token-info verification introduced in commit `ee2a25a` are provisional and must be replaced before production activation with mature packages:

- **PHPMailer/PHPMailer** for SMTP and transactional email.
- **google/apiclient** (official Google PHP client) for Google ID-token verification and Google Identity integration.

Until that replacement is completed and verified, SMTP and Google authentication must not be described as production-final.
