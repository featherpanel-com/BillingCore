CREATE TABLE
	IF NOT EXISTS `featherpanel_billingcore_invoice_items` (
		`id` INT (11) NOT NULL AUTO_INCREMENT,
		`invoice_id` INT (11) NOT NULL,
		`description` VARCHAR(500) NOT NULL,
		`quantity` DECIMAL(10, 2) NOT NULL DEFAULT 1.00,
		`unit_price` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
		`total` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
		`sort_order` INT (11) NOT NULL DEFAULT 0,
		`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
		`updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (`id`),
		KEY `billingcore_invoice_items_invoice_id_index` (`invoice_id`),
		KEY `billingcore_invoice_items_sort_order_index` (`sort_order`),
		CONSTRAINT `billingcore_invoice_items_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `featherpanel_billingcore_invoices` (`id`) ON DELETE CASCADE
	) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

