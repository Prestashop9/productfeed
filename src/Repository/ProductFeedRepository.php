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
}
