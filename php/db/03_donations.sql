-- Sawa — Money domain

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

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

SET FOREIGN_KEY_CHECKS = 1;
