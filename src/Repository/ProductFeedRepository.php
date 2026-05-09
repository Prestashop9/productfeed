<?php

declare(strict_types=1);

namespace PrestaShop\Module\ProductFeed\Repository;

use Db;
use DbQuery;

class ProductFeedRepository
{
    /** @var bool Has schema migration already been checked this request? */
    private static $schemaChecked = false;

    public function __construct()
    {
        if (!self::$schemaChecked) {
            $this->ensureSchema();
            self::$schemaChecked = true;
        }
    }

    /**
     * Idempotently add missing columns for existing installs.
     * Self-heals existing installs so no version bump / upgrade script required.
     */
    private function ensureSchema(): void
    {
        $db = Db::getInstance();
        $table = _DB_PREFIX_ . 'productfeed';

        $tableExists = (bool) $db->getValue(
            'SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
            AND table_name = "' . pSQL($table) . '"'
        );
        if (!$tableExists) {
            $this->installSchema();
        }

        $positionCols = $db->executeS('SHOW COLUMNS FROM `' . $table . '` LIKE "position"');
        if (empty($positionCols)) {
            $db->execute('ALTER TABLE `' . $table . '` ADD COLUMN `position` INT(11) UNSIGNED NOT NULL DEFAULT 0 AFTER `id_product`');
            $db->execute('ALTER TABLE `' . $table . '` ADD INDEX `idx_position` (`position`)');
        }
        $emptyPositions = (int) $db->getValue('SELECT COUNT(*) FROM `' . $table . '` WHERE `position` = 0');
        if ($emptyPositions > 0) {
            $db->execute('SET @productfeed_position := 0');
            $db->execute('UPDATE `' . $table . '` SET `position` = (@productfeed_position := @productfeed_position + 1) ORDER BY `id_productfeed` ASC');
        }

        $cols = $db->executeS('SHOW COLUMNS FROM `' . $table . '` LIKE "pushed_at"');
        if (empty($cols)) {
            $db->execute('ALTER TABLE `' . $table . '` ADD COLUMN `pushed_at` DATETIME NOT NULL AFTER `badge_expires`');
            $db->execute('UPDATE `' . $table . '` SET `pushed_at` = `date_add`');
            $db->execute('ALTER TABLE `' . $table . '` ADD INDEX `idx_pushed_at` (`pushed_at`)');
        }
    }

    private function installSchema(): void
    {
        $sql = file_get_contents(_PS_MODULE_DIR_ . 'productfeed/sql/install.sql');
        if (!$sql) {
            return;
        }

        $sql = str_replace('PREFIX_', _DB_PREFIX_, $sql);
        $queries = preg_split('/;\s*[\r\n]+/', trim($sql));
        foreach ($queries as $query) {
            $query = trim($query);
            if ($query !== '') {
                Db::getInstance()->execute($query);
            }
        }
    }

    public function getProductsForFeed(
        int $idLang,
        int $idShop,
        int $page = 1,
        int $perPage = 10,
        string $sortBy = 'date_add',
        string $sortOrder = 'DESC',
        int $idCategory = 0,
        string $feedSort = ''
    ): array {
        $offset = ($page - 1) * $perPage;

        $query = new DbQuery();
        $query->select('
            p.id_product,
            pl.name,
            pl.description_short,
            pl.link_rewrite,
            p.price,
            p.id_category_default,
            p.date_add AS product_date_add,
            p.date_upd AS product_date_upd,
            pf.is_sticky,
            pf.badge_text,
            pf.badge_expires,
            pf.pushed_at,
            pf.date_add AS feed_date_add,
            pf.date_upd AS feed_date_upd,
            cl.name AS category_name,
            cl.link_rewrite AS category_rewrite,
            img.id_image
        ');
        $query->from('productfeed', 'pf');
        $query->innerJoin('product', 'p', 'p.id_product = pf.id_product');
        $query->innerJoin('product_shop', 'ps', 'ps.id_product = p.id_product AND ps.id_shop = ' . $idShop);
        $query->innerJoin('product_lang', 'pl', 'pl.id_product = p.id_product AND pl.id_lang = ' . $idLang . ' AND pl.id_shop = ' . $idShop);
        $query->leftJoin('category_lang', 'cl', 'cl.id_category = p.id_category_default AND cl.id_lang = ' . $idLang . ' AND cl.id_shop = ' . $idShop);
        $query->leftJoin('image', 'img', 'img.id_product = p.id_product AND img.cover = 1');

        if ($feedSort === 'popular' || $feedSort === 'bestselling') {
            $query->leftJoin('product_sale', 'sale', 'sale.id_product = p.id_product');
        }

        $query->where('pf.is_active = 1');
        $query->where('ps.active = 1');

        if ($idCategory > 0) {
            $query->where('p.id_category_default = ' . $idCategory);
        }

        // Default feed order matches the storefront feed: latest pushes first.
        if ($feedSort === 'popular') {
            $query->orderBy('pf.is_sticky DESC, IFNULL(sale.quantity, 0) DESC, pf.pushed_at DESC');
        } elseif ($feedSort === 'bestselling') {
            $query->orderBy('pf.is_sticky DESC, IFNULL(sale.quantity, 0) DESC');
        } else {
            $query->orderBy('pf.is_sticky DESC, pf.pushed_at DESC');
        }

        $query->limit($perPage, $offset);

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($query) ?: [];
    }

    public function getTotalActiveProducts(int $idShop, int $idCategory = 0): int
    {
        $query = new DbQuery();
        $query->select('COUNT(*)');
        $query->from('productfeed', 'pf');
        $query->innerJoin('product', 'p', 'p.id_product = pf.id_product');
        $query->innerJoin('product_shop', 'ps', 'ps.id_product = p.id_product AND ps.id_shop = ' . $idShop);
        $query->where('pf.is_active = 1');
        $query->where('ps.active = 1');

        if ($idCategory > 0) {
            $query->where('p.id_category_default = ' . $idCategory);
        }

        return (int) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($query);
    }

    /**
     * Get all distinct categories that have products in the feed
     */
    public function getFeedCategories(int $idLang, int $idShop): array
    {
        $query = new DbQuery();
        $query->select('DISTINCT p.id_category_default AS id_category, cl.name AS category_name');
        $query->from('productfeed', 'pf');
        $query->innerJoin('product', 'p', 'p.id_product = pf.id_product');
        $query->innerJoin('product_shop', 'ps', 'ps.id_product = p.id_product AND ps.id_shop = ' . $idShop);
        $query->leftJoin('category_lang', 'cl', 'cl.id_category = p.id_category_default AND cl.id_lang = ' . $idLang . ' AND cl.id_shop = ' . $idShop);
        $query->where('pf.is_active = 1');
        $query->where('ps.active = 1');
        $query->where('cl.name IS NOT NULL');
        $query->orderBy('cl.name ASC');

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($query) ?: [];
    }

    public function getAllProductsForAdmin(
        int $idLang,
        int $idShop,
        int $page = 1,
        int $perPage = 50,
        string $search = '',
        string $sortBy = 'pushed',
        string $sortOrder = 'DESC'
    ): array {
        $offset = ($page - 1) * $perPage;
        $sortOrder = strtoupper($sortOrder) === 'DESC' ? 'DESC' : 'ASC';
        $sortMap = [
            'position' => 'pf.position',
            'name' => 'pl.name',
            'created' => 'p.date_add',
            'pushed' => 'pf.pushed_at',
        ];
        $sortBy = array_key_exists($sortBy, $sortMap) ? $sortBy : 'pushed';

        $searchWhere = '';
        if (!empty($search)) {
            $search = pSQL($search);
            $searchWhere = " AND (pl.name LIKE '%{$search}%' OR p.reference LIKE '%{$search}%')";
        }

        $sql = 'SELECT
                    pf.id_productfeed,
                    pf.position AS feed_position,
                    pf.id_product,
                    pf.is_sticky,
                    pf.is_active,
                    pf.badge_text,
                    pf.badge_expires,
                    pf.pushed_at,
                    pf.date_add,
                    pf.date_upd,
                    pl.name AS product_name,
                    p.price,
                    p.reference,
                    p.date_add AS product_date_add,
                    p.date_upd AS product_date_upd,
                    p.id_category_default AS id_category,
                    cl.name AS category_name,
                    img.id_image
                FROM `' . _DB_PREFIX_ . 'productfeed` pf
                INNER JOIN `' . _DB_PREFIX_ . 'product` p ON p.id_product = pf.id_product
                INNER JOIN `' . _DB_PREFIX_ . 'product_shop` ps ON ps.id_product = p.id_product AND ps.id_shop = ' . $idShop . '
                INNER JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON pl.id_product = p.id_product AND pl.id_lang = ' . $idLang . ' AND pl.id_shop = ' . $idShop . '
                LEFT JOIN `' . _DB_PREFIX_ . 'category_lang` cl ON cl.id_category = p.id_category_default AND cl.id_lang = ' . $idLang . ' AND cl.id_shop = ' . $idShop . '
                LEFT JOIN `' . _DB_PREFIX_ . 'image` img ON img.id_product = p.id_product AND img.cover = 1
                WHERE 1=1' . $searchWhere . '
                ORDER BY ' . $sortMap[$sortBy] . ' ' . $sortOrder . ', pf.id_productfeed ASC
                LIMIT ' . $offset . ', ' . $perPage;

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql) ?: [];
    }

    public function getTotalProducts(int $idShop, string $search = ''): int
    {
        $query = new DbQuery();
        $query->select('COUNT(*)');
        $query->from('productfeed', 'pf');
        $query->innerJoin('product', 'p', 'p.id_product = pf.id_product');
        $query->innerJoin('product_shop', 'ps', 'ps.id_product = p.id_product AND ps.id_shop = ' . $idShop);

        if (!empty($search)) {
            $idLang = (int) \Configuration::get('PS_LANG_DEFAULT');
            $search = pSQL($search);
            $query->innerJoin('product_lang', 'pl', 'pl.id_product = p.id_product AND pl.id_lang = ' . $idLang . ' AND pl.id_shop = ' . $idShop);
            $query->where('(pl.name LIKE \'%' . $search . '%\' OR p.reference LIKE \'%' . $search . '%\')');
        }

        return (int) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($query);
    }

    public function toggleField(int $idProduct, string $field): bool
    {
        $allowed = ['is_sticky', 'is_active'];
        if (!in_array($field, $allowed)) {
            return false;
        }

        return (bool) Db::getInstance()->execute(
            'UPDATE `' . _DB_PREFIX_ . 'productfeed`
             SET `' . $field . '` = IF(`' . $field . '` = 1, 0, 1),
                 `date_upd` = NOW()
             WHERE `id_product` = ' . $idProduct
        );
    }

    public function addProduct(int $idProduct): bool
    {
        $now = date('Y-m-d H:i:s');
        return (bool) Db::getInstance()->insert('productfeed', [
            'id_product' => $idProduct,
            'is_sticky' => 0,
            'is_active' => 1,
            'pushed_at' => $now,
            'date_add' => $now,
            'date_upd' => $now,
        ]);
    }

    public function removeProduct(int $idProduct): bool
    {
        return (bool) Db::getInstance()->delete('productfeed', 'id_product = ' . $idProduct);
    }

    /**
     * Bump a product to the top of the feed by refreshing pushed_at to NOW.
     */
    public function pushProduct(int $idProduct): bool
    {
        return (bool) Db::getInstance()->update(
            'productfeed',
            ['pushed_at' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s')],
            'id_product = ' . $idProduct
        );
    }

    /**
     * Apply a manual feed order. First id in $idsInOrder gets position 1.
     * Any product NOT listed is untouched — it keeps its existing position.
     */
    public function bulkReorder(array $idsInOrder): bool
    {
        $db = Db::getInstance();
        $success = true;
        foreach (array_values($idsInOrder) as $i => $idProduct) {
            $idProduct = (int) $idProduct;
            if ($idProduct <= 0) {
                continue;
            }
            $ok = $db->update(
                'productfeed',
                ['position' => $i + 1, 'date_upd' => date('Y-m-d H:i:s')],
                'id_product = ' . $idProduct
            );
            $success = $success && (bool) $ok;
        }
        return $success;
    }

    public function productExists(int $idProduct): bool
    {
        $query = new DbQuery();
        $query->select('COUNT(*)');
        $query->from('productfeed');
        $query->where('id_product = ' . $idProduct);

        return (int) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($query) > 0;
    }

    public function getFieldValue(int $idProduct, string $field): mixed
    {
        $allowed = ['is_sticky', 'is_active'];
        if (!in_array($field, $allowed)) {
            return null;
        }

        $query = new DbQuery();
        $query->select($field);
        $query->from('productfeed');
        $query->where('id_product = ' . $idProduct);

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($query);
    }

    public function updateBadge(int $idProduct, string $badgeText, ?string $badgeExpires): bool
    {
        return (bool) Db::getInstance()->update('productfeed', [
            'badge_text' => pSQL($badgeText),
            'badge_expires' => $badgeExpires ? pSQL($badgeExpires) : null,
            'date_upd' => date('Y-m-d H:i:s'),
        ], 'id_product = ' . $idProduct);
    }

    public function removeBadge(int $idProduct): bool
    {
        return (bool) Db::getInstance()->update('productfeed', [
            'badge_text' => null,
            'badge_expires' => null,
            'date_upd' => date('Y-m-d H:i:s'),
        ], 'id_product = ' . $idProduct);
    }

    /**
     * Get popular products — by sales quantity + recency
     */
    public function getPopularProducts(int $idLang, int $idShop, int $limit = 20): array
    {
        $query = new DbQuery();
        $query->select('
            p.id_product,
            pl.name,
            pl.link_rewrite,
            p.price,
            p.date_add AS product_date_add,
            cl.name AS category_name,
            cl.link_rewrite AS category_rewrite,
            img.id_image,
            IFNULL(sale.quantity, 0) AS popularity
        ');
        $query->from('productfeed', 'pf');
        $query->innerJoin('product', 'p', 'p.id_product = pf.id_product');
        $query->innerJoin('product_shop', 'ps', 'ps.id_product = p.id_product AND ps.id_shop = ' . $idShop);
        $query->innerJoin('product_lang', 'pl', 'pl.id_product = p.id_product AND pl.id_lang = ' . $idLang . ' AND pl.id_shop = ' . $idShop);
        $query->leftJoin('category_lang', 'cl', 'cl.id_category = p.id_category_default AND cl.id_lang = ' . $idLang . ' AND cl.id_shop = ' . $idShop);
        $query->leftJoin('image', 'img', 'img.id_product = p.id_product AND img.cover = 1');
        $query->leftJoin('product_sale', 'sale', 'sale.id_product = p.id_product');
        $query->where('pf.is_active = 1');
        $query->where('ps.active = 1');
        $query->orderBy('popularity DESC, p.date_add DESC');
        $query->limit($limit);

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($query) ?: [];
    }

    /**
     * Get best-selling products — random subset
     */
    public function getBestSellingProducts(int $idLang, int $idShop, int $limit = 20): array
    {
        $query = new DbQuery();
        $query->select('
            p.id_product,
            pl.name,
            pl.link_rewrite,
            p.price,
            p.date_add AS product_date_add,
            cl.name AS category_name,
            cl.link_rewrite AS category_rewrite,
            img.id_image,
            IFNULL(sale.quantity, 0) AS total_sold
        ');
        $query->from('productfeed', 'pf');
        $query->innerJoin('product', 'p', 'p.id_product = pf.id_product');
        $query->innerJoin('product_shop', 'ps', 'ps.id_product = p.id_product AND ps.id_shop = ' . $idShop);
        $query->innerJoin('product_lang', 'pl', 'pl.id_product = p.id_product AND pl.id_lang = ' . $idLang . ' AND pl.id_shop = ' . $idShop);
        $query->leftJoin('category_lang', 'cl', 'cl.id_category = p.id_category_default AND cl.id_lang = ' . $idLang . ' AND cl.id_shop = ' . $idShop);
        $query->leftJoin('image', 'img', 'img.id_product = p.id_product AND img.cover = 1');
        $query->leftJoin('product_sale', 'sale', 'sale.id_product = p.id_product');
        $query->where('pf.is_active = 1');
        $query->where('ps.active = 1');
        $query->orderBy('total_sold DESC');
        $query->limit($limit);

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($query) ?: [];
    }
}
