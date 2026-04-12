<?php

declare(strict_types=1);

namespace PrestaShop\Module\ProductFeed\Controller\Admin;

use Configuration;
use Context;
use PrestaShop\Module\ProductFeed\Repository\ProductFeedRepository;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

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

        // Build image URLs for each product
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
        }

        return $this->render('@Modules/productfeed/views/templates/admin/product_list.html.twig', [
            'products' => $products,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_products' => $total,
            'search' => $search,
            'toggle_url' => $this->generateUrl('admin_productfeed_toggle'),
            'feed_url' => $context->link->getModuleLink('productfeed', 'feed'),
            'products_url' => $this->generateUrl('admin_productfeed_list'),
            'likes_url' => $this->generateUrl('admin_productfeed_likes'),
            'saves_url' => $this->generateUrl('admin_productfeed_saves'),
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

    public function likesAction(Request $request): Response
    {
        $repo = new ProductFeedRepository();
        $context = Context::getContext();
        $idLang = (int) $context->language->id;
        $idShop = (int) $context->shop->id;
        $page = max(1, $request->query->getInt('page', 1));
        $view = $request->query->get('view', 'top'); // top | recent

        $totalLikes = $repo->getTotalLikes();
        $totalProducts = $repo->getTotalLikedProducts();

        if ($view === 'recent') {
            $items = $repo->getRecentLikes($idLang, $idShop, $page, 50);
        } else {
            $items = $repo->getTopLikedProducts($idLang, $idShop, $page, 50);
            foreach ($items as &$item) {
                if (!empty($item['id_image'])) {
                    $item['image_url'] = $context->link->getImageLink('', (string) $item['id_image'], 'small_default');
                } else {
                    $item['image_url'] = '';
                }
            }
        }

        return $this->render('@Modules/productfeed/views/templates/admin/likes.html.twig', [
            'items' => $items,
            'view' => $view,
            'current_page' => $page,
            'total_likes' => $totalLikes,
            'total_products' => $totalProducts,
            'products_url' => $this->generateUrl('admin_productfeed_list'),
            'likes_url' => $this->generateUrl('admin_productfeed_likes'),
            'saves_url' => $this->generateUrl('admin_productfeed_saves'),
        ]);
    }

    public function savesAction(Request $request): Response
    {
        $repo = new ProductFeedRepository();
        $context = Context::getContext();
        $idLang = (int) $context->language->id;
        $idShop = (int) $context->shop->id;
        $page = max(1, $request->query->getInt('page', 1));
        $view = $request->query->get('view', 'top'); // top | recent

        $totalSaves = $repo->getTotalSaves();
        $totalProducts = $repo->getTotalSavedProducts();

        if ($view === 'recent') {
            $items = $repo->getRecentSaves($idLang, $idShop, $page, 50);
        } else {
            $items = $repo->getTopSavedProducts($idLang, $idShop, $page, 50);
            foreach ($items as &$item) {
                if (!empty($item['id_image'])) {
                    $item['image_url'] = $context->link->getImageLink('', (string) $item['id_image'], 'small_default');
                } else {
                    $item['image_url'] = '';
                }
            }
        }

        return $this->render('@Modules/productfeed/views/templates/admin/saves.html.twig', [
            'items' => $items,
            'view' => $view,
            'current_page' => $page,
            'total_saves' => $totalSaves,
            'total_products' => $totalProducts,
            'products_url' => $this->generateUrl('admin_productfeed_list'),
            'likes_url' => $this->generateUrl('admin_productfeed_likes'),
            'saves_url' => $this->generateUrl('admin_productfeed_saves'),
        ]);
    }

    public function saversAction(Request $request): JsonResponse
    {
        $repo = new ProductFeedRepository();
        $idProduct = $request->query->getInt('id_product', 0);

        if ($idProduct <= 0) {
            return new JsonResponse(['success' => false], 400);
        }

        $savers = $repo->getSaversForProduct($idProduct);

        return new JsonResponse(['success' => true, 'savers' => $savers]);
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
