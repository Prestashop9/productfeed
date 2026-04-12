CREATE TABLE IF NOT EXISTS `PREFIX_productfeed` (
    `id_productfeed` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_product` INT(11) UNSIGNED NOT NULL,
    `is_sticky` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `position` INT(11) UNSIGNED NOT NULL DEFAULT 0,
    `date_add` DATETIME NOT NULL,
    `date_upd` DATETIME NOT NULL,
    PRIMARY KEY (`id_productfeed`),
    UNIQUE KEY `idx_id_product` (`id_product`),
    INDEX `idx_is_sticky` (`is_sticky`),
    INDEX `idx_is_active` (`is_active`),
    INDEX `idx_position` (`position`),
    INDEX `idx_date_add` (`date_add`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `PREFIX_productfeed` (`id_product`, `is_sticky`, `is_active`, `position`, `date_add`, `date_upd`)
SELECT p.`id_product`, 0, 1, p.`id_product`, p.`date_add`, p.`date_upd`
FROM `PREFIX_product` p
WHERE p.`active` = 1;

CREATE TABLE IF NOT EXISTS `PREFIX_productfeed_like` (
    `id_productfeed_like` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_product` INT(11) UNSIGNED NOT NULL,
    `id_customer` INT(11) UNSIGNED NOT NULL,
    `date_add` DATETIME NOT NULL,
    PRIMARY KEY (`id_productfeed_like`),
    UNIQUE KEY `idx_product_customer` (`id_product`, `id_customer`),
    INDEX `idx_id_product` (`id_product`),
    INDEX `idx_id_customer` (`id_customer`),
    INDEX `idx_date_add` (`date_add`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `PREFIX_productfeed_save` (
    `id_productfeed_save` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_product` INT(11) UNSIGNED NOT NULL,
    `id_customer` INT(11) UNSIGNED NOT NULL,
    `date_add` DATETIME NOT NULL,
    PRIMARY KEY (`id_productfeed_save`),
    UNIQUE KEY `idx_product_customer` (`id_product`, `id_customer`),
    INDEX `idx_id_product` (`id_product`),
    INDEX `idx_id_customer` (`id_customer`),
    INDEX `idx_date_add` (`date_add`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
