<?php
declare(strict_types=1);

/**
 * Initializes the SQLite database (`storage/bulbula_v1.sqlite`) from the V1 schema
 * and Bole Bulbula Operational Pilot seed data (`D-31`) for instant live preview.
 * Does NOT modify `database/schema_v1_mysql.sql` (preserved for cPanel MySQL).
 */

$dbPath = __DIR__ . '/../storage/bulbula_v1.sqlite';
if (is_file($dbPath)) {
    unlink($dbPath);
}

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA foreign_keys = ON; PRAGMA journal_mode = WAL;');

$schemaSql = <<<'SQL'
CREATE TABLE system_gates (
    gate_key             TEXT PRIMARY KEY,
    decision_ref        TEXT NOT NULL,
    is_enabled          INTEGER NOT NULL DEFAULT 0,
    config_json         TEXT NULL,
    updated_by_staff_id INTEGER NULL,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE staff_users (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    email               TEXT NOT NULL UNIQUE,
    full_name           TEXT NOT NULL,
    role                TEXT NOT NULL DEFAULT 'OPERATOR',
    password_hash       TEXT NOT NULL,
    totp_secret_enc     TEXT NULL,
    totp_enabled        INTEGER NOT NULL DEFAULT 0,
    is_active           INTEGER NOT NULL DEFAULT 1,
    last_login_at       DATETIME NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE categories (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    parent_id           INTEGER NULL REFERENCES categories(id),
    depth_level         INTEGER NOT NULL DEFAULT 1,
    slug                TEXT NOT NULL UNIQUE,
    name_en             TEXT NOT NULL,
    name_am             TEXT NULL,
    description_en      TEXT NULL,
    icon_key            TEXT NULL,
    display_order       INTEGER NOT NULL DEFAULT 0,
    is_active           INTEGER NOT NULL DEFAULT 1,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE category_aliases (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    category_id         INTEGER NOT NULL REFERENCES categories(id) ON DELETE CASCADE,
    alias_term          TEXT NOT NULL,
    language_code       TEXT NOT NULL DEFAULT 'en',
    UNIQUE(category_id, alias_term)
);

CREATE TABLE areas (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    slug                TEXT NOT NULL UNIQUE,
    name_en             TEXT NOT NULL,
    name_am             TEXT NULL,
    sub_city_en         TEXT NOT NULL,
    sub_city_am         TEXT NULL,
    center_latitude     REAL NULL,
    center_longitude    REAL NULL,
    is_launch_area      INTEGER NOT NULL DEFAULT 0,
    is_active           INTEGER NOT NULL DEFAULT 1,
    display_order       INTEGER NOT NULL DEFAULT 0,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE area_aliases (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    area_id             INTEGER NOT NULL REFERENCES areas(id) ON DELETE CASCADE,
    alias_term          TEXT NOT NULL,
    language_code       TEXT NOT NULL DEFAULT 'en',
    UNIQUE(area_id, alias_term)
);

CREATE TABLE businesses (
    id                      INTEGER PRIMARY KEY AUTOINCREMENT,
    slug                    TEXT NOT NULL UNIQUE,
    name_en                 TEXT NOT NULL,
    name_am                 TEXT NULL,
    primary_category_id     INTEGER NOT NULL REFERENCES categories(id),
    brand_summary_en        TEXT NULL,
    brand_description_en    TEXT NULL,
    website_url             TEXT NULL,
    lifecycle_status        TEXT NOT NULL DEFAULT 'PROSPECT',
    created_by_staff_id     INTEGER NOT NULL REFERENCES staff_users(id),
    updated_by_staff_id     INTEGER NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE business_secondary_categories (
    business_id             INTEGER NOT NULL REFERENCES businesses(id) ON DELETE CASCADE,
    category_id             INTEGER NOT NULL REFERENCES categories(id),
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (business_id, category_id)
);

CREATE TABLE business_permissions (
    id                      INTEGER PRIMARY KEY AUTOINCREMENT,
    business_id             INTEGER NOT NULL REFERENCES businesses(id),
    status                  TEXT NOT NULL DEFAULT 'PENDING',
    rep_full_name           TEXT NOT NULL,
    rep_role_title          TEXT NOT NULL,
    rep_phone               TEXT NOT NULL,
    rep_email               TEXT NULL,
    permission_method       TEXT NOT NULL,
    evidence_storage_path   TEXT NULL,
    scope_notes             TEXT NULL,
    collected_by_staff_id   INTEGER NOT NULL REFERENCES staff_users(id),
    granted_at              DATETIME NULL,
    revoked_at              DATETIME NULL,
    revocation_reason       TEXT NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE branches (
    id                          INTEGER PRIMARY KEY AUTOINCREMENT,
    business_id                 INTEGER NOT NULL REFERENCES businesses(id),
    active_permission_id        INTEGER NULL REFERENCES business_permissions(id),
    branch_slug                 TEXT NOT NULL UNIQUE,
    branch_name_en              TEXT NOT NULL,
    branch_name_am              TEXT NULL,
    is_primary_branch           INTEGER NOT NULL DEFAULT 1,
    area_id                     INTEGER NOT NULL REFERENCES areas(id),
    sub_city_en                 TEXT NOT NULL,
    address_line_en             TEXT NOT NULL,
    landmark_notes_en           TEXT NULL,
    latitude                    REAL NULL,
    longitude                   REAL NULL,
    phone_primary               TEXT NULL,
    phone_secondary             TEXT NULL,
    branch_email                TEXT NULL,
    opening_hours_status        TEXT NOT NULL DEFAULT 'UNKNOWN',
    opening_hours_json          TEXT NULL,
    services_products_json      TEXT NULL,
    publication_status          TEXT NOT NULL DEFAULT 'DRAFT',
    is_verified                 INTEGER NOT NULL DEFAULT 0,
    last_verified_at            DATETIME NULL,
    last_verified_by_staff_id   INTEGER NULL,
    published_at                DATETIME NULL,
    published_rating_avg        REAL NULL,
    published_review_count      INTEGER NOT NULL DEFAULT 0,
    created_by_staff_id         INTEGER NOT NULL,
    updated_at                  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at                  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE verification_reviews (
    id                      INTEGER PRIMARY KEY AUTOINCREMENT,
    branch_id               INTEGER NOT NULL REFERENCES branches(id) ON DELETE CASCADE,
    permission_id           INTEGER NOT NULL REFERENCES business_permissions(id),
    submitted_by_staff_id   INTEGER NOT NULL REFERENCES staff_users(id),
    reviewed_by_staff_id    INTEGER NOT NULL REFERENCES staff_users(id),
    outcome                 TEXT NOT NULL,
    checklist_json          TEXT NOT NULL,
    reviewer_notes          TEXT NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE media_assets (
    id                      INTEGER PRIMARY KEY AUTOINCREMENT,
    business_id             INTEGER NOT NULL REFERENCES businesses(id) ON DELETE CASCADE,
    branch_id               INTEGER NULL REFERENCES branches(id) ON DELETE CASCADE,
    permission_id           INTEGER NOT NULL REFERENCES business_permissions(id),
    media_type              TEXT NOT NULL DEFAULT 'GALLERY_PHOTO',
    file_path               TEXT NOT NULL,
    width_px                INTEGER NOT NULL DEFAULT 800,
    height_px               INTEGER NOT NULL DEFAULT 600,
    file_size_bytes         INTEGER NOT NULL DEFAULT 45000,
    alt_text_en             TEXT NOT NULL,
    display_order           INTEGER NOT NULL DEFAULT 0,
    is_active               INTEGER NOT NULL DEFAULT 1,
    uploaded_by_staff_id    INTEGER NOT NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE customers (
    id                      INTEGER PRIMARY KEY AUTOINCREMENT,
    email                   TEXT NOT NULL UNIQUE,
    display_name            TEXT NOT NULL,
    google_sub_id           TEXT NULL UNIQUE,
    account_status          TEXT NOT NULL DEFAULT 'ACTIVE',
    email_verified_at       DATETIME NULL,
    last_active_surface     TEXT NOT NULL DEFAULT 'WEB',
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE customer_otp_tokens (
    id                      INTEGER PRIMARY KEY AUTOINCREMENT,
    email                   TEXT NOT NULL,
    code_hmac_sha256        TEXT NOT NULL,
    attempt_count           INTEGER NOT NULL DEFAULT 0,
    requester_ip_hash       TEXT NOT NULL,
    expires_at              DATETIME NOT NULL,
    consumed_at             DATETIME NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE customer_saved_businesses (
    customer_id             INTEGER NOT NULL REFERENCES customers(id) ON DELETE CASCADE,
    business_id             INTEGER NOT NULL REFERENCES businesses(id) ON DELETE CASCADE,
    preferred_branch_id     INTEGER NULL REFERENCES branches(id) ON DELETE SET NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (customer_id, business_id)
);

CREATE TABLE reviews (
    id                      INTEGER PRIMARY KEY AUTOINCREMENT,
    branch_id               INTEGER NOT NULL REFERENCES branches(id),
    customer_id             INTEGER NOT NULL REFERENCES customers(id),
    rating                  INTEGER NOT NULL CHECK (rating BETWEEN 1 AND 5),
    review_text             TEXT NULL,
    status                  TEXT NOT NULL DEFAULT 'PENDING_MODERATION',
    edit_count              INTEGER NOT NULL DEFAULT 0,
    first_submitted_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_edited_at          DATETIME NULL,
    moderated_by_staff_id   INTEGER NULL,
    moderated_at            DATETIME NULL,
    moderation_reason_code  TEXT NULL,
    moderation_notes        TEXT NULL,
    withdrawn_at            DATETIME NULL
);

CREATE TABLE public_reports (
    id                      INTEGER PRIMARY KEY AUTOINCREMENT,
    target_type             TEXT NOT NULL,
    branch_id               INTEGER NULL,
    business_id             INTEGER NULL,
    review_id               INTEGER NULL,
    reporter_customer_id    INTEGER NULL,
    reporter_contact_email  TEXT NULL,
    issue_category          TEXT NOT NULL,
    details_text            TEXT NOT NULL,
    workflow_status         TEXT NOT NULL DEFAULT 'OPEN',
    handled_by_staff_id     INTEGER NULL,
    resolution_notes        TEXT NULL,
    resolved_at             DATETIME NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE sponsorship_campaigns (
    id                      INTEGER PRIMARY KEY AUTOINCREMENT,
    campaign_code           TEXT NOT NULL UNIQUE,
    business_id             INTEGER NOT NULL REFERENCES businesses(id),
    branch_id               INTEGER NOT NULL REFERENCES branches(id),
    placement_format        TEXT NOT NULL,
    target_category_id      INTEGER NULL,
    target_area_id          INTEGER NULL,
    package_name            TEXT NOT NULL,
    agreed_price_etb        REAL NOT NULL,
    starts_at               DATETIME NOT NULL,
    ends_at                 DATETIME NOT NULL,
    status                  TEXT NOT NULL DEFAULT 'DRAFT',
    created_by_staff_id     INTEGER NOT NULL,
    approved_by_admin_id    INTEGER NULL,
    approved_at             DATETIME NULL,
    terminated_by_admin_id  INTEGER NULL,
    terminated_at           DATETIME NULL,
    termination_reason      TEXT NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE sponsorship_daily_metrics (
    campaign_id             INTEGER NOT NULL REFERENCES sponsorship_campaigns(id) ON DELETE CASCADE,
    metric_date             TEXT NOT NULL,
    surface                 TEXT NOT NULL,
    impressions_count       INTEGER NOT NULL DEFAULT 0,
    clicks_count            INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (campaign_id, metric_date, surface)
);

CREATE TABLE search_query_logs (
    id                      INTEGER PRIMARY KEY AUTOINCREMENT,
    surface                 TEXT NOT NULL DEFAULT 'WEB',
    query_normalized        TEXT NOT NULL,
    area_id                 INTEGER NULL,
    category_id             INTEGER NULL,
    results_count           INTEGER NOT NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE branch_daily_analytics (
    branch_id               INTEGER NOT NULL REFERENCES branches(id) ON DELETE CASCADE,
    metric_date             TEXT NOT NULL,
    surface                 TEXT NOT NULL,
    profile_views           INTEGER NOT NULL DEFAULT 0,
    action_call_clicks      INTEGER NOT NULL DEFAULT 0,
    action_map_clicks       INTEGER NOT NULL DEFAULT 0,
    action_website_clicks   INTEGER NOT NULL DEFAULT 0,
    action_email_clicks     INTEGER NOT NULL DEFAULT 0,
    action_share_clicks     INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (branch_id, metric_date, surface)
);

CREATE TABLE privacy_requests (
    id                      INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id             INTEGER NULL,
    requester_email         TEXT NOT NULL,
    request_type            TEXT NOT NULL,
    status                  TEXT NOT NULL DEFAULT 'SUBMITTED',
    handled_by_staff_id     INTEGER NULL,
    resolution_summary      TEXT NULL,
    completed_at            DATETIME NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE audit_logs (
    id                      INTEGER PRIMARY KEY AUTOINCREMENT,
    actor_type              TEXT NOT NULL,
    actor_staff_id          INTEGER NULL,
    actor_customer_id       INTEGER NULL,
    action_code             TEXT NOT NULL,
    entity_type             TEXT NOT NULL,
    entity_id               INTEGER NOT NULL,
    before_state_json       TEXT NULL,
    after_state_json        TEXT NULL,
    reason_notes            TEXT NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
SQL;

$pdo->exec($schemaSql);

// Load seed SQL and convert MySQL ON DUPLICATE KEY UPDATE clauses into SQLite INSERT OR REPLACE
$seedSql = file_get_contents(__DIR__ . '/seeds/seed_v1_pilot.sql');
$seedSql = preg_replace('/INSERT\s+INTO/i', 'INSERT OR REPLACE INTO', $seedSql);
$seedSql = preg_replace('/^\s*ON\s+DUPLICATE\s+KEY\s+UPDATE\s+.*$/im', ';', $seedSql);
$pdo->exec($seedSql);

// Add initial operational analytics and audit log entries for rich Staff Console preview
$pdo->exec("
INSERT INTO branch_daily_analytics (branch_id, metric_date, surface, profile_views, action_call_clicks, action_map_clicks, action_website_clicks, action_share_clicks) VALUES
(1, '2026-10-14', 'WEB', 142, 38, 29, 14, 11),
(1, '2026-10-14', 'TELEGRAM_MINI_APP', 86, 24, 19, 6, 18),
(2, '2026-10-14', 'WEB', 118, 41, 33, 0, 9),
(3, '2026-10-14', 'WEB', 195, 22, 54, 31, 27),
(4, '2026-10-14', 'WEB', 164, 19, 47, 12, 14),
(6, '2026-10-14', 'WEB', 73, 28, 16, 0, 5);

INSERT INTO sponsorship_daily_metrics (campaign_id, metric_date, surface, impressions_count, clicks_count) VALUES
(1, '2026-10-14', 'WEB', 640, 48),
(1, '2026-10-14', 'TELEGRAM_MINI_APP', 310, 29),
(2, '2026-10-14', 'WEB', 420, 37),
(3, '2026-10-14', 'WEB', 290, 24);

INSERT INTO search_query_logs (surface, query_normalized, area_id, category_id, results_count) VALUES
('WEB', 'clinic', 1, 11, 1),
('TELEGRAM_MINI_APP', 'buna', 1, 21, 1),
('WEB', 'pharmacy mariam', 3, 12, 1),
('WEB', '24 hour vet clinic', 1, NULL, 0),
('TELEGRAM_MINI_APP', 'notary office bulbula', 1, NULL, 0);

INSERT INTO public_reports (target_type, branch_id, business_id, issue_category, details_text, workflow_status, reporter_contact_email) VALUES
('BRANCH_LISTING', 7, 6, 'WRONG_HOURS', 'Tesfa Garage opens at 8:00 AM Monday through Saturday.', 'OPEN', 'visitor@example.com');

INSERT INTO audit_logs (actor_type, actor_staff_id, action_code, entity_type, entity_id, after_state_json, reason_notes) VALUES
('STAFF', 2, 'PERMISSION_GRANTED', 'business_permission', 1, '{\"business_id\":1,\"status\":\"GRANTED\",\"method\":\"SIGNED_FORM\"}', 'Recorded signed consent form from Dr. Solomon Mengistu'),
('STAFF', 3, 'QC_VERIFICATION_PASSED', 'branch', 1, '{\"branch_id\":1,\"outcome\":\"PASSED\",\"is_verified\":1}', 'Second-reviewer QC passed for Birhan Medium Clinic Main Branch'),
('STAFF', 1, 'CAMPAIGN_APPROVED', 'sponsorship_campaign', 1, '{\"campaign_code\":\"SPON-2026-001\",\"status\":\"ACTIVE\"}', 'Approved V1 Pilot Homepage Spotlight');
");

echo "SQLite database initialized successfully at: {$dbPath}\n";
