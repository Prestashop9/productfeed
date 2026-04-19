CREATE TABLE IF NOT EXISTS `PREFIX_productfeed` (
    `id_productfeed` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_product` INT(11) UNSIGNED NOT NULL,
    `is_sticky` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `badge_text` VARCHAR(50) DEFAULT NULL,
    `badge_expires` DATETIME DEFAULT NULL,
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

