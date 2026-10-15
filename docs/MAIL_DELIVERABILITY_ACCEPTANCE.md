# Transactional email acceptance (P2)

**Status: pending recipient confirmation.** SMTP server acceptance and PHP `mail()` submission are not evidence of mailbox delivery, inbox placement, or successful OTP completion. Do not send to an unapproved address.

## Existing path and safety boundaries

The Administrator-only Staff Console at `/staff/settings` offers an email transport test. It uses the same PHPMailer-backed `Bulbula\Core\Mailer` adapter as Customer OTP and lifecycle notices. The POST is CSRF-protected, validates the recipient server-side, and allows at most three attempts per Staff member per hour. Each attempted handoff records only a generated test reference, transport type, and whether the transport accepted it—never the recipient, password, SMTP transcript, or message body. The displayed outcome says **accepted by transport**, not **delivered**.

A configured SMTP transport alone cannot establish delivery. The configured production From domain was observed as `arkeonethiopia.com` on 2026-10-15; DNS TXT lookup returned a single SPF record and a DMARC record with `p=none`. This is a configuration observation, not a verified SPF/DKIM pass or DMARC alignment on a received message. The DKIM selector and actual message headers have not been provided. Do not change DNS or outbound mail settings based solely on this observation.

## Owner-controlled test procedure

1. The owner designates a test mailbox and explicitly authorizes **one** transactional test message to it. Keep the full mailbox address out of the repository and screenshots. Use an external mailbox provider as well as an internal one if practical.
2. Administrator signs in with 2FA, opens **Staff Console → Settings → Email delivery test**, submits the approved mailbox address once, and records UTC time and the generated reference. If transport rejects, stop and inspect protected mail logs and server configuration; do not repeatedly retry or lower TLS verification.
3. Mailbox owner checks inbox, spam/junk, and any quarantine; reports whether the reference arrived and its folder, approximate delay, and the provider's SPF, DKIM, and DMARC authentication results from **original message headers**. Do not upload full headers or customer data to Git. If possible, check alignment against the actual `From:` domain and provider return-path/DKIM signing domain.
4. With a dedicated consented test Customer address, exercise the **actual OTP issuance and successful use** on Web, then an account/security lifecycle notification if permitted. A stand-alone settings test does not prove OTP flow or timing. Check expiry, one-time use, and spam placement. Do not record OTP values.
5. Investigate failed authentication, bounces, spam placement, unexpected delay, or missing messages with the mail provider. Record the outcome and owner sign-off before treating SMTP deliverability as a launch gate met. Do not log passwords, email addresses, OTPs, or SMTP transcript content.

## Evidence fields

Record UTC time, environment, deployed Git commit, transport (`smtp` or `mail`), reference, whether transport accepted, mailbox provider, received/not received, inbox/junk/quarantine, delay range, SPF/DKIM/DMARC pass/fail/alignment (from received headers), actual OTP flow success, exceptions, and reviewer. A synthetic CI check or accepted SMTP `250` response is **not** a substitute for recipient observation.

Reference: [Google email sender guidelines](https://support.google.com/a/answer/81126) for SPF/DKIM/DMARC alignment and TLS expectations; [PHPMailer troubleshooting](https://github.com/PHPMailer/PHPMailer/wiki/Troubleshooting) for TLS diagnostics without weakening certificate checks.
