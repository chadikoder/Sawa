-- Sawa — Payment sessions & receipts

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

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
    pdf_path        VARCHAR(500) NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_receipt_bill (bill_id),
    KEY idx_receipt_user (user_id, created_at),
    CONSTRAINT fk_receipt_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_receipt_donation FOREIGN KEY (donation_id) REFERENCES donations(id) ON DELETE SET NULL,
    CONSTRAINT fk_receipt_payment FOREIGN KEY (payment_session_id) REFERENCES payment_sessions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
