-- create_products.sql
CREATE TABLE IF NOT EXISTS `products` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `store_id` INT NOT NULL,         -- odniesienie do sklepy.id
  `location` TINYINT NOT NULL,     -- 1..20
  `sub` CHAR(1) NOT NULL,          -- 'A' lub 'B'
  `name` VARCHAR(255) NOT NULL,
  `reference` VARCHAR(100) NOT NULL,
  `ean` VARCHAR(32) NOT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `store_loc_sub` (`store_id`,`location`,`sub`),
  INDEX (`reference`),
  INDEX (`ean`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
