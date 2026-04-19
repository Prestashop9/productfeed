<?php

declare(strict_types=1);

namespace PrestaShop\Module\ProductFeed\Controller\Admin;

use Configuration;
use Context;
use Db;
use Language;
use PrestaShop\Module\ProductFeed\Repository\ProductFeedRepository;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tab;
use Tools;

class ProductFeedAdminController extends FrameworkBundleAdminController
{
    public function indexAction(Request $request): Response
    {
        $repo = new ProductFeedRepository();
        $context = Context::getContext();
        $idLang = (int) $context->language->id;
        $idShop = (int) $context->shop->id;

        $page = max(1, $request->query->getInt('page', 1));
        $perPage = 50;
        $search = $request->query->get('search', '');

        $products = $repo->getAllProductsForAdmin($idLang, $idShop, $page, $perPage, $search);
        $total = $repo->getTotalProducts($idShop, $search);
        $totalPages = max(1, (int) ceil($total / $perPage));

        // Build image URLs and format prices
        $locale = $context->getCurrentLocale();
        $currencyIso = $context->currency->iso_code;

        foreach ($products as &$product) {
            if (!empty($product['id_image'])) {
                $product['image_url'] = $context->link->getImageLink(
                    $product['product_name'] ?? '',
                    (string) $product['id_image'],
                    'small_default'
                );
            } else {
                $product['image_url'] = '';
            }

            $product['formatted_price'] = $locale->formatPrice(
                \Product::getPriceStatic((int) $product['id_product'], true),
                $currencyIso
            );
        }

        // Most recent push across the feed (used in the header status line)
        $lastPushRow = Db::getInstance()->getRow('SELECT MAX(pushed_at) AS last_push FROM `' . _DB_PREFIX_ . 'productfeed`');
        $lastPushAgo = $this->humanizeTimeAgo($lastPushRow['last_push'] ?? null);

        return $this->render('@Modules/productfeed/views/templates/admin/product_list.html.twig', [
            'products' => $products,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_products' => $total,
            'search' => $search,
            'feed_name' => Configuration::get('PRODUCTFEED_PAGE_TITLE') ?: 'Feed',
            'feed_slug' => Configuration::get('PRODUCTFEED_URL_SLUG') ?: 'feed',
            'last_push_ago' => $lastPushAgo,
            'toggle_url' => $this->generateUrl('admin_productfeed_toggle'),
            'badge_url' => $this->generateUrl('admin_productfeed_badge'),
            'push_url' => $this->generateUrl('admin_productfeed_push'),
            'reorder_url' => $this->generateUrl('admin_productfeed_reorder'),
            'feed_url' => $context->link->getModuleLink('productfeed', 'feed'),
            'settings_url' => $this->generateUrl('admin_productfeed_settings'),
            'sortable_js_url' => $context->link->getMediaLink('/modules/productfeed/views/js/sortable.min.js'),
            'cdd_css_url' => $context->link->getMediaLink('/modules/modulenotifications/views/css/custom-dropdown.css'),
            'cdd_js_url' => $context->link->getMediaLink('/modules/modulenotifications/views/js/custom-dropdown.js'),
        ]);
    }

    /**
     * Friendly relative time ("18m ago", "2h ago", "3d ago") for header status line.
     */
    private function humanizeTimeAgo(?string $datetime): string
    {
        if (empty($datetime) || $datetime === '0000-00-00 00:00:00') {
            return 'never';
        }
        $diff = time() - strtotime($datetime);
        if ($diff < 60)       return 'just now';
        if ($diff < 3600)     return (int) floor($diff / 60) . 'm ago';
        if ($diff < 86400)    return (int) floor($diff / 3600) . 'h ago';
        if ($diff < 2592000)  return (int) floor($diff / 86400) . 'd ago';
        if ($diff < 31536000) return (int) floor($diff / 2592000) . 'mo ago';
        return (int) floor($diff / 31536000) . 'y ago';
    }

    public function toggleAction(Request $request): JsonResponse
    {
        $idProduct = $request->request->getInt('id_product');
        $field = $request->request->get('field', '');

        if ($idProduct <= 0) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid product ID'], 400);
        }

        $repo = new ProductFeedRepository();
        $success = $repo->toggleField($idProduct, $field);

        if (!$success) {
            return new JsonResponse(['success' => false, 'message' => 'Toggle failed'], 500);
        }

        $newValue = $repo->getFieldValue($idProduct, $field);

        return new JsonResponse([
            'success' => true,
            'new_value' => (int) $newValue,
        ]);
    }

    public function badgeAction(Request $request): JsonResponse
    {
        $idProduct = $request->request->getInt('id_product');
        $action = $request->request->get('badge_action', 'set'); // set | remove

        if ($idProduct <= 0) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid product ID'], 400);
        }

        $repo = new ProductFeedRepository();

        if ($action === 'remove') {
            $success = $repo->removeBadge($idProduct);
            return new JsonResponse(['success' => $success, 'badge_text' => '', 'badge_expires' => '']);
        }

        $badgeText = trim($request->request->get('badge_text', ''));
        $badgeExpires = $request->request->get('badge_expires', '');

        if (empty($badgeText)) {
            return new JsonResponse(['success' => false, 'message' => 'Badge text is required'], 400);
        }

        $expiresDate = !empty($badgeExpires) ? $badgeExpires . ' 23:59:59' : null;
        $success = $repo->updateBadge($idProduct, $badgeText, $expiresDate);

        return new JsonResponse([
            'success' => $success,
            'badge_text' => $badgeText,
            'badge_expires' => $badgeExpires,
        ]);
    }

    public function settingsAction(Request $request): Response
    {
        $context = Context::getContext();
        $successMessage = '';

        if ($request->isMethod('POST')) {
            $feedName = trim($request->request->get('PRODUCTFEED_PAGE_TITLE', 'Feed')) ?: 'Feed';
            Configuration::updateValue('PRODUCTFEED_PAGE_TITLE', $feedName);
            // Sync the admin tab name across all languages so the left-nav entry
            // follows the configured feed name (e.g. "News" instead of "Product Feed").
            $this->syncAdminTabName($feedName);

            $slug = $request->request->get('PRODUCTFEED_URL_SLUG', 'feed');
            $slug = Tools::str2url($slug ?: 'feed');
            Configuration::updateValue('PRODUCTFEED_URL_SLUG', $slug);

            Configuration::updateValue('PRODUCTFEED_PER_PAGE', $request->request->getInt('PRODUCTFEED_PER_PAGE', 10));
            Configuration::updateValue('PRODUCTFEED_SCROLL_TYPE', $request->request->get('PRODUCTFEED_SCROLL_TYPE', 'pagination'));
            Configuration::updateValue('PRODUCTFEED_SORT_BY', $request->request->get('PRODUCTFEED_SORT_BY', 'date_add'));
            Configuration::updateValue('PRODUCTFEED_SORT_ORDER', $request->request->get('PRODUCTFEED_SORT_ORDER', 'DESC'));
            Configuration::updateValue('PRODUCTFEED_SHOW_CATEGORY', $request->request->getInt('PRODUCTFEED_SHOW_CATEGORY', 1));
            Configuration::updateValue('PRODUCTFEED_SHOW_DATE', $request->request->getInt('PRODUCTFEED_SHOW_DATE', 1));
            Configuration::updateValue('PRODUCTFEED_SHOW_PRICE', $request->request->getInt('PRODUCTFEED_SHOW_PRICE', 1));

            $successMessage = 'Settings saved successfully.';
        }

        $slug = Configuration::get('PRODUCTFEED_URL_SLUG') ?: 'feed';

        // Header status line — same as the products page
        $repo = new ProductFeedRepository();
        $totalProducts = $repo->getTotalProducts((int) $context->shop->id, '');
        $lastPushRow = Db::getInstance()->getRow('SELECT MAX(pushed_at) AS last_push FROM `' . _DB_PREFIX_ . 'productfeed`');
        $lastPushAgo = $this->humanizeTimeAgo($lastPushRow['last_push'] ?? null);
        $feedBaseUrl = rtrim($context->shop->getBaseURL(true), '/') . '/';

        return $this->render('@Modules/productfeed/views/templates/admin/settings.html.twig', [
            'settings' => [
                'PRODUCTFEED_PAGE_TITLE' => Configuration::get('PRODUCTFEED_PAGE_TITLE') ?: 'Feed',
                'PRODUCTFEED_URL_SLUG' => $slug,
                'PRODUCTFEED_PER_PAGE' => (int) Configuration::get('PRODUCTFEED_PER_PAGE') ?: 10,
                'PRODUCTFEED_SCROLL_TYPE' => Configuration::get('PRODUCTFEED_SCROLL_TYPE') ?: 'pagination',
                'PRODUCTFEED_SORT_BY' => Configuration::get('PRODUCTFEED_SORT_BY') ?: 'date_add',
                'PRODUCTFEED_SORT_ORDER' => Configuration::get('PRODUCTFEED_SORT_ORDER') ?: 'DESC',
                'PRODUCTFEED_SHOW_CATEGORY' => (int) Configuration::get('PRODUCTFEED_SHOW_CATEGORY'),
                'PRODUCTFEED_SHOW_DATE' => (int) Configuration::get('PRODUCTFEED_SHOW_DATE'),
                'PRODUCTFEED_SHOW_PRICE' => (int) Configuration::get('PRODUCTFEED_SHOW_PRICE'),
            ],
            'feed_name' => Configuration::get('PRODUCTFEED_PAGE_TITLE') ?: 'Feed',
            'feed_slug' => $slug,
            'feed_url' => $context->link->getModuleLink('productfeed', 'feed'),
            'feed_base_url' => $feedBaseUrl,
            'total_products' => $totalProducts,
            'last_push_ago' => $lastPushAgo,
            'products_url' => $this->generateUrl('admin_productfeed_list'),
            'success_message' => $successMessage,
            'cdd_css_url' => $context->link->getMediaLink('/modules/modulenotifications/views/css/custom-dropdown.css'),
            'cdd_js_url' => $context->link->getMediaLink('/modules/modulenotifications/views/js/custom-dropdown.js'),
        ]);
    }

    /**
     * Update the admin tab label for the AdminProductFeed tab across every language.
     * Called whenever the user saves a new feed name, so the left-nav menu matches.
     */
    private function syncAdminTabName(string $name): void
    {
        $idTab = (int) Tab::getIdFromClassName('AdminProductFeed');
        if ($idTab <= 0) {
            return;
        }
        $tab = new Tab($idTab);
        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[(int) $lang['id_lang']] = $name;
        }
        $tab->update();
    }

    /**
     * Bump a product to the top of the feed (pushed_at = NOW).
     */
    public function pushAction(Request $request): JsonResponse
    {
        $idProduct = $request->request->getInt('id_product');
        if ($idProduct <= 0) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid product ID'], 400);
        }

        $repo = new ProductFeedRepository();
        $success = $repo->pushProduct($idProduct);

        return new JsonResponse([
            'success' => $success,
            'pushed_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Apply a manual order: staggered pushed_at timestamps from a list of ids.
     * Expects body: ids[]=1&ids[]=2&ids[]=3 (order matters).
     */
    public function reorderAction(Request $request): JsonResponse
    {
        $ids = $request->request->all('ids');
        if (empty($ids) || !is_array($ids)) {
            return new JsonResponse(['success' => false, 'message' => 'No ids provided'], 400);
        }

        $repo = new ProductFeedRepository();
        $success = $repo->bulkReorder($ids);

        return new JsonResponse(['success' => $success]);
    }
}
