CREATE TABLE
	IF NOT EXISTS `featherpanel_billingcore_invoices` (
		`id` INT (11) NOT NULL AUTO_INCREMENT,
		`user_id` INT (11) NOT NULL,
		`invoice_number` VARCHAR(64) NOT NULL,
		`status` ENUM('draft', 'pending', 'paid', 'overdue', 'cancelled') NOT NULL DEFAULT 'draft',
		`due_date` DATE DEFAULT NULL,
		`paid_at` DATETIME DEFAULT NULL,
		`subtotal` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
		`tax_rate` DECIMAL(5, 2) NOT NULL DEFAULT 0.00,
		`tax_amount` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
		`total` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
		`currency_code` CHAR(3) NOT NULL DEFAULT 'EUR',
		`notes` TEXT DEFAULT NULL,
		`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
		`updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (`id`),
		UNIQUE KEY `billingcore_invoices_invoice_number_unique` (`invoice_number`),
		KEY `billingcore_invoices_user_id_index` (`user_id`),
		KEY `billingcore_invoices_status_index` (`status`),
		KEY `billingcore_invoices_due_date_index` (`due_date`),
		CONSTRAINT `billingcore_invoices_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `featherpanel_users` (`id`) ON DELETE CASCADE
	) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

