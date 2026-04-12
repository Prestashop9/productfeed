<?php

declare(strict_types=1);

namespace PrestaShop\Module\ProductFeed\Repository;

use Db;
use DbQuery;

class ProductFeedRepository
{
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

        $allowedSort = ['date_add', 'date_upd', 'position'];
        $sortBy = in_array($sortBy, $allowedSort) ? $sortBy : 'date_add';
        $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

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

        // Sort by feed mode
        if ($feedSort === 'popular') {
            $query->orderBy('IFNULL(sale.quantity, 0) DESC, p.date_add DESC');
        } elseif ($feedSort === 'bestselling') {
            $query->orderBy('IFNULL(sale.quantity, 0) DESC');
        } else {
            $query->orderBy('pf.is_sticky DESC, pf.' . $sortBy . ' ' . $sortOrder);
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
        string $search = ''
    ): array {
        $offset = ($page - 1) * $perPage;

        $query = new DbQuery();
        $query->select('
            pf.id_productfeed,
            pf.id_product,
            pf.is_sticky,
            pf.is_active,
            pf.position,
            pf.date_add,
            pf.date_upd,
            pl.name AS product_name,
            p.price,
            p.reference,
            p.date_add AS product_date_add,
            p.date_upd AS product_date_upd,
            cl.name AS category_name,
            img.id_image
        ');
        $query->from('productfeed', 'pf');
        $query->innerJoin('product', 'p', 'p.id_product = pf.id_product');
        $query->innerJoin('product_shop', 'ps', 'ps.id_product = p.id_product AND ps.id_shop = ' . $idShop);
        $query->innerJoin('product_lang', 'pl', 'pl.id_product = p.id_product AND pl.id_lang = ' . $idLang . ' AND pl.id_shop = ' . $idShop);
        $query->leftJoin('category_lang', 'cl', 'cl.id_category = p.id_category_default AND cl.id_lang = ' . $idLang . ' AND cl.id_shop = ' . $idShop);
        $query->leftJoin('image', 'img', 'img.id_product = p.id_product AND img.cover = 1');

        if (!empty($search)) {
            $search = pSQL($search);
            $query->where('(pl.name LIKE \'%' . $search . '%\' OR p.reference LIKE \'%' . $search . '%\')');
        }

        $query->orderBy('pf.is_sticky DESC, pf.date_add DESC');
        $query->limit($perPage, $offset);

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($query) ?: [];
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
        return (bool) Db::getInstance()->insert('productfeed', [
            'id_product' => $idProduct,
            'is_sticky' => 0,
            'is_active' => 1,
            'position' => $idProduct,
            'date_add' => date('Y-m-d H:i:s'),
            'date_upd' => date('Y-m-d H:i:s'),
        ]);
    }

    public function removeProduct(int $idProduct): bool
    {
        return (bool) Db::getInstance()->delete('productfeed', 'id_product = ' . $idProduct);
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
        $allowed = ['is_sticky', 'is_active', 'position'];
        if (!in_array($field, $allowed)) {
            return null;
        }

        $query = new DbQuery();
        $query->select($field);
        $query->from('productfeed');
        $query->where('id_product = ' . $idProduct);

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($query);
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

    // ================================================
    // LIKES & SAVES
    // ================================================

    /**
     * Toggle a like — insert if not exists, delete if exists
     * @return array{liked: bool, total: int}
     */
    public function toggleLike(int $idProduct, int $idCustomer): array
    {
        $db = Db::getInstance();
        $exists = (int) $db->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'productfeed_like`
             WHERE id_product = ' . $idProduct . ' AND id_customer = ' . $idCustomer
        );

        if ($exists) {
            $db->delete('productfeed_like', 'id_product = ' . $idProduct . ' AND id_customer = ' . $idCustomer);
            $liked = false;
        } else {
            $db->insert('productfeed_like', [
                'id_product' => $idProduct,
                'id_customer' => $idCustomer,
                'date_add' => date('Y-m-d H:i:s'),
            ]);
            $liked = true;
        }

        $total = (int) $db->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'productfeed_like` WHERE id_product = ' . $idProduct
        );

        return ['liked' => $liked, 'total' => $total];
    }

    /**
     * Toggle a save — insert if not exists, delete if exists
     * @return array{saved: bool, total: int}
     */
    public function toggleSave(int $idProduct, int $idCustomer): array
    {
        $db = Db::getInstance();
        $exists = (int) $db->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'productfeed_save`
             WHERE id_product = ' . $idProduct . ' AND id_customer = ' . $idCustomer
        );

        if ($exists) {
            $db->delete('productfeed_save', 'id_product = ' . $idProduct . ' AND id_customer = ' . $idCustomer);
            $saved = false;
        } else {
            $db->insert('productfeed_save', [
                'id_product' => $idProduct,
                'id_customer' => $idCustomer,
                'date_add' => date('Y-m-d H:i:s'),
            ]);
            $saved = true;
        }

        $total = (int) $db->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'productfeed_save` WHERE id_product = ' . $idProduct
        );

        return ['saved' => $saved, 'total' => $total];
    }

    /**
     * Get all product IDs liked by a customer
     */
    public function getCustomerLikes(int $idCustomer): array
    {
        $rows = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            'SELECT id_product FROM `' . _DB_PREFIX_ . 'productfeed_like` WHERE id_customer = ' . $idCustomer
        );

        return $rows ? array_column($rows, 'id_product') : [];
    }

    /**
     * Get all product IDs saved by a customer
     */
    public function getCustomerSaves(int $idCustomer): array
    {
        $rows = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            'SELECT id_product FROM `' . _DB_PREFIX_ . 'productfeed_save` WHERE id_customer = ' . $idCustomer
        );

        return $rows ? array_column($rows, 'id_product') : [];
    }

    // ================================================
    // ADMIN — Likes Stats
    // ================================================

    /**
     * Products ranked by total likes
     */
    public function getTopLikedProducts(int $idLang, int $idShop, int $page = 1, int $perPage = 50): array
    {
        $offset = ($page - 1) * $perPage;

        $sql = 'SELECT
                    l.id_product,
                    pl.name AS product_name,
                    cl.name AS category_name,
                    img.id_image,
                    COUNT(l.id_productfeed_like) AS total_likes,
                    MAX(l.date_add) AS last_liked_at
                FROM `' . _DB_PREFIX_ . 'productfeed_like` l
                INNER JOIN `' . _DB_PREFIX_ . 'product_lang` pl
                    ON pl.id_product = l.id_product AND pl.id_lang = ' . $idLang . ' AND pl.id_shop = ' . $idShop . '
                LEFT JOIN `' . _DB_PREFIX_ . 'product` p ON p.id_product = l.id_product
                LEFT JOIN `' . _DB_PREFIX_ . 'category_lang` cl
                    ON cl.id_category = p.id_category_default AND cl.id_lang = ' . $idLang . ' AND cl.id_shop = ' . $idShop . '
                LEFT JOIN `' . _DB_PREFIX_ . 'image` img ON img.id_product = l.id_product AND img.cover = 1
                GROUP BY l.id_product
                ORDER BY total_likes DESC, last_liked_at DESC
                LIMIT ' . $offset . ', ' . $perPage;

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql) ?: [];
    }

    /**
     * Recent individual likes with customer info
     */
    public function getRecentLikes(int $idLang, int $idShop, int $page = 1, int $perPage = 50): array
    {
        $offset = ($page - 1) * $perPage;

        $sql = 'SELECT
                    l.id_productfeed_like,
                    l.id_product,
                    l.id_customer,
                    l.date_add,
                    pl.name AS product_name,
                    c.firstname,
                    c.lastname,
                    c.email
                FROM `' . _DB_PREFIX_ . 'productfeed_like` l
                INNER JOIN `' . _DB_PREFIX_ . 'product_lang` pl
                    ON pl.id_product = l.id_product AND pl.id_lang = ' . $idLang . ' AND pl.id_shop = ' . $idShop . '
                INNER JOIN `' . _DB_PREFIX_ . 'customer` c ON c.id_customer = l.id_customer
                ORDER BY l.date_add DESC
                LIMIT ' . $offset . ', ' . $perPage;

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql) ?: [];
    }

    public function getTotalLikedProducts(): int
    {
        return (int) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
            'SELECT COUNT(DISTINCT id_product) FROM `' . _DB_PREFIX_ . 'productfeed_like`'
        );
    }

    public function getTotalLikes(): int
    {
        return (int) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'productfeed_like`'
        );
    }

    // ================================================
    // ADMIN — Saves Stats
    // ================================================

    /**
     * Products ranked by total saves, with list of users who saved
     */
    public function getTopSavedProducts(int $idLang, int $idShop, int $page = 1, int $perPage = 50): array
    {
        $offset = ($page - 1) * $perPage;

        $sql = 'SELECT
                    s.id_product,
                    pl.name AS product_name,
                    cl.name AS category_name,
                    img.id_image,
                    COUNT(s.id_productfeed_save) AS total_saves,
                    MAX(s.date_add) AS last_saved_at
                FROM `' . _DB_PREFIX_ . 'productfeed_save` s
                INNER JOIN `' . _DB_PREFIX_ . 'product_lang` pl
                    ON pl.id_product = s.id_product AND pl.id_lang = ' . $idLang . ' AND pl.id_shop = ' . $idShop . '
                LEFT JOIN `' . _DB_PREFIX_ . 'product` p ON p.id_product = s.id_product
                LEFT JOIN `' . _DB_PREFIX_ . 'category_lang` cl
                    ON cl.id_category = p.id_category_default AND cl.id_lang = ' . $idLang . ' AND cl.id_shop = ' . $idShop . '
                LEFT JOIN `' . _DB_PREFIX_ . 'image` img ON img.id_product = s.id_product AND img.cover = 1
                GROUP BY s.id_product
                ORDER BY total_saves DESC, last_saved_at DESC
                LIMIT ' . $offset . ', ' . $perPage;

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql) ?: [];
    }

    /**
     * Get users who saved a specific product
     */
    public function getSaversForProduct(int $idProduct): array
    {
        $sql = 'SELECT
                    s.id_customer,
                    s.date_add,
                    c.firstname,
                    c.lastname,
                    c.email
                FROM `' . _DB_PREFIX_ . 'productfeed_save` s
                INNER JOIN `' . _DB_PREFIX_ . 'customer` c ON c.id_customer = s.id_customer
                WHERE s.id_product = ' . $idProduct . '
                ORDER BY s.date_add DESC';

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql) ?: [];
    }

    /**
     * Recent individual saves with customer info
     */
    public function getRecentSaves(int $idLang, int $idShop, int $page = 1, int $perPage = 50): array
    {
        $offset = ($page - 1) * $perPage;

        $sql = 'SELECT
                    s.id_productfeed_save,
                    s.id_product,
                    s.id_customer,
                    s.date_add,
                    pl.name AS product_name,
                    c.firstname,
                    c.lastname,
                    c.email
                FROM `' . _DB_PREFIX_ . 'productfeed_save` s
                INNER JOIN `' . _DB_PREFIX_ . 'product_lang` pl
                    ON pl.id_product = s.id_product AND pl.id_lang = ' . $idLang . ' AND pl.id_shop = ' . $idShop . '
                INNER JOIN `' . _DB_PREFIX_ . 'customer` c ON c.id_customer = s.id_customer
                ORDER BY s.date_add DESC
                LIMIT ' . $offset . ', ' . $perPage;

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql) ?: [];
    }

    public function getTotalSavedProducts(): int
    {
        return (int) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
            'SELECT COUNT(DISTINCT id_product) FROM `' . _DB_PREFIX_ . 'productfeed_save`'
        );
    }

    public function getTotalSaves(): int
    {
        return (int) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'productfeed_save`'
        );
    }
}
