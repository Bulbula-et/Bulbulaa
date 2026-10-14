-- ============================================================================
-- BULBULA.ET V1 — PRODUCTION MYSQL 8.0 / MARIADB 10.6+ SCHEMA
-- Scope Reference: Product Feature Catalogue V1 (C-01 to C-40)
-- Engine: InnoDB | Charset: utf8mb4_unicode_ci (Supports English + Amharic)
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- 0. SYSTEM GATES & READINESS REGISTER (Page 14: D-04 to D-45)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS system_gates (
    gate_key            VARCHAR(64) NOT NULL PRIMARY KEY,
    decision_ref        VARCHAR(32) NOT NULL COMMENT 'e.g. D-39, D-45, D-40',
    is_enabled          TINYINT(1) NOT NULL DEFAULT 0,
    config_json         JSON NULL,
    updated_by_staff_id BIGINT UNSIGNED NULL,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 1. STAFF ACCOUNTS & STRONG AUTHENTICATION (C-19..C-29, Gate D-45)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS staff_users (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email               VARCHAR(190) NOT NULL UNIQUE,
    full_name           VARCHAR(120) NOT NULL,
    role                ENUM('OPERATOR', 'ADMINISTRATOR') NOT NULL DEFAULT 'OPERATOR',
    password_hash       VARCHAR(255) NOT NULL COMMENT 'Argon2id / Bcrypt hash',
    totp_secret_enc      VARCHAR(255) NULL COMMENT 'Encrypted RFC 6238 TOTP secret (D-45)',
    totp_enabled        TINYINT(1) NOT NULL DEFAULT 0,
    is_active           TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at       DATETIME NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_staff_role_active (role, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2. TAXONOMY: TWO-LEVEL CATEGORIES & CONTROLLED ALIASES (C-04, C-23)
-- Rule: Exactly two levels (Category -> Subcategory). Treated as reference data.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id           INT UNSIGNED NULL COMMENT 'NULL = Level 1 Category; NOT NULL = Level 2 Subcategory',
    depth_level         TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1 = Category, 2 = Subcategory (enforced)',
    slug                VARCHAR(120) NOT NULL UNIQUE,
    name_en             VARCHAR(120) NOT NULL,
    name_am             VARCHAR(160) NULL COMMENT 'Optional curated Amharic label (C-04)',
    description_en      VARCHAR(500) NULL,
    icon_key            VARCHAR(64) NULL COMMENT 'SVG icon identifier',
    display_order       INT NOT NULL DEFAULT 0,
    is_active           TINYINT(1) NOT NULL DEFAULT 1,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE RESTRICT,
    CONSTRAINT chk_category_depth CHECK (
        (parent_id IS NULL AND depth_level = 1) OR
        (parent_id IS NOT NULL AND depth_level = 2)
    ),
    INDEX idx_categories_parent_active (parent_id, is_active, display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS category_aliases (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id         INT UNSIGNED NOT NULL,
    alias_term          VARCHAR(120) NOT NULL,
    language_code       ENUM('en', 'am', 'translit') NOT NULL DEFAULT 'en',
    CONSTRAINT fk_cat_aliases_cat FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    UNIQUE KEY uq_category_alias (category_id, alias_term),
    INDEX idx_cat_alias_lookup (alias_term)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 3. LOCATIONS & CONTROLLED AREAS (C-05, C-24, Gate D-40)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS areas (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug                VARCHAR(120) NOT NULL UNIQUE,
    name_en             VARCHAR(120) NOT NULL COMMENT 'e.g. Bole Bulbula',
    name_am             VARCHAR(160) NULL,
    sub_city_en         VARCHAR(120) NOT NULL COMMENT 'e.g. Bole',
    sub_city_am         VARCHAR(160) NULL,
    center_latitude     DECIMAL(10, 7) NULL,
    center_longitude    DECIMAL(10, 7) NULL,
    is_launch_area      TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Controlled by D-40 launch boundary',
    is_active           TINYINT(1) NOT NULL DEFAULT 1,
    display_order       INT NOT NULL DEFAULT 0,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_areas_active_order (is_active, display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS area_aliases (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    area_id             INT UNSIGNED NOT NULL,
    alias_term          VARCHAR(120) NOT NULL,
    language_code       ENUM('en', 'am', 'translit') NOT NULL DEFAULT 'en',
    CONSTRAINT fk_area_aliases_area FOREIGN KEY (area_id) REFERENCES areas(id) ON DELETE CASCADE,
    UNIQUE KEY uq_area_alias (area_id, alias_term),
    INDEX idx_area_alias_lookup (alias_term)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 4. BUSINESSES (Brand-Level Facts Only — Page 3, C-08, C-19, C-20)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS businesses (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug                    VARCHAR(160) NOT NULL UNIQUE,
    name_en                 VARCHAR(180) NOT NULL,
    name_am                 VARCHAR(180) NULL,
    primary_category_id     INT UNSIGNED NOT NULL COMMENT 'Every listing has exactly 1 Primary Category (C-04)',
    brand_summary_en        VARCHAR(500) NULL,
    brand_description_en    TEXT NULL,
    website_url             VARCHAR(350) NULL COMMENT 'Brand-level website (C-11)',
    lifecycle_status        ENUM('PROSPECT', 'DRAFT', 'PUBLISHED', 'UNPUBLISHED', 'CLOSED', 'ARCHIVED') NOT NULL DEFAULT 'PROSPECT',
    created_by_staff_id     BIGINT UNSIGNED NOT NULL,
    updated_by_staff_id     BIGINT UNSIGNED NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_biz_primary_cat FOREIGN KEY (primary_category_id) REFERENCES categories(id) ON DELETE RESTRICT,
    CONSTRAINT fk_biz_created_staff FOREIGN KEY (created_by_staff_id) REFERENCES staff_users(id) ON DELETE RESTRICT,
    INDEX idx_biz_status_cat (lifecycle_status, primary_category_id),
    FULLTEXT INDEX ft_biz_names (name_en, name_am, brand_summary_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Secondary Categories (Zero or more, no duplicate with Primary enforced in service + unique key)
CREATE TABLE IF NOT EXISTS business_secondary_categories (
    business_id             BIGINT UNSIGNED NOT NULL,
    category_id             INT UNSIGNED NOT NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (business_id, category_id),
    CONSTRAINT fk_bsc_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    CONSTRAINT fk_bsc_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT,
    INDEX idx_bsc_category (category_id, business_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 5. BUSINESS PERMISSION RECORDS (Hard Publication Gate — C-19, C-21)
-- Rule: No business listing may be published without a linked GRANTED Permission.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS business_permissions (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id             BIGINT UNSIGNED NOT NULL,
    status                  ENUM('PENDING', 'GRANTED', 'REVOKED', 'EXPIRED') NOT NULL DEFAULT 'PENDING',
    rep_full_name           VARCHAR(160) NOT NULL COMMENT 'Authorising business representative',
    rep_role_title          VARCHAR(120) NOT NULL COMMENT 'e.g. Owner, General Manager',
    rep_phone               VARCHAR(40) NOT NULL,
    rep_email               VARCHAR(190) NULL,
    permission_method       ENUM('SIGNED_FORM', 'RECORDED_VISIT', 'VERIFIED_EMAIL', 'VERIFIED_PHONE') NOT NULL,
    evidence_storage_path   VARCHAR(350) NULL COMMENT 'Stored outside public_html in storage/permissions_private/',
    scope_notes             TEXT NULL,
    collected_by_staff_id   BIGINT UNSIGNED NOT NULL,
    granted_at              DATETIME NULL,
    revoked_at              DATETIME NULL,
    revocation_reason       VARCHAR(500) NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_perm_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE RESTRICT,
    CONSTRAINT fk_perm_staff FOREIGN KEY (collected_by_staff_id) REFERENCES staff_users(id) ON DELETE RESTRICT,
    INDEX idx_perm_business_status (business_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 6. BRANCHES (Physical & Operating Facts — Page 3, C-05..C-12, C-19..C-21)
-- Rule: Address, Area, Sub-city, coordinates, phone, branch email, hours,
--       reviews, and location analytics belong strictly to Branch.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS branches (
    id                          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id                 BIGINT UNSIGNED NOT NULL,
    active_permission_id        BIGINT UNSIGNED NULL COMMENT 'Required before publication_status = PUBLISHED',
    branch_slug                 VARCHAR(180) NOT NULL UNIQUE,
    branch_name_en              VARCHAR(180) NOT NULL COMMENT 'e.g. Bole Bulbula Main Branch',
    branch_name_am              VARCHAR(180) NULL,
    is_primary_branch           TINYINT(1) NOT NULL DEFAULT 1,
    area_id                     INT UNSIGNED NOT NULL,
    sub_city_en                 VARCHAR(120) NOT NULL,
    address_line_en             VARCHAR(300) NOT NULL,
    landmark_notes_en           VARCHAR(300) NULL COMMENT 'Local wayfinding context in Addis Ababa',
    latitude                    DECIMAL(10, 7) NULL COMMENT 'Used for C-07 Nearby discovery & C-10 Maps',
    longitude                   DECIMAL(10, 7) NULL,
    phone_primary               VARCHAR(40) NULL COMMENT 'Branch-level phone (C-11)',
    phone_secondary             VARCHAR(40) NULL,
    branch_email                VARCHAR(190) NULL COMMENT 'Branch-level email (C-11)',
    opening_hours_status        ENUM('KNOWN', 'UNKNOWN', 'BY_APPOINTMENT') NOT NULL DEFAULT 'UNKNOWN' COMMENT 'C-09: never guess when unknown',
    opening_hours_json          JSON NULL COMMENT 'Structured weekly schedule when KNOWN (D-04)',
    services_products_json      JSON NULL COMMENT 'Structured branch offerings (D-44)',
    publication_status          ENUM('DRAFT', 'PENDING_QC', 'PUBLISHED', 'TEMPORARILY_CLOSED', 'PERMANENTLY_CLOSED', 'UNPUBLISHED') NOT NULL DEFAULT 'DRAFT',
    is_verified                 TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'C-12: Operational trust indicator (never sold)',
    last_verified_at            DATETIME NULL,
    last_verified_by_staff_id   BIGINT UNSIGNED NULL,
    published_at                DATETIME NULL,
    -- Denormalized read-performance counters (recomputed transactionally on review moderation C-13)
    published_rating_avg        DECIMAL(3, 2) NULL COMMENT 'Unweighted mean of PUBLISHED reviews; NULL if 0 reviews',
    published_review_count      INT UNSIGNED NOT NULL DEFAULT 0,
    created_by_staff_id         BIGINT UNSIGNED NOT NULL,
    updated_at                  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at                  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_branch_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE RESTRICT,
    CONSTRAINT fk_branch_permission FOREIGN KEY (active_permission_id) REFERENCES business_permissions(id) ON DELETE RESTRICT,
    CONSTRAINT fk_branch_area FOREIGN KEY (area_id) REFERENCES areas(id) ON DELETE RESTRICT,
    INDEX idx_branch_pub_area (publication_status, area_id),
    INDEX idx_branch_pub_coords (publication_status, latitude, longitude),
    INDEX idx_branch_business (business_id, publication_status),
    FULLTEXT INDEX ft_branch_search (branch_name_en, branch_name_am, address_line_en, landmark_notes_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 7. VERIFICATION & QUALITY CONTROL LOGS (C-21, Gate D-08)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS verification_reviews (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    branch_id               BIGINT UNSIGNED NOT NULL,
    permission_id           BIGINT UNSIGNED NOT NULL,
    submitted_by_staff_id   BIGINT UNSIGNED NOT NULL,
    reviewed_by_staff_id    BIGINT UNSIGNED NOT NULL COMMENT 'Second reviewer where available (C-21)',
    outcome                 ENUM('PASSED', 'RETURNED_FOR_FIXES', 'REJECTED') NOT NULL,
    checklist_json          JSON NOT NULL COMMENT 'Records permission check, coordinates check, category check',
    reviewer_notes          TEXT NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_qc_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE,
    CONSTRAINT fk_qc_permission FOREIGN KEY (permission_id) REFERENCES business_permissions(id) ON DELETE RESTRICT,
    CONSTRAINT fk_qc_submitter FOREIGN KEY (submitted_by_staff_id) REFERENCES staff_users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_qc_reviewer FOREIGN KEY (reviewed_by_staff_id) REFERENCES staff_users(id) ON DELETE RESTRICT,
    INDEX idx_qc_branch_outcome (branch_id, outcome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 8. PERMITTED MEDIA ASSETS (C-22)
-- Rule: Attached to either Business (brand logo/cover) or specific Branch.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS media_assets (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id             BIGINT UNSIGNED NOT NULL,
    branch_id               BIGINT UNSIGNED NULL COMMENT 'NULL = Brand-level asset; NOT NULL = Branch-specific photo',
    permission_id           BIGINT UNSIGNED NOT NULL,
    media_type              ENUM('LOGO', 'COVER', 'GALLERY_PHOTO', 'INTERIOR', 'EXTERIOR') NOT NULL DEFAULT 'GALLERY_PHOTO',
    file_path               VARCHAR(300) NOT NULL COMMENT 'Relative to /media/ in public_html',
    width_px                SMALLINT UNSIGNED NOT NULL,
    height_px               SMALLINT UNSIGNED NOT NULL,
    file_size_bytes         INT UNSIGNED NOT NULL,
    alt_text_en             VARCHAR(220) NOT NULL,
    display_order           SMALLINT NOT NULL DEFAULT 0,
    is_active               TINYINT(1) NOT NULL DEFAULT 1,
    uploaded_by_staff_id    BIGINT UNSIGNED NOT NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_media_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    CONSTRAINT fk_media_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE,
    CONSTRAINT fk_media_permission FOREIGN KEY (permission_id) REFERENCES business_permissions(id) ON DELETE RESTRICT,
    INDEX idx_media_branch_active (branch_id, is_active, display_order),
    INDEX idx_media_biz_active (business_id, is_active, display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 9. CUSTOMER ACCOUNTS & PASSWORD-FREE AUTH (C-30..C-33, C-36)
-- Rule: Google Auth + Email OTP only. Unified identity across Web & Telegram.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS customers (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email                   VARCHAR(190) NOT NULL UNIQUE,
    display_name            VARCHAR(100) NOT NULL,
    google_sub_id           VARCHAR(128) NULL UNIQUE COMMENT 'Verified server-side Google subject ID (C-30)',
    account_status          ENUM('ACTIVE', 'SUSPENDED', 'PENDING_DELETION', 'DELETED') NOT NULL DEFAULT 'ACTIVE',
    email_verified_at       DATETIME NULL,
    last_active_surface     ENUM('WEB', 'TELEGRAM_MINI_APP') NOT NULL DEFAULT 'WEB',
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_otp_tokens (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email                   VARCHAR(190) NOT NULL,
    code_hmac_sha256        CHAR(64) NOT NULL COMMENT 'C-31: Stored as hash, never plain text',
    attempt_count           TINYINT UNSIGNED NOT NULL DEFAULT 0,
    requester_ip_hash       CHAR(64) NOT NULL,
    expires_at              DATETIME NOT NULL,
    consumed_at             DATETIME NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_otp_email_expires (email, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 10. SAVED BUSINESSES (C-14, C-34)
-- Rule: Shared across Web and Telegram Mini App under unified Customer identity.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS customer_saved_businesses (
    customer_id             BIGINT UNSIGNED NOT NULL,
    business_id             BIGINT UNSIGNED NOT NULL,
    preferred_branch_id     BIGINT UNSIGNED NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (customer_id, business_id),
    CONSTRAINT fk_save_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    CONSTRAINT fk_save_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    CONSTRAINT fk_save_branch FOREIGN KEY (preferred_branch_id) REFERENCES branches(id) ON DELETE SET NULL,
    INDEX idx_save_customer_created (customer_id, created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 11. BRANCH REVIEWS & PRE-PUBLICATION MODERATION (C-13, C-25, C-35)
-- Rules:
--   - Belongs strictly to a Branch.
--   - Rating is integer 1..5; written text is optional.
--   - One active Review per Customer per Branch.
--   - Editable within 30 days of initial submission (re-enters PENDING_MODERATION).
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS reviews (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    branch_id               BIGINT UNSIGNED NOT NULL,
    customer_id             BIGINT UNSIGNED NOT NULL,
    -- Generates NULL when WITHDRAWN/REMOVED so uq_active_review_per_branch permits only 1 active review
    active_slot             TINYINT GENERATED ALWAYS AS (
                                CASE WHEN status IN ('PENDING_MODERATION', 'PUBLISHED', 'REJECTED') THEN 1 ELSE NULL END
                            ) STORED,
    rating                  TINYINT UNSIGNED NOT NULL COMMENT 'Required integer 1 to 5',
    review_text             TEXT NULL COMMENT 'Optional written review text',
    status                  ENUM('PENDING_MODERATION', 'PUBLISHED', 'REJECTED', 'WITHDRAWN_BY_AUTHOR', 'REMOVED_BY_STAFF') NOT NULL DEFAULT 'PENDING_MODERATION',
    edit_count              TINYINT UNSIGNED NOT NULL DEFAULT 0,
    first_submitted_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_edited_at          DATETIME NULL,
    moderated_by_staff_id   BIGINT UNSIGNED NULL,
    moderated_at            DATETIME NULL,
    moderation_reason_code  VARCHAR(64) NULL,
    moderation_notes         VARCHAR(500) NULL,
    withdrawn_at            DATETIME NULL,
    CONSTRAINT fk_review_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE RESTRICT,
    CONSTRAINT fk_review_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
    CONSTRAINT fk_review_moderator FOREIGN KEY (moderated_by_staff_id) REFERENCES staff_users(id) ON DELETE SET NULL,
    CONSTRAINT chk_review_rating CHECK (rating BETWEEN 1 AND 5),
    UNIQUE KEY uq_one_active_review_per_customer_branch (branch_id, customer_id, active_slot),
    INDEX idx_reviews_branch_published (branch_id, status, first_submitted_at DESC),
    INDEX idx_reviews_moderation_queue (status, first_submitted_at ASC),
    INDEX idx_reviews_customer (customer_id, first_submitted_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 12. PUBLIC REPORTS & CORRECTION SUGGESTIONS (C-15, C-26)
-- Rule: Guests can report listing errors; Review reports require authenticated Customer.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public_reports (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    target_type             ENUM('BRANCH_LISTING', 'BUSINESS_LISTING', 'REVIEW') NOT NULL,
    branch_id               BIGINT UNSIGNED NULL,
    business_id             BIGINT UNSIGNED NULL,
    review_id               BIGINT UNSIGNED NULL,
    reporter_customer_id    BIGINT UNSIGNED NULL COMMENT 'Required when target_type = REVIEW (C-15)',
    reporter_contact_email  VARCHAR(190) NULL COMMENT 'Optional for Guest listing correction reports',
    issue_category          ENUM('WRONG_HOURS', 'WRONG_PHONE', 'WRONG_LOCATION', 'CLOSED_PERMANENTLY', 'DUPLICATE', 'REVIEW_POLICY_VIOLATION', 'LEGAL_CONCERN', 'OTHER') NOT NULL,
    details_text            TEXT NOT NULL,
    workflow_status         ENUM('OPEN', 'IN_REVIEW', 'ESCALATED_LEGAL', 'RESOLVED_UPDATED', 'DISMISSED') NOT NULL DEFAULT 'OPEN',
    handled_by_staff_id     BIGINT UNSIGNED NULL,
    resolution_notes        TEXT NULL,
    resolved_at             DATETIME NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_report_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL,
    CONSTRAINT fk_report_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE SET NULL,
    CONSTRAINT fk_report_review FOREIGN KEY (review_id) REFERENCES reviews(id) ON DELETE SET NULL,
    CONSTRAINT fk_report_customer FOREIGN KEY (reporter_customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
    CONSTRAINT chk_review_report_auth CHECK (
        (target_type != 'REVIEW') OR (reporter_customer_id IS NOT NULL)
    ),
    INDEX idx_reports_queue (workflow_status, target_type, created_at ASC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 13. STAFF-MANAGED SPONSORSHIP CAMPAIGNS (C-16, C-27, Section 07)
-- Rule: Only 3 approved formats. Staff creates -> Admin approves -> Time-bounded.
--       Impressions and clicks only. Never alters organic ranking or verification.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sponsorship_campaigns (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_code           VARCHAR(40) NOT NULL UNIQUE COMMENT 'Offline agreement reference code',
    business_id             BIGINT UNSIGNED NOT NULL,
    branch_id               BIGINT UNSIGNED NOT NULL,
    placement_format        ENUM('SEARCH_PLACEMENT', 'CATEGORY_SPONSORSHIP', 'HOMEPAGE_PROMOTION') NOT NULL,
    target_category_id      INT UNSIGNED NULL COMMENT 'Required for SEARCH_PLACEMENT and CATEGORY_SPONSORSHIP',
    target_area_id          INT UNSIGNED NULL COMMENT 'Optional area filter within category-by-area pages',
    package_name            VARCHAR(120) NOT NULL,
    agreed_price_etb        DECIMAL(12, 2) NOT NULL COMMENT 'Fixed package price in ETB (C-27)',
    starts_at               DATETIME NOT NULL,
    ends_at                 DATETIME NOT NULL,
    status                  ENUM('DRAFT', 'PENDING_ADMIN_APPROVAL', 'APPROVED_SCHEDULED', 'ACTIVE', 'COMPLETED', 'TERMINATED_EARLY') NOT NULL DEFAULT 'DRAFT',
    created_by_staff_id     BIGINT UNSIGNED NOT NULL,
    approved_by_admin_id    BIGINT UNSIGNED NULL COMMENT 'Must be ADMINISTRATOR role (C-27)',
    approved_at             DATETIME NULL,
    terminated_by_admin_id  BIGINT UNSIGNED NULL,
    terminated_at           DATETIME NULL,
    termination_reason      VARCHAR(500) NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_camp_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE RESTRICT,
    CONSTRAINT fk_camp_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE RESTRICT,
    CONSTRAINT fk_camp_category FOREIGN KEY (target_category_id) REFERENCES categories(id) ON DELETE RESTRICT,
    CONSTRAINT fk_camp_area FOREIGN KEY (target_area_id) REFERENCES areas(id) ON DELETE RESTRICT,
    CONSTRAINT fk_camp_creator FOREIGN KEY (created_by_staff_id) REFERENCES staff_users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_camp_approver FOREIGN KEY (approved_by_admin_id) REFERENCES staff_users(id) ON DELETE RESTRICT,
    CONSTRAINT chk_camp_dates CHECK (ends_at > starts_at),
    INDEX idx_camp_active_lookup (status, placement_format, target_category_id, starts_at, ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sponsorship_daily_metrics (
    campaign_id             BIGINT UNSIGNED NOT NULL,
    metric_date             DATE NOT NULL,
    surface                 ENUM('WEB', 'TELEGRAM_MINI_APP') NOT NULL,
    impressions_count       INT UNSIGNED NOT NULL DEFAULT 0,
    clicks_count            INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (campaign_id, metric_date, surface),
    CONSTRAINT fk_camp_metrics FOREIGN KEY (campaign_id) REFERENCES sponsorship_campaigns(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 14. SERVER-SIDE OPERATIONAL ANALYTICS (C-28, C-38)
-- Rule: First-party server-side events only. No third-party ad-tech or PII.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS search_query_logs (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    surface                 ENUM('WEB', 'TELEGRAM_MINI_APP') NOT NULL DEFAULT 'WEB',
    query_normalized        VARCHAR(160) NOT NULL,
    area_id                 INT UNSIGNED NULL,
    category_id             INT UNSIGNED NULL,
    results_count           SMALLINT UNSIGNED NOT NULL,
    is_zero_result          TINYINT(1) GENERATED ALWAYS AS (IF(results_count = 0, 1, 0)) STORED,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_search_zero_results (is_zero_result, created_at DESC),
    INDEX idx_search_term_freq (query_normalized, created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS branch_daily_analytics (
    branch_id               BIGINT UNSIGNED NOT NULL,
    metric_date             DATE NOT NULL,
    surface                 ENUM('WEB', 'TELEGRAM_MINI_APP') NOT NULL,
    profile_views           INT UNSIGNED NOT NULL DEFAULT 0,
    action_call_clicks      INT UNSIGNED NOT NULL DEFAULT 0,
    action_map_clicks       INT UNSIGNED NOT NULL DEFAULT 0,
    action_website_clicks   INT UNSIGNED NOT NULL DEFAULT 0,
    action_email_clicks     INT UNSIGNED NOT NULL DEFAULT 0,
    action_share_clicks     INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (branch_id, metric_date, surface),
    CONSTRAINT fk_bda_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 15. PRIVACY & ACCOUNT DELETION REQUESTS (C-36, C-40)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS privacy_requests (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id             BIGINT UNSIGNED NULL,
    requester_email         VARCHAR(190) NOT NULL,
    request_type            ENUM('ACCOUNT_DELETION', 'DATA_ACCESS', 'LISTING_TAKEDOWN_INQUIRY') NOT NULL,
    status                  ENUM('SUBMITTED', 'IN_PROGRESS', 'COMPLETED', 'REJECTED_WITH_REASON') NOT NULL DEFAULT 'SUBMITTED',
    handled_by_staff_id     BIGINT UNSIGNED NULL,
    resolution_summary      TEXT NULL,
    completed_at            DATETIME NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_priv_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    CONSTRAINT fk_priv_staff FOREIGN KEY (handled_by_staff_id) REFERENCES staff_users(id) ON DELETE SET NULL,
    INDEX idx_priv_status (status, created_at ASC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 16. APPEND-ONLY AUDIT LOGS (C-29)
-- Rule: Restricted to Administrators. Never stores credentials, OTPs, or session IDs.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_logs (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_type              ENUM('STAFF', 'CUSTOMER', 'SYSTEM') NOT NULL,
    actor_staff_id          BIGINT UNSIGNED NULL,
    actor_customer_id       BIGINT UNSIGNED NULL,
    action_code             VARCHAR(80) NOT NULL COMMENT 'e.g. BRANCH_PUBLISHED, PERMISSION_GRANTED, REVIEW_MODERATED, CAMPAIGN_APPROVED',
    entity_type             VARCHAR(60) NOT NULL COMMENT 'e.g. branch, business, review, campaign, category',
    entity_id               BIGINT UNSIGNED NOT NULL,
    before_state_json       JSON NULL,
    after_state_json        JSON NULL,
    reason_notes            VARCHAR(500) NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_entity (entity_type, entity_id, created_at DESC),
    INDEX idx_audit_staff (actor_staff_id, created_at DESC),
    INDEX idx_audit_action (action_code, created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
