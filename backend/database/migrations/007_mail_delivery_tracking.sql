-- Apenas estrutura. A linha de controle é criada sob demanda pelo MailDeliveryService.
CREATE TABLE mail_quota_guard (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE mail_deliveries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    contact_message_id BIGINT UNSIGNED NULL,
    kind ENUM('contact_owner','contact_confirmation') NOT NULL,
    status ENUM('reserved','sent','failed','skipped') NOT NULL DEFAULT 'reserved',
    quota_date DATE NOT NULL,
    reserved_at_utc DATETIME NOT NULL,
    sent_at_utc DATETIME NULL,
    updated_at_utc DATETIME NULL,
    INDEX idx_mail_delivery_quota_date (quota_date, status),
    INDEX idx_mail_delivery_rolling (reserved_at_utc),
    INDEX idx_mail_delivery_contact (contact_message_id),
    CONSTRAINT fk_mail_delivery_contact FOREIGN KEY (contact_message_id)
        REFERENCES contact_messages(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
