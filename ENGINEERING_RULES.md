# Bulbula.et Engineering Rules

## Plain PHP, Clean Architecture & Selective Dependency Rule (Locked — 2026-10-14)

Bulbula.et must remain a **plain PHP application without a framework**. It must stay easy to understand, upload, configure, and run on inexpensive shared hosting.

- Do not introduce Laravel, Symfony Framework, Slim, CodeIgniter, or another application framework.
- Do not replace small, stable, application-specific code merely because a package exists.
- A dependency is allowed only when it is a focused, mature, security-reviewed library that removes substantial protocol/security risk (for example PHPMailer or Google's official API client).
- Prefer zero-dependency PHP and native PHP functionality when the implementation is small, clear, testable, and not security/protocol sensitive.
- Reject dependency-heavy packages, framework components with large transitive graphs, daemon/Redis requirements, and solutions that complicate cPanel/shared-hosting deployment.
- Keep every approved library behind a small Bulbula.et adapter so it can be replaced without changing domain logic.
- `vendor/` is generated from the pinned `composer.lock`; it is not source code and must not be committed. Shared hosting receives it via `composer install` or a deployment upload bundle.
- Clean architecture boundaries remain mandatory: Core infrastructure, Domain rules, Repositories, Services, Controllers, and Views.
- When no clean, lightweight library is suitable, keep or improve the plain-PHP implementation and cover it with tests.

## Mature Open-Source Integration First (Qualified by the Plain-PHP Rule)

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

### Remediation record

The initial custom SMTP socket implementation and direct Google token-info verification introduced in commit `ee2a25a` were explicitly provisional. They were subsequently replaced before production activation with mature packages:

- **PHPMailer/PHPMailer** for SMTP and transactional email.
- **google/apiclient** (official Google PHP client) for cryptographic Google ID-token verification and Google Identity integration.

Versions are pinned through `composer.lock`; deployment must run `composer install --no-dev --prefer-dist --optimize-autoloader` and `composer audit --no-dev`.
