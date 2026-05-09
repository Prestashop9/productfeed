CREATE TABLE IF NOT EXISTS `PREFIX_productfeed` (
    `id_productfeed` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_product` INT(11) UNSIGNED NOT NULL,
    `position` INT(11) UNSIGNED NOT NULL DEFAULT 0,
    `is_sticky` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `badge_text` VARCHAR(50) DEFAULT NULL,
    `badge_expires` DATETIME DEFAULT NULL,
    `pushed_at` DATETIME NOT NULL,
    `date_add` DATETIME NOT NULL,
    `date_upd` DATETIME NOT NULL,
    PRIMARY KEY (`id_productfeed`),
    UNIQUE KEY `idx_id_product` (`id_product`),
    INDEX `idx_position` (`position`),
    INDEX `idx_is_sticky` (`is_sticky`),
    INDEX `idx_is_active` (`is_active`),
    INDEX `idx_pushed_at` (`pushed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `PREFIX_productfeed` (`id_product`, `position`, `is_sticky`, `is_active`, `pushed_at`, `date_add`, `date_upd`)
SELECT p.`id_product`, ROW_NUMBER() OVER (ORDER BY p.`id_product` ASC), 0, 1, p.`date_add`, p.`date_add`, p.`date_upd`
FROM `PREFIX_product` p
WHERE p.`active` = 1;
