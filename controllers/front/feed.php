<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

class ProductFeedFeedModuleFrontController extends ModuleFrontController
{
    public function setMedia(): void
    {
        parent::setMedia();

        $this->registerStylesheet(
            'module-productfeed-feed',
            'modules/productfeed/views/css/feed.css',
            ['media' => 'all', 'priority' => 150]
        );
        $this->registerJavascript(
            'module-productfeed-feed',
            'modules/productfeed/views/js/feed.js',
            ['position' => 'bottom', 'priority' => 150]
        );
    }

    public function initContent(): void
    {
        parent::initContent();

        $repo = new \PrestaShop\Module\ProductFeed\Repository\ProductFeedRepository();

        $idLang = (int) $this->context->language->id;
        $idShop = (int) $this->context->shop->id;

        // Handle AJAX requests for sidebar widgets
        if (Tools::getValue('ajax') === '1' && Tools::getValue('action') === 'sidebar') {
            $this->ajaxSidebar($repo, $idLang, $idShop);
            return;
        }

        $page = max(1, (int) Tools::getValue('page', 1));
        $perPage = (int) Configuration::get('PRODUCTFEED_PER_PAGE') ?: 10;
        $sortBy = Configuration::get('PRODUCTFEED_SORT_BY') ?: 'date_add';
        $sortOrder = Configuration::get('PRODUCTFEED_SORT_ORDER') ?: 'DESC';
        $scrollType = Configuration::get('PRODUCTFEED_SCROLL_TYPE') ?: 'pagination';
        $idCategory = (int) Tools::getValue('id_category', 0);
        $feedSort = Tools::getValue('feed_sort', '');
        $feedSort = in_array($feedSort, ['popular', 'bestselling']) ? $feedSort : '';

        $products = $repo->getProductsForFeed($idLang, $idShop, $page, $perPage, $sortBy, $sortOrder, $idCategory, $feedSort);
        $total = $repo->getTotalActiveProducts($idShop, $idCategory);
        $totalPages = max(1, (int) ceil($total / $perPage));

        // Enrich main feed products
        $enriched = $this->enrichProducts($products);

        // For AJAX infinite scroll requests (main feed)
        if (Tools::getValue('ajax') === '1') {
            header('Content-Type: application/json');
            echo json_encode([
                'products' => $enriched,
                'current_page' => $page,
                'total_pages' => $totalPages,
                'has_more' => $page < $totalPages,
            ]);
            exit;
        }

        // Get active category name for filter header
        $activeCategoryName = '';
        if ($idCategory > 0) {
            $catLang = new \Category($idCategory, $idLang);
            $activeCategoryName = $catLang->name ?? '';
        }

        // Get all categories in the feed for filter list
        $feedCategories = $repo->getFeedCategories($idLang, $idShop);

        // Sidebar data: fetch pools, shuffle, take 5 each
        $popularPool = $repo->getPopularProducts($idLang, $idShop, 20);
        $bestSellingPool = $repo->getBestSellingProducts($idLang, $idShop, 20);

        shuffle($popularPool);
        shuffle($bestSellingPool);

        $popularItems = $this->enrichSidebarProducts(array_slice($popularPool, 0, 5));
        $bestSellingItems = $this->enrichSidebarProducts(array_slice($bestSellingPool, 0, 5));

        $this->context->smarty->assign([
            'feed_products' => $enriched,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_products' => $total,
            'per_page' => $perPage,
            'has_more' => $page < $totalPages,
            'scroll_type' => $scrollType,
            'show_category' => (bool) Configuration::get('PRODUCTFEED_SHOW_CATEGORY'),
            'show_date' => (bool) Configuration::get('PRODUCTFEED_SHOW_DATE'),
            'show_price' => (bool) Configuration::get('PRODUCTFEED_SHOW_PRICE'),
            'feed_ajax_url' => $this->context->link->getModuleLink('productfeed', 'feed'),
            'feed_page_title' => Configuration::get('PRODUCTFEED_PAGE_TITLE') ?: 'Feed',
            'popular_products' => $popularItems,
            'bestselling_products' => $bestSellingItems,
            'feed_categories' => $feedCategories,
            'active_category_id' => $idCategory,
            'active_category_name' => $activeCategoryName,
            'feed_sort' => $feedSort,
        ]);

        $this->setTemplate('module:productfeed/views/templates/front/feed.tpl');
    }

    /**
     * AJAX handler for sidebar "Show More" — returns a fresh random batch
     */
    private function ajaxSidebar(
        \PrestaShop\Module\ProductFeed\Repository\ProductFeedRepository $repo,
        int $idLang,
        int $idShop
    ): void {
        $type = Tools::getValue('type', 'popular'); // popular | bestselling

        if ($type === 'bestselling') {
            $pool = $repo->getBestSellingProducts($idLang, $idShop, 20);
        } else {
            $pool = $repo->getPopularProducts($idLang, $idShop, 20);
        }

        shuffle($pool);
        $items = $this->enrichSidebarProducts(array_slice($pool, 0, 5));

        header('Content-Type: application/json');
        echo json_encode(['products' => $items]);
        exit;
    }

    /**
     * Enrich full feed product data (image, price, URLs)
     */
    private function enrichProducts(array $products): array
    {
        $idLang = (int) $this->context->language->id;
        $purchasedIds = $this->getCustomerPurchasedProductIds();
        $libraryUrl = $this->getLibraryUrl();
        $enriched = [];

        foreach ($products as $product) {
            $idProduct = (int) $product['id_product'];
            $linkRewrite = $product['link_rewrite'];

            $productUrl = $this->context->link->getProductLink(
                $idProduct,
                $linkRewrite,
                $product['category_rewrite'] ?? null,
                null,
                $idLang
            );

            $imageUrl = '';
            if (!empty($product['id_image'])) {
                $imageUrl = $this->context->link->getImageLink(
                    $linkRewrite,
                    (string) $product['id_image'],
                    'product_main_2x'
                );
            }

            $price = Product::getPriceStatic($idProduct, true);
            $originalPrice = Product::getPriceStatic($idProduct, true, null, 6, null, false, false);
            $locale = $this->context->getCurrentLocale();
            $currencyIso = $this->context->currency->iso_code;
            $formattedPrice = $locale->formatPrice($price, $currencyIso);
            $formattedOriginalPrice = '';
            $discountPercent = 0;
            if ($originalPrice > $price && $originalPrice > 0) {
                $formattedOriginalPrice = $locale->formatPrice($originalPrice, $currencyIso);
                $discountPercent = (int) round((1 - $price / $originalPrice) * 100);
            }

            $addToCartUrl = $this->context->link->getPageLink('cart', true, null, [
                'add' => 1,
                'id_product' => $idProduct,
                'token' => Tools::getToken(false),
            ]);

            $buyNowUrl = $this->context->link->getPageLink('cart', true, null, [
                'add' => 1,
                'id_product' => $idProduct,
                'token' => Tools::getToken(false),
                'action' => 'show',
            ]);

            $enriched[] = [
                'id_product' => $idProduct,
                'name' => $product['name'],
                'description_short' => $product['description_short'],
                'url' => $productUrl,
                'image_url' => $imageUrl,
                'id_category' => (int) ($product['id_category_default'] ?? 0),
                'category_name' => $product['category_name'] ?? '',
                'price' => $formattedPrice,
                'original_price' => $formattedOriginalPrice,
                'discount_percent' => $discountPercent,
                'is_sticky' => (bool) $product['is_sticky'],
                'badge_text' => $this->getActiveBadge($product),
                // Time-ago reflects feed freshness (pushed_at), not product creation
                'date_add' => !empty($product['pushed_at']) ? $product['pushed_at'] : $product['product_date_add'],
                'date_upd' => $product['product_date_upd'],
                'add_to_cart_url' => $addToCartUrl,
                'buy_now_url' => $buyNowUrl,
                'is_purchased' => in_array($idProduct, $purchasedIds, true),
                'library_url' => $libraryUrl,
            ];
        }

        return $enriched;
    }

    /**
     * Resolve the customer library URL. Uses the digitalaccess module's friendly route when installed
     * (auto-detects DIGITALACCESS_FRIENDLY_URL config — defaults to "mylibrary"). Falls back to /mylibrary otherwise.
     */
    private function getLibraryUrl(): string
    {
        if (Module::isInstalled('digitalaccess') && Module::isEnabled('digitalaccess')) {
            return $this->context->link->getModuleLink('digitalaccess', 'dashboard');
        }

        $slug = Configuration::get('DIGITALACCESS_FRIENDLY_URL') ?: 'mylibrary';

        return $this->context->link->getBaseLink() . ltrim($slug, '/');
    }

    /**
     * Return product IDs the current customer has purchased (non-cancelled/refunded/payment-error orders).
     * Empty array if guest.
     */
    private function getCustomerPurchasedProductIds(): array
    {
        $idCustomer = (int) $this->context->customer->id;
        if ($idCustomer <= 0) {
            return [];
        }

        $sql = 'SELECT DISTINCT od.product_id
                FROM `' . _DB_PREFIX_ . 'order_detail` od
                INNER JOIN `' . _DB_PREFIX_ . 'orders` o ON o.id_order = od.id_order
                WHERE o.id_customer = ' . $idCustomer . '
                AND o.current_state NOT IN (6, 7, 8)';

        $rows = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

        return $rows ? array_map('intval', array_column($rows, 'product_id')) : [];
    }

    /**
     * Enrich sidebar product data (compact — image, name, price, URL)
     */
    private function enrichSidebarProducts(array $products): array
    {
        $idLang = (int) $this->context->language->id;
        $enriched = [];

        foreach ($products as $product) {
            $idProduct = (int) $product['id_product'];
            $linkRewrite = $product['link_rewrite'];

            $productUrl = $this->context->link->getProductLink(
                $idProduct,
                $linkRewrite,
                $product['category_rewrite'] ?? null,
                null,
                $idLang
            );

            $imageUrl = '';
            if (!empty($product['id_image'])) {
                $imageUrl = $this->context->link->getImageLink(
                    $linkRewrite,
                    (string) $product['id_image'],
                    'small_default'
                );
            }

            $price = Product::getPriceStatic($idProduct, true);
            $locale = $this->context->getCurrentLocale();
            $formattedPrice = $locale->formatPrice($price, $this->context->currency->iso_code);

            $enriched[] = [
                'id_product' => $idProduct,
                'name' => $product['name'],
                'url' => $productUrl,
                'image_url' => $imageUrl,
                'category_name' => $product['category_name'] ?? '',
                'price' => $formattedPrice,
            ];
        }

        return $enriched;
    }

    /**
     * Return badge text only if not expired
     */
    private function getActiveBadge(array $product): string
    {
        $text = $product['badge_text'] ?? '';
        if (empty($text)) {
            return '';
        }

        $expires = $product['badge_expires'] ?? null;
        if ($expires && strtotime($expires) < time()) {
            return '';
        }

        return $text;
    }

    public function getBreadcrumbLinks(): array
    {
        $pageTitle = Configuration::get('PRODUCTFEED_PAGE_TITLE') ?: 'Feed';
        $breadcrumb = parent::getBreadcrumbLinks();
        $breadcrumb['links'][] = [
            'title' => $pageTitle,
            'url' => $this->context->link->getModuleLink('productfeed', 'feed'),
        ];

        return $breadcrumb;
    }

    public function getTemplateVarPage(): array
    {
        $pageTitle = Configuration::get('PRODUCTFEED_PAGE_TITLE') ?: 'Feed';
        $page = parent::getTemplateVarPage();
        $page['meta']['title'] = $pageTitle;
        $page['meta']['description'] = $this->trans('Browse our latest products', [], 'Modules.Productfeed.Shop');

        return $page;
    }
}
