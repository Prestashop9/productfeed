<?php

declare(strict_types=1);

use PrestaShop\Module\ProductFeed\Repository\ProductFeedRepository;

class ProductFeedApiModuleFrontController extends ModuleFrontController
{
    public $maintenance = false;
    private ProductFeedRepository $repo;

    public function display(): void
    {
    }

    public function postProcess(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->ajaxRender(json_encode(['success' => true]));
            return;
        }

        try {
            if (!$this->authenticate()) {
                http_response_code(401);
                $this->ajaxRender(json_encode(['success' => false, 'error' => 'Invalid or missing API token']));
                return;
            }

            $this->repo = new ProductFeedRepository();
            $action = Tools::getValue('action', 'ping');

            $response = match ($action) {
                'ping' => $this->actionPing(),
                'getFeed' => $this->actionGetFeed(),
                'getProduct' => $this->actionGetProduct(),
                'getCategories' => $this->actionGetCategories(),
                'getPopular' => $this->actionGetPopular(),
                'getBestselling' => $this->actionGetBestselling(),
                'getSettings' => $this->actionGetSettings(),
                'addProduct' => $this->actionAddProduct(),
                'removeProduct' => $this->actionRemoveProduct(),
                'toggleActive' => $this->actionToggleActive(),
                'toggleSticky' => $this->actionToggleSticky(),
                'pushToTop' => $this->actionPushToTop(),
                'updateBadge' => $this->actionUpdateBadge(),
                'removeBadge' => $this->actionRemoveBadge(),
                'reorder' => $this->actionReorder(),
                'updateSettings' => $this->actionUpdateSettings(),
                default => ['success' => false, 'error' => "Unknown action: $action"],
            };

            $this->ajaxRender(json_encode($response));
        } catch (\Throwable $e) {
            http_response_code(500);
            $this->ajaxRender(json_encode(['success' => false, 'error' => $e->getMessage()]));
        }
    }

    private function authenticate(): bool
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $token = '';
        if (str_starts_with($authHeader, 'Bearer ')) {
            $token = trim(substr($authHeader, 7));
        }
        if ($token === '') {
            $token = Tools::getValue('api_key', '');
        }
        if ($token === '') {
            return false;
        }
        $configToken = Configuration::get('PRODUCTFEED_API_TOKEN');
        return $configToken !== false && $configToken !== '' && hash_equals($configToken, $token);
    }

    private function getPostJson(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        return $raw !== '' ? (json_decode($raw, true) ?: []) : [];
    }

    private function getLangId(): int
    {
        return (int) ($this->context->language->id ?? Configuration::get('PS_LANG_DEFAULT'));
    }

    private function getShopId(): int
    {
        return (int) ($this->context->shop->id ?? Configuration::get('PS_SHOP_DEFAULT'));
    }

    private function actionPing(): array
    {
        return ['success' => true, 'message' => 'ProductFeed API is online', 'version' => $this->module->version];
    }

    private function actionGetFeed(): array
    {
        $page = max(1, (int) Tools::getValue('page', 1));
        $perPage = min(100, max(1, (int) Tools::getValue('limit', 20)));
        $category = (int) Tools::getValue('id_category', 0);
        $sort = Tools::getValue('sort', '');

        $products = $this->repo->getProductsForFeed($this->getLangId(), $this->getShopId(), $page, $perPage, 'date_add', 'DESC', $category, $sort);
        $total = $this->repo->getTotalActiveProducts($this->getShopId(), $category);

        $baseUrl = Tools::getShopDomainSsl(true) . __PS_BASE_URI__;
        foreach ($products as &$p) {
            $p['image_url'] = $p['id_image'] ? $baseUrl . 'img/p/' . (new Image((int) $p['id_image']))->getImgPath() . '-home_default.jpg' : null;
        }

        return ['success' => true, 'products' => $products, 'total' => $total, 'page' => $page, 'limit' => $perPage];
    }

    private function actionGetProduct(): array
    {
        $id = (int) Tools::getValue('id_product', 0);
        if ($id <= 0) {
            return ['success' => false, 'error' => 'Missing id_product'];
        }
        if (!$this->repo->productExists($id)) {
            return ['success' => false, 'error' => 'Product not in feed'];
        }

        $products = $this->repo->getProductsForFeed($this->getLangId(), $this->getShopId(), 1, 1, 'date_add', 'DESC', 0, '');
        $all = $this->repo->getAllProductsForAdmin($this->getLangId(), $this->getShopId(), 1, 9999);
        $product = null;
        foreach ($all as $p) {
            if ((int) $p['id_product'] === $id) {
                $product = $p;
                break;
            }
        }

        if (!$product) {
            return ['success' => false, 'error' => 'Product not found in feed'];
        }

        $baseUrl = Tools::getShopDomainSsl(true) . __PS_BASE_URI__;
        $product['image_url'] = $product['id_image'] ? $baseUrl . 'img/p/' . (new Image((int) $product['id_image']))->getImgPath() . '-home_default.jpg' : null;

        return ['success' => true, 'product' => $product];
    }

    private function actionGetCategories(): array
    {
        $categories = $this->repo->getFeedCategories($this->getLangId(), $this->getShopId());
        return ['success' => true, 'categories' => $categories];
    }

    private function actionGetPopular(): array
    {
        $limit = min(100, max(1, (int) Tools::getValue('limit', 20)));
        $products = $this->repo->getPopularProducts($this->getLangId(), $this->getShopId(), $limit);
        return ['success' => true, 'products' => $products];
    }

    private function actionGetBestselling(): array
    {
        $limit = min(100, max(1, (int) Tools::getValue('limit', 20)));
        $products = $this->repo->getBestSellingProducts($this->getLangId(), $this->getShopId(), $limit);
        return ['success' => true, 'products' => $products];
    }

    private function actionGetSettings(): array
    {
        return ['success' => true, 'settings' => [
            'per_page' => (int) Configuration::get('PRODUCTFEED_PER_PAGE') ?: 10,
            'scroll_type' => Configuration::get('PRODUCTFEED_SCROLL_TYPE') ?: 'pagination',
            'show_category' => (bool) Configuration::get('PRODUCTFEED_SHOW_CATEGORY'),
            'show_date' => (bool) Configuration::get('PRODUCTFEED_SHOW_DATE'),
            'show_price' => (bool) Configuration::get('PRODUCTFEED_SHOW_PRICE'),
            'sort_by' => Configuration::get('PRODUCTFEED_SORT_BY') ?: 'date_add',
            'sort_order' => Configuration::get('PRODUCTFEED_SORT_ORDER') ?: 'DESC',
            'page_title' => Configuration::get('PRODUCTFEED_PAGE_TITLE') ?: '',
            'url_slug' => Configuration::get('PRODUCTFEED_URL_SLUG') ?: 'feed',
        ]];
    }

    private function actionAddProduct(): array
    {
        $data = $this->getPostJson();
        $id = (int) ($data['id_product'] ?? 0);
        if ($id <= 0) {
            return ['success' => false, 'error' => 'Missing id_product'];
        }

        $product = new Product($id);
        if (!Validate::isLoadedObject($product)) {
            return ['success' => false, 'error' => 'Product not found'];
        }

        if ($this->repo->productExists($id)) {
            return ['success' => false, 'error' => 'Product already in feed'];
        }

        if (!$this->repo->addProduct($id)) {
            return ['success' => false, 'error' => 'Failed to add product'];
        }

        return ['success' => true, 'message' => 'Product added to feed'];
    }

    private function actionRemoveProduct(): array
    {
        $data = $this->getPostJson();
        $id = (int) ($data['id_product'] ?? 0);
        if ($id <= 0) {
            return ['success' => false, 'error' => 'Missing id_product'];
        }

        if (!$this->repo->productExists($id)) {
            return ['success' => false, 'error' => 'Product not in feed'];
        }

        if (!$this->repo->removeProduct($id)) {
            return ['success' => false, 'error' => 'Failed to remove product'];
        }

        return ['success' => true, 'message' => 'Product removed from feed'];
    }

    private function actionToggleActive(): array
    {
        $data = $this->getPostJson();
        $id = (int) ($data['id_product'] ?? 0);
        if ($id <= 0) {
            return ['success' => false, 'error' => 'Missing id_product'];
        }

        $this->repo->toggleField($id, 'is_active');
        $newValue = (bool) $this->repo->getFieldValue($id, 'is_active');

        return ['success' => true, 'id_product' => $id, 'is_active' => $newValue];
    }

    private function actionToggleSticky(): array
    {
        $data = $this->getPostJson();
        $id = (int) ($data['id_product'] ?? 0);
        if ($id <= 0) {
            return ['success' => false, 'error' => 'Missing id_product'];
        }

        $this->repo->toggleField($id, 'is_sticky');
        $newValue = (bool) $this->repo->getFieldValue($id, 'is_sticky');

        return ['success' => true, 'id_product' => $id, 'is_sticky' => $newValue];
    }

    private function actionPushToTop(): array
    {
        $data = $this->getPostJson();
        $id = (int) ($data['id_product'] ?? 0);
        if ($id <= 0) {
            return ['success' => false, 'error' => 'Missing id_product'];
        }

        if (!$this->repo->pushProduct($id)) {
            return ['success' => false, 'error' => 'Failed to push product'];
        }

        return ['success' => true, 'message' => 'Product pushed to top'];
    }

    private function actionUpdateBadge(): array
    {
        $data = $this->getPostJson();
        $id = (int) ($data['id_product'] ?? 0);
        $text = $data['badge_text'] ?? '';
        $expires = $data['badge_expires'] ?? null;

        if ($id <= 0) {
            return ['success' => false, 'error' => 'Missing id_product'];
        }
        if ($text === '') {
            return ['success' => false, 'error' => 'Missing badge_text'];
        }

        if (!$this->repo->updateBadge($id, $text, $expires)) {
            return ['success' => false, 'error' => 'Failed to update badge'];
        }

        return ['success' => true, 'message' => 'Badge updated', 'data' => ['id_product' => $id, 'badge_text' => $text, 'badge_expires' => $expires]];
    }

    private function actionRemoveBadge(): array
    {
        $data = $this->getPostJson();
        $id = (int) ($data['id_product'] ?? 0);
        if ($id <= 0) {
            return ['success' => false, 'error' => 'Missing id_product'];
        }

        $this->repo->removeBadge($id);
        return ['success' => true, 'message' => 'Badge removed'];
    }

    private function actionReorder(): array
    {
        $data = $this->getPostJson();
        $ids = $data['product_ids'] ?? [];
        if (empty($ids) || !is_array($ids)) {
            return ['success' => false, 'error' => 'Missing product_ids array'];
        }

        if (!$this->repo->bulkReorder($ids)) {
            return ['success' => false, 'error' => 'Failed to reorder'];
        }

        return ['success' => true, 'message' => 'Feed reordered', 'order' => $ids];
    }

    private function actionUpdateSettings(): array
    {
        $data = $this->getPostJson();
        $map = [
            'per_page' => 'PRODUCTFEED_PER_PAGE',
            'scroll_type' => 'PRODUCTFEED_SCROLL_TYPE',
            'show_category' => 'PRODUCTFEED_SHOW_CATEGORY',
            'show_date' => 'PRODUCTFEED_SHOW_DATE',
            'show_price' => 'PRODUCTFEED_SHOW_PRICE',
            'sort_by' => 'PRODUCTFEED_SORT_BY',
            'sort_order' => 'PRODUCTFEED_SORT_ORDER',
            'page_title' => 'PRODUCTFEED_PAGE_TITLE',
            'url_slug' => 'PRODUCTFEED_URL_SLUG',
        ];

        $updated = [];
        foreach ($map as $key => $configKey) {
            if (isset($data[$key])) {
                Configuration::updateValue($configKey, $data[$key]);
                $updated[] = $key;
            }
        }

        if (empty($updated)) {
            return ['success' => false, 'error' => 'No settings to update'];
        }

        return ['success' => true, 'message' => 'Settings updated', 'updated' => $updated];
    }
}
