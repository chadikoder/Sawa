-- =============================================================================
-- Sawa — Complete database schema (shared-hosting safe MySQL / MariaDB)
-- © 2026 Chadi Ikhoder — PolyForm Noncommercial 1.0.0
--
-- HOW TO IMPORT (phpMyAdmin / shared hosting like InfinityFree, Namecheap):
--   1. In your hosting panel, create an empty database (e.g. "sawa" or the
--      prefixed name your host assigns, such as epiz_123_sawa).
--   2. Open phpMyAdmin, select that database in the left sidebar.
--   3. Go to the "Import" tab, choose this file (database_complete.sql),
--      then click "Go". Do NOT add a CREATE DATABASE / USE line — this file
--      intentionally omits them so it works with prefixed shared-hosting DBs.
--   4. Point the app at the DB via environment variables / .env
--      (SAWA_DB_HOST, SAWA_DB_NAME, SAWA_DB_USER, SAWA_DB_PASS).
--
-- Seeded logins (CHANGE THESE PASSWORDS IN PRODUCTION):
--   admin@sawa.local        / Admin123      (role: admin)
--   donor@sawa.local        / Demo123!      (role: user)
--   beneficiary@sawa.local  / Demo123!      (role: beneficiary)
--   org@sawa.local          / Demo123!      (role: organisation, verified)
--
-- This file is idempotent: re-importing will not duplicate tables or seed rows.
-- =============================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

-- =============================================================================
-- 1. AUTH DOMAIN
-- =============================================================================

CREATE TABLE IF NOT EXISTS users (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email           VARCHAR(191) NULL,
    phone           VARCHAR(40) NULL,
    password_hash   VARCHAR(255) NOT NULL,
    full_name       VARCHAR(120) NOT NULL,
    role            ENUM('user', 'beneficiary', 'organisation', 'admin') NOT NULL DEFAULT 'user',
    email_verified  TINYINT(1) NOT NULL DEFAULT 0,
    active          TINYINT(1) NOT NULL DEFAULT 1,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role),
    KEY idx_users_active_email (active, email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_profiles (
    user_id         BIGINT UNSIGNED NOT NULL,
    bio             VARCHAR(250) NULL,
    location        VARCHAR(80) NULL,
    birthdate       DATE NULL,
    -- All four values the signup form offers. Listing only Male/Female made
    -- php/auth/signup.php fail on the other two: strict mode (MySQL 8's
    -- default) raises "Data truncated for column 'gender'", the signup
    -- transaction rolls back and the user is bounced with ?error=server.
    gender          ENUM('Male', 'Female', 'Other', 'Prefer not to say') NULL,
    avatar_path     VARCHAR(500) NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    CONSTRAINT fk_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS organisations (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id             BIGINT UNSIGNED NOT NULL,
    name                VARCHAR(200) NOT NULL,
    description         TEXT NULL,
    phone               VARCHAR(40) NULL,
    address             VARCHAR(255) NULL,
    website             VARCHAR(255) NULL,
    logo_path           VARCHAR(500) NULL,
    registration_doc    VARCHAR(500) NULL,
    verified            TINYINT(1) NOT NULL DEFAULT 0,
    verified_at         DATETIME NULL,
    verified_by         BIGINT UNSIGNED NULL,
    rejected            TINYINT(1) NOT NULL DEFAULT 0,
    rejection_reason    TEXT NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_org_user (user_id),
    KEY idx_org_verified (verified),
    CONSTRAINT fk_org_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_org_verified_by FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     BIGINT UNSIGNED NOT NULL,
    token       VARCHAR(128) NOT NULL,
    expires_at  DATETIME NOT NULL,
    used        TINYINT(1) NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_reset_token (token),
    KEY idx_reset_user (user_id),
    CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_verifications (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     BIGINT UNSIGNED NOT NULL,
    token       VARCHAR(128) NOT NULL,
    expires_at  DATETIME NOT NULL,
    used        TINYINT(1) NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_verify_token (token),
    KEY idx_verify_user (user_id),
    CONSTRAINT fk_verify_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email           VARCHAR(191) NOT NULL,
    ip_address      VARCHAR(45) NOT NULL,
    success         TINYINT(1) NOT NULL,
    user_agent      VARCHAR(500) NULL,
    attempted_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_login_email_time (email, attempted_at),
    KEY idx_login_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 2. LOOKUPS DOMAIN (must exist before campaigns)
-- =============================================================================

CREATE TABLE IF NOT EXISTS categories (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug        VARCHAR(60) NOT NULL,
    name_en     VARCHAR(120) NOT NULL,
    name_ar     VARCHAR(120) NOT NULL,
    icon        VARCHAR(120) NULL,
    sort_order  INT NOT NULL DEFAULT 0,
    active      TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS locations (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug        VARCHAR(60) NOT NULL,
    name_en     VARCHAR(120) NOT NULL,
    name_ar     VARCHAR(120) NOT NULL,
    region      VARCHAR(80) NULL,
    sort_order  INT NOT NULL DEFAULT 0,
    active      TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_locations_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 3. CAMPAIGNS DOMAIN
-- =============================================================================

CREATE TABLE IF NOT EXISTS campaigns (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    organisation_id     BIGINT UNSIGNED NULL,
    owner_user_id       BIGINT UNSIGNED NULL,
    title               VARCHAR(200) NOT NULL,
    summary             VARCHAR(500) NULL,
    description         TEXT NOT NULL,
    goal_amount         DECIMAL(12,2) NOT NULL,
    raised_amount       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    currency            CHAR(3) NOT NULL DEFAULT 'USD',
    category_id         BIGINT UNSIGNED NULL,
    location_id         BIGINT UNSIGNED NULL,
    cover_image         VARCHAR(500) NULL,
    status              ENUM('draft','pending','active','paused','completed','rejected') NOT NULL DEFAULT 'pending',
    rejection_reason    TEXT NULL,
    starts_at           DATETIME NULL,
    ends_at             DATETIME NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_campaigns_status (status),
    KEY idx_campaigns_category (category_id),
    KEY idx_campaigns_location (location_id),
    KEY idx_campaigns_org (organisation_id),
    KEY idx_campaigns_owner (owner_user_id),
    KEY idx_campaigns_active_created (status, created_at),
    CONSTRAINT fk_campaigns_org FOREIGN KEY (organisation_id) REFERENCES organisations(id) ON DELETE CASCADE,
    CONSTRAINT fk_campaigns_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_campaigns_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    CONSTRAINT fk_campaigns_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS campaign_images (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    campaign_id     BIGINT UNSIGNED NOT NULL,
    image_path      VARCHAR(500) NOT NULL,
    caption         VARCHAR(255) NULL,
    sort_order      INT NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_camp_images (campaign_id, sort_order),
    CONSTRAINT fk_camp_images_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS campaign_updates (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    campaign_id     BIGINT UNSIGNED NOT NULL,
    posted_by       BIGINT UNSIGNED NULL,
    title           VARCHAR(200) NOT NULL,
    body            TEXT NOT NULL,
    image_path      VARCHAR(500) NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_camp_updates (campaign_id, created_at),
    CONSTRAINT fk_camp_updates_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    CONSTRAINT fk_camp_updates_user FOREIGN KEY (posted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS saved_campaigns (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         BIGINT UNSIGNED NOT NULL,
    campaign_id     BIGINT UNSIGNED NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_saved (user_id, campaign_id),
    KEY idx_saved_user (user_id),
    CONSTRAINT fk_saved_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_saved_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 4. MONEY DOMAIN
-- =============================================================================

CREATE TABLE IF NOT EXISTS donations (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    campaign_id     BIGINT UNSIGNED NOT NULL,
    donor_id        BIGINT UNSIGNED NULL,
    guest_name      VARCHAR(120) NULL,
    guest_email     VARCHAR(255) NULL,
    guest_phone     VARCHAR(40) NULL,
    amount          DECIMAL(12,2) NOT NULL,
    fee_amount      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total_charged   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    currency        CHAR(3) NOT NULL DEFAULT 'USD',
    status          ENUM('pending','verified','completed','refunded','failed') NOT NULL DEFAULT 'pending',
    payment_method  ENUM('whish','hosted_checkout','wallet') NOT NULL DEFAULT 'whish',
    anonymous       TINYINT(1) NOT NULL DEFAULT 0,
    message         VARCHAR(500) NULL,
    payment_ref     VARCHAR(120) NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    verified_at     DATETIME NULL,
    completed_at    DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_donations_campaign (campaign_id),
    KEY idx_donations_donor (donor_id),
    KEY idx_donations_status (status),
    KEY idx_donations_created (created_at),
    CONSTRAINT fk_donations_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE RESTRICT,
    CONSTRAINT fk_donations_donor FOREIGN KEY (donor_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS donation_status_history (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    donation_id     BIGINT UNSIGNED NOT NULL,
    from_status     VARCHAR(20) NULL,
    to_status       VARCHAR(20) NOT NULL,
    changed_by      BIGINT UNSIGNED NULL,
    notes           VARCHAR(500) NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_dsh_donation (donation_id, created_at),
    CONSTRAINT fk_dsh_donation FOREIGN KEY (donation_id) REFERENCES donations(id) ON DELETE CASCADE,
    CONSTRAINT fk_dsh_user FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wallet_transactions (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    organisation_id     BIGINT UNSIGNED NOT NULL,
    type                ENUM('credit','debit','withdrawal','adjustment') NOT NULL,
    amount              DECIMAL(12,2) NOT NULL,
    currency            CHAR(3) NOT NULL DEFAULT 'USD',
    balance_after       DECIMAL(12,2) NOT NULL,
    related_donation_id BIGINT UNSIGNED NULL,
    description         VARCHAR(255) NULL,
    created_by          BIGINT UNSIGNED NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_wallet_org_time (organisation_id, created_at),
    KEY idx_wallet_donation (related_donation_id),
    CONSTRAINT fk_wallet_org FOREIGN KEY (organisation_id) REFERENCES organisations(id) ON DELETE CASCADE,
    CONSTRAINT fk_wallet_donation FOREIGN KEY (related_donation_id) REFERENCES donations(id) ON DELETE SET NULL,
    CONSTRAINT fk_wallet_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_wallet_ledger (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         BIGINT UNSIGNED NOT NULL,
    type            ENUM('topup','donation','refund','cashout','adjustment') NOT NULL,
    amount          DECIMAL(12,2) NOT NULL,
    fee_amount      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    balance_after   DECIMAL(12,2) NOT NULL,
    related_donation_id BIGINT UNSIGNED NULL,
    description     VARCHAR(255) NULL,
    payment_ref     VARCHAR(120) NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_uwl_user_time (user_id, created_at),
    CONSTRAINT fk_uwl_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_uwl_donation FOREIGN KEY (related_donation_id) REFERENCES donations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cash_out_requests (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         BIGINT UNSIGNED NOT NULL,
    amount          DECIMAL(12,2) NOT NULL,
    fee_amount      DECIMAL(12,2) NOT NULL,
    net_amount      DECIMAL(12,2) NOT NULL,
    method          ENUM('whish','bank_card') NOT NULL,
    destination     VARCHAR(255) NOT NULL,
    status          ENUM('pending','processing','completed','failed','cancelled') NOT NULL DEFAULT 'pending',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at    DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_cashout_user (user_id, status),
    CONSTRAINT fk_cashout_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 5. ENGAGEMENT DOMAIN
-- =============================================================================

CREATE TABLE IF NOT EXISTS notifications (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     BIGINT UNSIGNED NOT NULL,
    type        VARCHAR(40) NOT NULL,
    title       VARCHAR(200) NOT NULL,
    body        TEXT NULL,
    link        VARCHAR(500) NULL,
    is_read     TINYINT(1) NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notif_user_time (user_id, created_at),
    KEY idx_notif_unread (user_id, is_read, created_at),
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comments (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    campaign_id     BIGINT UNSIGNED NOT NULL,
    user_id         BIGINT UNSIGNED NOT NULL,
    body            TEXT NOT NULL,
    deleted_at      DATETIME NULL,
    deleted_by      BIGINT UNSIGNED NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_comments_campaign (campaign_id, created_at),
    CONSTRAINT fk_comments_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    CONSTRAINT fk_comments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_comments_deleted_by FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reports (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    reporter_id     BIGINT UNSIGNED NULL,
    target_type     ENUM('campaign','comment','organisation','user') NOT NULL,
    target_id       BIGINT UNSIGNED NOT NULL,
    reason          VARCHAR(80) NOT NULL,
    details         TEXT NULL,
    status          ENUM('open','reviewing','resolved','dismissed') NOT NULL DEFAULT 'open',
    resolved_by     BIGINT UNSIGNED NULL,
    resolved_at     DATETIME NULL,
    resolution      TEXT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_reports_status (status, created_at),
    KEY idx_reports_target (target_type, target_id),
    CONSTRAINT fk_reports_reporter FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_reports_resolver FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 6. ADMIN DOMAIN
-- =============================================================================

CREATE TABLE IF NOT EXISTS audit_log (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_id        BIGINT UNSIGNED NULL,
    action          VARCHAR(80) NOT NULL,
    target_type     VARCHAR(40) NOT NULL,
    target_id       BIGINT UNSIGNED NULL,
    details         TEXT NULL,
    ip_address      VARCHAR(45) NULL,
    user_agent      VARCHAR(500) NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_admin (admin_id, created_at),
    KEY idx_audit_target (target_type, target_id),
    KEY idx_audit_action (action, created_at),
    CONSTRAINT fk_audit_admin FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 7. MESSAGING DOMAIN
-- =============================================================================

CREATE TABLE IF NOT EXISTS message_threads (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_a_id       BIGINT UNSIGNED NOT NULL,
    user_b_id       BIGINT UNSIGNED NOT NULL,
    last_message_at DATETIME NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_thread_pair (user_a_id, user_b_id),
    KEY idx_threads_last (last_message_at),
    CONSTRAINT fk_thread_a FOREIGN KEY (user_a_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_thread_b FOREIGN KEY (user_b_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS messages (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    thread_id       BIGINT UNSIGNED NOT NULL,
    sender_id       BIGINT UNSIGNED NOT NULL,
    body            TEXT NOT NULL,
    attachment_path VARCHAR(500) NULL,
    read_at         DATETIME NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_messages_thread (thread_id, created_at),
    CONSTRAINT fk_messages_thread FOREIGN KEY (thread_id) REFERENCES message_threads(id) ON DELETE CASCADE,
    CONSTRAINT fk_messages_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 8. PAYMENTS & RECEIPTS DOMAIN
-- =============================================================================

CREATE TABLE IF NOT EXISTS payment_sessions (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         BIGINT UNSIGNED NULL,
    donation_id     BIGINT UNSIGNED NULL,
    purpose         ENUM('donation','wallet_topup') NOT NULL,
    amount          DECIMAL(12,2) NOT NULL,
    fee_amount      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total_amount    DECIMAL(12,2) NOT NULL,
    currency        CHAR(3) NOT NULL DEFAULT 'USD',
    provider        ENUM('whish','hosted_checkout','wallet') NOT NULL,
    provider_ref    VARCHAR(120) NULL,
    status          ENUM('pending','confirmed','failed','cancelled') NOT NULL DEFAULT 'pending',
    return_token    VARCHAR(64) NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at    DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payment_return (return_token),
    KEY idx_payment_status (status, created_at),
    CONSTRAINT fk_payment_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_payment_donation FOREIGN KEY (donation_id) REFERENCES donations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS receipts (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    bill_id         VARCHAR(32) NOT NULL,
    user_id         BIGINT UNSIGNED NULL,
    donation_id     BIGINT UNSIGNED NULL,
    payment_session_id BIGINT UNSIGNED NULL,
    recipient_label VARCHAR(200) NOT NULL,
    method_label    VARCHAR(80) NOT NULL,
    subtotal        DECIMAL(12,2) NOT NULL,
    fee_amount      DECIMAL(12,2) NOT NULL,
    total_paid      DECIMAL(12,2) NOT NULL,
    provider_ref    VARCHAR(120) NULL,
    checksum        VARCHAR(64) NOT NULL,
    -- Unguessable capability token for the guest receipt link. bill_id is
    -- sequential and therefore enumerable, so it can never be the only thing
    -- guarding a receipt. Members are authorised by session instead and their
    -- rows may leave this NULL.
    access_token    CHAR(64) NULL,
    pdf_path        VARCHAR(500) NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_receipt_bill (bill_id),
    UNIQUE KEY uq_receipt_token (access_token),
    KEY idx_receipt_user (user_id, created_at),
    CONSTRAINT fk_receipt_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_receipt_donation FOREIGN KEY (donation_id) REFERENCES donations(id) ON DELETE SET NULL,
    CONSTRAINT fk_receipt_payment FOREIGN KEY (payment_session_id) REFERENCES payment_sessions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Migration: user_profiles.gender
--
-- Widen the enum on databases created before 'Other' / 'Prefer not to say'
-- were listed. MODIFY is safe to re-run, but it rebuilds the table, so only
-- issue it when the definition is actually out of date.
-- -----------------------------------------------------------------------------
SET @gender_type := (SELECT COLUMN_TYPE FROM information_schema.COLUMNS
                      WHERE TABLE_SCHEMA = DATABASE()
                        AND TABLE_NAME = 'user_profiles'
                        AND COLUMN_NAME = 'gender');
SET @sql := IF(@gender_type IS NOT NULL AND @gender_type NOT LIKE '%Prefer not to say%',
    "ALTER TABLE user_profiles MODIFY COLUMN gender ENUM('Male','Female','Other','Prefer not to say') NULL",
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------------
-- Migration: receipts.access_token
--
-- The CREATE TABLE above only runs on a fresh database. A database imported
-- before this column existed keeps its old receipts table, so add the column
-- and its unique index separately. `ADD COLUMN IF NOT EXISTS` is MariaDB-only,
-- so this checks information_schema and builds the statement dynamically —
-- that form works on both MySQL 8 and MariaDB 10.4, and is safe to re-run.
-- -----------------------------------------------------------------------------
SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = 'receipts'
                    AND COLUMN_NAME = 'access_token');
SET @sql := IF(@has_col = 0,
    'ALTER TABLE receipts ADD COLUMN access_token CHAR(64) NULL AFTER checksum',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = 'receipts'
                    AND INDEX_NAME = 'uq_receipt_token');
SET @sql := IF(@has_idx = 0,
    'ALTER TABLE receipts ADD UNIQUE KEY uq_receipt_token (access_token)',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- 9. SEED DATA (idempotent — safe to re-run)
-- =============================================================================

INSERT INTO categories (slug, name_en, name_ar, sort_order) VALUES
('medical', 'Medical', 'طبي', 1),
('educational', 'Educational', 'تعليمي', 2),
('food', 'Food', 'غذاء', 3),
('shelter', 'Shelter', 'مأوى', 4),
('other', 'Other', 'أخرى', 99)
ON DUPLICATE KEY UPDATE name_en = VALUES(name_en), name_ar = VALUES(name_ar), sort_order = VALUES(sort_order);

INSERT INTO locations (slug, name_en, name_ar, region, sort_order) VALUES
('beirut', 'Beirut', 'بيروت', 'Beirut', 1),
('tripoli', 'Tripoli', 'طرابلس', 'North', 2),
('akkar', 'Akkar', 'عكار', 'North', 3),
('batroun', 'Batroun', 'البترون', 'North', 4),
('baalbek', 'Baalbek', 'بعلبك', 'Bekaa', 5),
('south_lb', 'South Lebanon', 'جنوب لبنان', 'South', 6)
ON DUPLICATE KEY UPDATE name_en = VALUES(name_en), name_ar = VALUES(name_ar), region = VALUES(region), sort_order = VALUES(sort_order);

-- Seed accounts. Passwords are REAL bcrypt hashes (work immediately on import):
--   admin@sawa.local       -> Admin123
--   donor/beneficiary/org  -> Demo123!
-- INSERT IGNORE skips rows whose unique email already exists (idempotent).
INSERT IGNORE INTO users (email, password_hash, full_name, role, email_verified, active) VALUES
('admin@sawa.local',       '$2y$12$RRepxZXAxR68dsSDyGrTEOZY2ywAfZ0iyiemTAdu4RchUDLu/bfg2', 'Sawa Admin',         'admin',        1, 1),
('donor@sawa.local',       '$2y$12$SptcKMAoqRtDQnfwmloOB.JNRdAMDWJQYebdJFthUnmJ22hiodUDe', 'Sawa Donor',         'user',         1, 1),
('beneficiary@sawa.local', '$2y$12$SptcKMAoqRtDQnfwmloOB.JNRdAMDWJQYebdJFthUnmJ22hiodUDe', 'Lina Recipient',     'beneficiary',  1, 1),
('org@sawa.local',         '$2y$12$SptcKMAoqRtDQnfwmloOB.JNRdAMDWJQYebdJFthUnmJ22hiodUDe', 'Community Care NGO', 'organisation', 1, 1);

INSERT INTO organisations (user_id, name, description, verified, verified_at)
SELECT u.id, 'Community Care NGO', 'Verified Lebanese charity supporting families in need.', 1, NOW()
FROM users u
WHERE u.email = 'org@sawa.local'
  AND NOT EXISTS (SELECT 1 FROM organisations o WHERE o.user_id = u.id);

-- Beneficiary-owned sample campaign
INSERT INTO campaigns (owner_user_id, title, summary, description, goal_amount, raised_amount, category_id, location_id, status, ends_at)
SELECT u.id,
       'Medical aid for my daughter Lina',
       'Lina needs ongoing treatment at Saint George Hospital.',
       'Lina is 4 and needs ongoing treatment at Saint George Hospital. Every donation helps cover medication and hospital visits.',
       10000.00, 3200.00,
       (SELECT id FROM categories WHERE slug = 'medical' LIMIT 1),
       (SELECT id FROM locations WHERE slug = 'beirut' LIMIT 1),
       'active', DATE_ADD(NOW(), INTERVAL 5 DAY)
FROM users u
WHERE u.email = 'beneficiary@sawa.local'
  AND NOT EXISTS (SELECT 1 FROM campaigns c WHERE c.owner_user_id = u.id AND c.title = 'Medical aid for my daughter Lina');

-- Organisation-owned sample campaigns
INSERT INTO campaigns (organisation_id, title, summary, description, goal_amount, raised_amount, category_id, location_id, status, ends_at)
SELECT o.id,
       'Help Sara Beat Cancer',
       '6-year-old Sara needs urgent chemotherapy.',
       '6-year-old Sara needs urgent chemotherapy in Beirut. Every dollar brings her closer to recovery.',
       20000.00, 14400.00,
       (SELECT id FROM categories WHERE slug = 'medical' LIMIT 1),
       (SELECT id FROM locations WHERE slug = 'beirut' LIMIT 1),
       'active', DATE_ADD(NOW(), INTERVAL 4 DAY)
FROM organisations o
INNER JOIN users u ON u.id = o.user_id AND u.email = 'org@sawa.local'
WHERE NOT EXISTS (SELECT 1 FROM campaigns c WHERE c.organisation_id = o.id AND c.title = 'Help Sara Beat Cancer');

INSERT INTO campaigns (organisation_id, title, summary, description, goal_amount, raised_amount, category_id, location_id, status, ends_at)
SELECT o.id,
       'Winter Food Packages',
       'Warm meals for 50 families in Tripoli.',
       'Winter Food Packages delivers warm meals and staples to 50 families in Tripoli during the cold season.',
       10000.00, 8800.00,
       (SELECT id FROM categories WHERE slug = 'food' LIMIT 1),
       (SELECT id FROM locations WHERE slug = 'tripoli' LIMIT 1),
       'active', DATE_ADD(NOW(), INTERVAL 14 DAY)
FROM organisations o
INNER JOIN users u ON u.id = o.user_id AND u.email = 'org@sawa.local'
WHERE NOT EXISTS (SELECT 1 FROM campaigns c WHERE c.organisation_id = o.id AND c.title = 'Winter Food Packages');

INSERT INTO campaigns (organisation_id, title, summary, description, goal_amount, raised_amount, category_id, location_id, status, ends_at)
SELECT o.id,
       'School Supplies for Akkar',
       '120 children need notebooks and uniforms.',
       'School Supplies for Akkar equips 120 children with notebooks, uniforms, and backpacks before the new term.',
       5000.00, 2250.00,
       (SELECT id FROM categories WHERE slug = 'educational' LIMIT 1),
       (SELECT id FROM locations WHERE slug = 'akkar' LIMIT 1),
       'active', DATE_ADD(NOW(), INTERVAL 21 DAY)
FROM organisations o
INNER JOIN users u ON u.id = o.user_id AND u.email = 'org@sawa.local'
WHERE NOT EXISTS (SELECT 1 FROM campaigns c WHERE c.organisation_id = o.id AND c.title = 'School Supplies for Akkar');

INSERT INTO campaigns (organisation_id, title, summary, description, goal_amount, raised_amount, category_id, location_id, status, ends_at)
SELECT o.id,
       'Emergency Roof Repair',
       'Families in Beirut need safe shelter before winter rain.',
       'Emergency Roof Repair funds urgent roof sealing and tarpaulins for families whose homes were damaged.',
       10000.00, 3100.00,
       (SELECT id FROM categories WHERE slug = 'shelter' LIMIT 1),
       (SELECT id FROM locations WHERE slug = 'beirut' LIMIT 1),
       'active', DATE_ADD(NOW(), INTERVAL 3 DAY)
FROM organisations o
INNER JOIN users u ON u.id = o.user_id AND u.email = 'org@sawa.local'
WHERE NOT EXISTS (SELECT 1 FROM campaigns c WHERE c.organisation_id = o.id AND c.title = 'Emergency Roof Repair');

-- -----------------------------------------------------------------------------
-- Donation history behind the campaign totals above.
--
-- The campaigns seeded above carry a raised_amount, but before this block the
-- donations table was empty. That made the platform stats self-contradictory:
-- CampaignService::stats() reads raised_amount from campaigns (so it showed
-- $31.8k) but counts donors from donations (so it showed 0).
--
-- The amounts below sum EXACTLY to each campaign's raised_amount:
--   Medical aid for my daughter Lina  3,200
--   Help Sara Beat Cancer            14,400
--   Winter Food Packages              8,800
--   School Supplies for Akkar         2,250
--   Emergency Roof Repair             3,100
--                                 = 31,750
-- Keep them in sync if you change either side.
--
-- Fees follow DonationService::feeRate(): members paying by wallet are charged
-- FEE_RATE_MEMBER_WALLET (5%), guests FEE_RATE_GUEST (10%). raised_amount
-- tracks `amount` only, never the fee.
-- -----------------------------------------------------------------------------

-- Additional donor accounts, so the donor count reflects a realistic spread
-- rather than a single repeat giver. Password for all: Demo123!
INSERT IGNORE INTO users (email, password_hash, full_name, role, email_verified, active) VALUES
('donor01@sawa.local', '$2y$12$SptcKMAoqRtDQnfwmloOB.JNRdAMDWJQYebdJFthUnmJ22hiodUDe', 'Maya Haddad',    'user', 1, 1),
('donor02@sawa.local', '$2y$12$SptcKMAoqRtDQnfwmloOB.JNRdAMDWJQYebdJFthUnmJ22hiodUDe', 'Karim Nassar',   'user', 1, 1),
('donor03@sawa.local', '$2y$12$SptcKMAoqRtDQnfwmloOB.JNRdAMDWJQYebdJFthUnmJ22hiodUDe', 'Rana Khoury',    'user', 1, 1),
('donor04@sawa.local', '$2y$12$SptcKMAoqRtDQnfwmloOB.JNRdAMDWJQYebdJFthUnmJ22hiodUDe', 'Fadi Aoun',      'user', 1, 1),
('donor05@sawa.local', '$2y$12$SptcKMAoqRtDQnfwmloOB.JNRdAMDWJQYebdJFthUnmJ22hiodUDe', 'Layla Saad',     'user', 1, 1),
('donor06@sawa.local', '$2y$12$SptcKMAoqRtDQnfwmloOB.JNRdAMDWJQYebdJFthUnmJ22hiodUDe', 'Omar Chami',     'user', 1, 1),
('donor07@sawa.local', '$2y$12$SptcKMAoqRtDQnfwmloOB.JNRdAMDWJQYebdJFthUnmJ22hiodUDe', 'Nour Fares',     'user', 1, 1),
('donor08@sawa.local', '$2y$12$SptcKMAoqRtDQnfwmloOB.JNRdAMDWJQYebdJFthUnmJ22hiodUDe', 'Ziad Merhi',     'user', 1, 1),
('donor09@sawa.local', '$2y$12$SptcKMAoqRtDQnfwmloOB.JNRdAMDWJQYebdJFthUnmJ22hiodUDe', 'Hala Bitar',     'user', 1, 1),
('donor10@sawa.local', '$2y$12$SptcKMAoqRtDQnfwmloOB.JNRdAMDWJQYebdJFthUnmJ22hiodUDe', 'Elie Gerges',    'user', 1, 1),
('donor11@sawa.local', '$2y$12$SptcKMAoqRtDQnfwmloOB.JNRdAMDWJQYebdJFthUnmJ22hiodUDe', 'Sami Daher',     'user', 1, 1),
('donor12@sawa.local', '$2y$12$SptcKMAoqRtDQnfwmloOB.JNRdAMDWJQYebdJFthUnmJ22hiodUDe', 'Tala Rizk',      'user', 1, 1);

-- Each row is keyed by a unique payment_ref, so re-running this file is a no-op.
-- donor_email NULL => guest donation (no account, 10% fee, hosted checkout).
INSERT INTO donations
  (campaign_id, donor_id, guest_name, guest_email, amount, fee_amount, total_charged,
   currency, status, payment_method, anonymous, payment_ref,
   created_at, verified_at, completed_at)
SELECT c.id,
       u.id,
       IF(s.donor_email IS NULL, s.guest_name, NULL),
       IF(s.donor_email IS NULL, s.guest_email, NULL),
       s.amount,
       ROUND(s.amount * IF(s.donor_email IS NULL, 0.10, 0.05), 2),
       ROUND(s.amount * (1 + IF(s.donor_email IS NULL, 0.10, 0.05)), 2),
       'USD',
       s.status,
       IF(s.donor_email IS NULL, 'hosted_checkout', 'wallet'),
       s.anonymous,
       s.ref,
       DATE_SUB(NOW(), INTERVAL s.days_ago DAY),
       DATE_ADD(DATE_SUB(NOW(), INTERVAL s.days_ago DAY), INTERVAL 1 HOUR),
       IF(s.status = 'completed',
          DATE_ADD(DATE_SUB(NOW(), INTERVAL s.days_ago DAY), INTERVAL 2 HOUR),
          NULL)
FROM (
  -- campaign_title                     | donor_email          | guest_name          | guest_email                | amount | status      | anon | days_ago | ref
  SELECT 'Medical aid for my daughter Lina' AS campaign_title, 'donor01@sawa.local' AS donor_email, NULL AS guest_name, NULL AS guest_email, 250.00 AS amount, 'completed' AS status, 0 AS anonymous, 12 AS days_ago, 'SEED-C1-01' AS ref
  UNION ALL SELECT 'Medical aid for my daughter Lina', 'donor02@sawa.local', NULL, NULL,  500.00, 'completed', 0, 10, 'SEED-C1-02'
  UNION ALL SELECT 'Medical aid for my daughter Lina', 'donor03@sawa.local', NULL, NULL,  150.00, 'completed', 0,  8, 'SEED-C1-03'
  UNION ALL SELECT 'Medical aid for my daughter Lina', 'donor04@sawa.local', NULL, NULL,  300.00, 'completed', 0,  6, 'SEED-C1-04'
  UNION ALL SELECT 'Medical aid for my daughter Lina', 'donor@sawa.local',   NULL, NULL,  800.00, 'completed', 0,  4, 'SEED-C1-05'
  UNION ALL SELECT 'Medical aid for my daughter Lina', NULL, 'Anonymous',       NULL,                        400.00, 'completed', 1,  9, 'SEED-C1-G1'
  UNION ALL SELECT 'Medical aid for my daughter Lina', NULL, 'Rami H.',         'rami.h@example.com',        300.00, 'completed', 0,  5, 'SEED-C1-G2'
  UNION ALL SELECT 'Medical aid for my daughter Lina', NULL, 'Community donor', 'community@example.com',     500.00, 'verified',  0,  2, 'SEED-C1-G3'

  UNION ALL SELECT 'Help Sara Beat Cancer', 'donor05@sawa.local', NULL, NULL, 1000.00, 'completed', 0, 20, 'SEED-C2-01'
  UNION ALL SELECT 'Help Sara Beat Cancer', 'donor06@sawa.local', NULL, NULL,  750.00, 'completed', 0, 18, 'SEED-C2-02'
  UNION ALL SELECT 'Help Sara Beat Cancer', 'donor07@sawa.local', NULL, NULL,  500.00, 'completed', 0, 15, 'SEED-C2-03'
  UNION ALL SELECT 'Help Sara Beat Cancer', 'donor08@sawa.local', NULL, NULL, 2000.00, 'completed', 0, 12, 'SEED-C2-04'
  UNION ALL SELECT 'Help Sara Beat Cancer', 'donor09@sawa.local', NULL, NULL,  250.00, 'completed', 0, 10, 'SEED-C2-05'
  UNION ALL SELECT 'Help Sara Beat Cancer', 'donor10@sawa.local', NULL, NULL, 1500.00, 'completed', 0,  7, 'SEED-C2-06'
  UNION ALL SELECT 'Help Sara Beat Cancer', 'donor@sawa.local',   NULL, NULL,  900.00, 'completed', 0,  5, 'SEED-C2-07'
  UNION ALL SELECT 'Help Sara Beat Cancer', NULL, 'Anonymous',         NULL,                     3000.00, 'completed', 1, 16, 'SEED-C2-G1'
  UNION ALL SELECT 'Help Sara Beat Cancer', NULL, 'Nadia K.',          'nadia.k@example.com',    1500.00, 'completed', 0, 11, 'SEED-C2-G2'
  UNION ALL SELECT 'Help Sara Beat Cancer', NULL, 'Beirut wellwisher', 'wellwisher@example.com', 2000.00, 'completed', 0,  6, 'SEED-C2-G3'
  UNION ALL SELECT 'Help Sara Beat Cancer', NULL, 'Anonymous',         NULL,                     1000.00, 'verified',  1,  1, 'SEED-C2-G4'

  UNION ALL SELECT 'Winter Food Packages', 'donor11@sawa.local', NULL, NULL,  500.00, 'completed', 0, 14, 'SEED-C3-01'
  UNION ALL SELECT 'Winter Food Packages', 'donor12@sawa.local', NULL, NULL, 1200.00, 'completed', 0, 12, 'SEED-C3-02'
  UNION ALL SELECT 'Winter Food Packages', 'donor01@sawa.local', NULL, NULL,  300.00, 'completed', 0,  9, 'SEED-C3-03'
  UNION ALL SELECT 'Winter Food Packages', 'donor05@sawa.local', NULL, NULL,  750.00, 'completed', 0,  6, 'SEED-C3-04'
  UNION ALL SELECT 'Winter Food Packages', 'donor08@sawa.local', NULL, NULL, 1050.00, 'completed', 0,  3, 'SEED-C3-05'
  UNION ALL SELECT 'Winter Food Packages', NULL, 'Anonymous',      NULL,                    2000.00, 'completed', 1, 13, 'SEED-C3-G1'
  UNION ALL SELECT 'Winter Food Packages', NULL, 'Tripoli friend', 'tripoli@example.com',   1500.00, 'completed', 0,  8, 'SEED-C3-G2'
  UNION ALL SELECT 'Winter Food Packages', NULL, 'Anonymous',      NULL,                    1500.00, 'verified',  1,  2, 'SEED-C3-G3'

  UNION ALL SELECT 'School Supplies for Akkar', 'donor02@sawa.local', NULL, NULL, 150.00, 'completed', 0, 11, 'SEED-C4-01'
  UNION ALL SELECT 'School Supplies for Akkar', 'donor06@sawa.local', NULL, NULL, 400.00, 'completed', 0,  8, 'SEED-C4-02'
  UNION ALL SELECT 'School Supplies for Akkar', 'donor09@sawa.local', NULL, NULL, 200.00, 'completed', 0,  5, 'SEED-C4-03'
  UNION ALL SELECT 'School Supplies for Akkar', 'donor11@sawa.local', NULL, NULL, 500.00, 'completed', 0,  2, 'SEED-C4-04'
  UNION ALL SELECT 'School Supplies for Akkar', NULL, 'Anonymous',       NULL,                  600.00, 'completed', 1,  7, 'SEED-C4-G1'
  UNION ALL SELECT 'School Supplies for Akkar', NULL, 'Akkar supporter', 'akkar@example.com',   400.00, 'verified',  0,  1, 'SEED-C4-G2'

  UNION ALL SELECT 'Emergency Roof Repair', 'donor03@sawa.local', NULL, NULL, 300.00, 'completed', 0,  9, 'SEED-C5-01'
  UNION ALL SELECT 'Emergency Roof Repair', 'donor07@sawa.local', NULL, NULL, 600.00, 'completed', 0,  7, 'SEED-C5-02'
  UNION ALL SELECT 'Emergency Roof Repair', 'donor10@sawa.local', NULL, NULL, 250.00, 'completed', 0,  4, 'SEED-C5-03'
  UNION ALL SELECT 'Emergency Roof Repair', 'donor12@sawa.local', NULL, NULL, 450.00, 'completed', 0,  2, 'SEED-C5-04'
  UNION ALL SELECT 'Emergency Roof Repair', NULL, 'Anonymous',        NULL,                   900.00, 'completed', 1,  6, 'SEED-C5-G1'
  UNION ALL SELECT 'Emergency Roof Repair', NULL, 'Zahle neighbour',  'zahle@example.com',    600.00, 'verified',  0,  1, 'SEED-C5-G2'
) AS s
INNER JOIN campaigns c ON c.title = s.campaign_title
LEFT  JOIN users     u ON u.email = s.donor_email
WHERE NOT EXISTS (SELECT 1 FROM donations d WHERE d.payment_ref = s.ref);

-- Organisation wallet ledger for the donations above.
-- At runtime DonationService::completeDonation() always calls
-- WalletService::creditOrganisation(), so seeded donations must produce the
-- same credit rows. Without them WalletService::orgBalance() finds no rows and
-- returns 0.0 — the admin money view showed a $0.00 balance for Community Care
-- NGO against the $28,550 its four campaigns had raised.
-- Only organisation-owned campaigns credit a wallet; the beneficiary-owned
-- "Medical aid for my daughter Lina" ($3,200) correctly contributes nothing.
-- balance_after is a running total ordered by donation id, which is the order
-- the rows above were inserted, so the highest-id row (the one orgBalance()
-- reads) carries the correct final balance.
-- Idempotent: rows already carrying a related_donation_id are skipped.
INSERT INTO wallet_transactions
  (organisation_id, type, amount, currency, balance_after, related_donation_id, description, created_by, created_at)
SELECT c.organisation_id,
       'credit',
       d.amount,
       'USD',
       (SELECT ROUND(SUM(d2.amount), 2)
          FROM donations d2
          INNER JOIN campaigns c2 ON c2.id = d2.campaign_id
         WHERE c2.organisation_id = c.organisation_id
           AND d2.status IN ('verified','completed')
           AND d2.id <= d.id),
       d.id,
       CONCAT('Donation #', d.id),
       NULL,
       d.verified_at
FROM donations d
INNER JOIN campaigns c ON c.id = d.campaign_id
WHERE c.organisation_id IS NOT NULL
  AND d.status IN ('verified','completed')
  -- Derived table forces materialisation so MySQL allows referencing the
  -- INSERT target inside the guard.
  AND d.id NOT IN (
        SELECT existing.related_donation_id FROM (
          SELECT w.related_donation_id FROM wallet_transactions w
          WHERE w.related_donation_id IS NOT NULL
        ) AS existing
      )
ORDER BY d.id;

-- Donor wallet ledger for the wallet-paid donations above.
-- The seed marks every member donation as payment_method='wallet', but seeded
-- no user_wallet_ledger rows — so those donors had spent from wallets that had
-- never existed, WalletService::balance() returned 0.0 for all of them, and the
-- admin Users view reported "With Wallets: 0" against 25 wallet payments.
-- Guest donations use hosted_checkout and correctly get no ledger.
--
-- Step 1: one top-up per donor covering everything they spend, plus $200 left
-- over so the wallet still shows a live balance (the admin metric counts users
-- whose latest balance_after is > 0, so netting exactly to zero would still
-- have read as "no wallet").
INSERT INTO user_wallet_ledger
  (user_id, type, amount, balance_after, description, payment_ref, created_at)
SELECT t.donor_id,
       'topup',
       t.spend + 200.00,
       t.spend + 200.00,
       'Wallet top-up',
       CONCAT('SEED-TOPUP-', t.donor_id),
       DATE_SUB(NOW(), INTERVAL 30 DAY)
FROM (
  SELECT d.donor_id AS donor_id, SUM(d.total_charged) AS spend
  FROM donations d
  WHERE d.donor_id IS NOT NULL
    AND d.payment_method = 'wallet'
    AND d.status IN ('verified','completed')
  GROUP BY d.donor_id
) AS t
WHERE t.donor_id NOT IN (
  SELECT existing.user_id FROM (SELECT user_id FROM user_wallet_ledger) AS existing
);

-- Step 2: one debit per wallet donation, carrying a running balance. Ordered
-- by donor then donation id, so each donor's highest-id row holds their final
-- balance — which is what WalletService::balance() reads.
INSERT INTO user_wallet_ledger
  (user_id, type, amount, balance_after, related_donation_id, description, created_at)
SELECT d.donor_id,
       'donation',
       d.total_charged,
       (SELECT tt.spend + 200.00 FROM (
          SELECT d4.donor_id AS dz, SUM(d4.total_charged) AS spend
          FROM donations d4
          WHERE d4.donor_id IS NOT NULL
            AND d4.payment_method = 'wallet'
            AND d4.status IN ('verified','completed')
          GROUP BY d4.donor_id
        ) AS tt WHERE tt.dz = d.donor_id)
       - (SELECT SUM(d2.total_charged) FROM donations d2
           WHERE d2.donor_id = d.donor_id
             AND d2.payment_method = 'wallet'
             AND d2.status IN ('verified','completed')
             AND d2.id <= d.id),
       d.id,
       CONCAT('Donation #', d.id),
       d.verified_at
FROM donations d
WHERE d.donor_id IS NOT NULL
  AND d.payment_method = 'wallet'
  AND d.status IN ('verified','completed')
  AND d.id NOT IN (
    SELECT existing.related_donation_id FROM (
      SELECT related_donation_id FROM user_wallet_ledger WHERE related_donation_id IS NOT NULL
    ) AS existing
  )
ORDER BY d.donor_id, d.id;

-- =============================================================================
-- End of database_complete.sql
-- =============================================================================
