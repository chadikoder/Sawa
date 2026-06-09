-- Sawa — Campaigns domain

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

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
    CONSTRAINT fk_campaigns_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL,
    CONSTRAINT chk_campaign_owner CHECK (
        organisation_id IS NOT NULL OR owner_user_id IS NOT NULL
    )
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

SET FOREIGN_KEY_CHECKS = 1;
