<?php

declare(strict_types=1);

namespace PrestaShop\Module\ProductFeed\Controller\Admin;

use Configuration;
use Context;
use PrestaShop\Module\ProductFeed\Repository\ProductFeedRepository;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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

        return $this->render('@Modules/productfeed/views/templates/admin/product_list.html.twig', [
            'products' => $products,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_products' => $total,
            'search' => $search,
            'toggle_url' => $this->generateUrl('admin_productfeed_toggle'),
            'badge_url' => $this->generateUrl('admin_productfeed_badge'),
            'feed_url' => $context->link->getModuleLink('productfeed', 'feed'),
            'settings_url' => $this->generateUrl('admin_productfeed_settings'),
        ]);
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
            Configuration::updateValue('PRODUCTFEED_PAGE_TITLE', $request->request->get('PRODUCTFEED_PAGE_TITLE', 'Feed'));

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
            'feed_url' => $context->shop->getBaseURL(true) . $slug,
            'products_url' => $this->generateUrl('admin_productfeed_list'),
            'success_message' => $successMessage,
        ]);
    }

    public function updatePositionAction(Request $request): JsonResponse
    {
        $idProduct = $request->request->getInt('id_product');
        $position = $request->request->getInt('position');

        if ($idProduct <= 0) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid product ID'], 400);
        }

        $result = \Db::getInstance()->update('productfeed', [
            'position' => $position,
            'date_upd' => date('Y-m-d H:i:s'),
        ], 'id_product = ' . $idProduct);

        return new JsonResponse(['success' => (bool) $result]);
    }
}
