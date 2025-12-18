CREATE TABLE
	IF NOT EXISTS `featherpanel_billing_user_info` (
		`id` INT (11) NOT NULL AUTO_INCREMENT,
		`user_id` INT (11) NOT NULL,
		`full_name` VARCHAR(255) NOT NULL,
		`company_name` VARCHAR(255) DEFAULT NULL,
		`address_line1` VARCHAR(255) NOT NULL,
		`address_line2` VARCHAR(255) DEFAULT NULL,
		`city` VARCHAR(191) NOT NULL,
		`state` VARCHAR(191) DEFAULT NULL,
		`postal_code` VARCHAR(32) NOT NULL,
		`country_code` CHAR(2) NOT NULL,
		`vat_id` VARCHAR(64) DEFAULT NULL,
		`phone` VARCHAR(32) DEFAULT NULL,
		`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
		`updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (`id`),
		UNIQUE KEY `billing_user_info_user_id_unique` (`user_id`),
		CONSTRAINT `billing_user_info_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `featherpanel_users` (`id`) ON DELETE CASCADE
	) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;