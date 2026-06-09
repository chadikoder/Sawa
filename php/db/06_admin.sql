-- Sawa — Admin domain

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS audit_log (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_id        BIGINT UNSIGNED NULL,
    action          VARCHAR(80) NOT NULL,
    target_type     VARCHAR(40) NOT NULL,
    target_id       BIGINT UNSIGNED NULL,
    details         JSON NULL,
    ip_address      VARCHAR(45) NULL,
    user_agent      VARCHAR(500) NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_admin (admin_id, created_at),
    KEY idx_audit_target (target_type, target_id),
    KEY idx_audit_action (action, created_at),
    CONSTRAINT fk_audit_admin FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
