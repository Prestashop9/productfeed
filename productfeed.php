<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

class ProductFeed extends Module
{
    const HOOKS = [
        'displayHeader',
        'moduleRoutes',
        'actionProductSave',
        'actionObjectProductDeleteAfter',
    ];

    const CONFIG_KEYS = [
        'PRODUCTFEED_PER_PAGE',
        'PRODUCTFEED_SCROLL_TYPE',
        'PRODUCTFEED_SORT_BY',
        'PRODUCTFEED_SORT_ORDER',
        'PRODUCTFEED_SHOW_CATEGORY',
        'PRODUCTFEED_SHOW_DATE',
        'PRODUCTFEED_SHOW_PRICE',
        'PRODUCTFEED_URL_SLUG',
        'PRODUCTFEED_PAGE_TITLE',
    ];

    public function __construct()
    {
        $this->name = 'productfeed';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'Mohamed';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '9.0.0', 'max' => _PS_VERSION_];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('Product Feed', [], 'Modules.Productfeed.Admin');
        $this->description = $this->trans('Display products as a newsfeed with cards, pagination or infinite scroll, and sticky posts.', [], 'Modules.Productfeed.Admin');
    }

    public function install(): bool
    {
        return parent::install()
            && $this->registerHook(static::HOOKS)
            && $this->installDb()
            && $this->installTab()
            && $this->installConfig();
    }

    public function uninstall(): bool
    {
        return parent::uninstall()
            && $this->uninstallDb()
            && $this->uninstallTab()
            && $this->uninstallConfig();
    }

    private function installDb(): bool
    {
        $sql = file_get_contents(__DIR__ . '/sql/install.sql');
        if ($sql) {
            $sql = str_replace('PREFIX_', _DB_PREFIX_, $sql);
            return (bool) Db::getInstance()->execute($sql);
        }
        return true;
    }

    private function uninstallDb(): bool
    {
        $sql = file_get_contents(__DIR__ . '/sql/uninstall.sql');
        if ($sql) {
            $sql = str_replace('PREFIX_', _DB_PREFIX_, $sql);
            return (bool) Db::getInstance()->execute($sql);
        }
        return true;
    }

    private function installTab(): bool
    {
        $tab = new Tab();
        $tab->active = 1;
        $tab->class_name = 'AdminProductFeed';
        $tab->route_name = 'admin_productfeed_list';
        $tab->name = [];
        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = 'Product Feed';
        }
        $tab->id_parent = (int) Tab::getIdFromClassName('AdminCatalog');
        $tab->module = $this->name;

        return $tab->add();
    }

    private function uninstallTab(): bool
    {
        $idTab = (int) Tab::getIdFromClassName('AdminProductFeed');
        if ($idTab) {
            $tab = new Tab($idTab);
            return $tab->delete();
        }
        return true;
    }

    private function installConfig(): bool
    {
        Configuration::updateValue('PRODUCTFEED_PER_PAGE', 10);
        Configuration::updateValue('PRODUCTFEED_SCROLL_TYPE', 'pagination');
        Configuration::updateValue('PRODUCTFEED_SORT_BY', 'date_add');
        Configuration::updateValue('PRODUCTFEED_SORT_ORDER', 'DESC');
        Configuration::updateValue('PRODUCTFEED_SHOW_CATEGORY', 1);
        Configuration::updateValue('PRODUCTFEED_SHOW_DATE', 1);
        Configuration::updateValue('PRODUCTFEED_SHOW_PRICE', 1);
        Configuration::updateValue('PRODUCTFEED_URL_SLUG', 'feed');
        Configuration::updateValue('PRODUCTFEED_PAGE_TITLE', 'Feed');

        return true;
    }

    private function uninstallConfig(): bool
    {
        foreach (static::CONFIG_KEYS as $key) {
            Configuration::deleteByName($key);
        }
        return true;
    }

    /**
     * Module configuration page (Back Office > Module Manager > Configure)
     */
    public function getContent(): string
    {
        $output = '';

        if (Tools::isSubmit('submit_productfeed_settings')) {
            Configuration::updateValue('PRODUCTFEED_PER_PAGE', (int) Tools::getValue('PRODUCTFEED_PER_PAGE'));
            Configuration::updateValue('PRODUCTFEED_SCROLL_TYPE', Tools::getValue('PRODUCTFEED_SCROLL_TYPE'));
            Configuration::updateValue('PRODUCTFEED_SORT_BY', Tools::getValue('PRODUCTFEED_SORT_BY'));
            Configuration::updateValue('PRODUCTFEED_SORT_ORDER', Tools::getValue('PRODUCTFEED_SORT_ORDER'));
            Configuration::updateValue('PRODUCTFEED_SHOW_CATEGORY', (int) Tools::getValue('PRODUCTFEED_SHOW_CATEGORY'));
            Configuration::updateValue('PRODUCTFEED_SHOW_DATE', (int) Tools::getValue('PRODUCTFEED_SHOW_DATE'));
            Configuration::updateValue('PRODUCTFEED_SHOW_PRICE', (int) Tools::getValue('PRODUCTFEED_SHOW_PRICE'));

            $newSlug = Tools::getValue('PRODUCTFEED_URL_SLUG');
            $newSlug = Tools::str2url($newSlug ?: 'feed');
            Configuration::updateValue('PRODUCTFEED_URL_SLUG', $newSlug);
            Configuration::updateValue('PRODUCTFEED_PAGE_TITLE', Tools::getValue('PRODUCTFEED_PAGE_TITLE'));

            $output .= $this->displayConfirmation($this->trans('Settings updated.', [], 'Modules.Productfeed.Admin'));
        }

        // Link to admin product list
        $adminLink = $this->context->link->getAdminLink('AdminProductFeed');
        $output .= '<div class="panel"><div class="panel-heading"><i class="icon-list"></i> ' . $this->trans('Manage Feed', [], 'Modules.Productfeed.Admin') . '</div>';
        $output .= '<p>' . $this->trans('Manage which products appear in the feed, set sticky posts, and control visibility.', [], 'Modules.Productfeed.Admin') . '</p>';
        $output .= '<a href="' . $adminLink . '" class="btn btn-primary"><i class="icon-external-link"></i> ' . $this->trans('Open Product Feed Manager', [], 'Modules.Productfeed.Admin') . '</a>';

        // Feed front URL
        $slug = Configuration::get('PRODUCTFEED_URL_SLUG') ?: 'feed';
        $feedUrl = $this->context->shop->getBaseURL(true) . $slug;
        $output .= '<hr><p><strong>' . $this->trans('Feed URL:', [], 'Modules.Productfeed.Admin') . '</strong> <a href="' . $feedUrl . '" target="_blank">' . $feedUrl . '</a></p>';
        $output .= '</div>';

        return $output . $this->renderSettingsForm();
    }

    private function renderSettingsForm(): string
    {
        $fields = [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Feed Settings', [], 'Modules.Productfeed.Admin'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->trans('Page title', [], 'Modules.Productfeed.Admin'),
                        'name' => 'PRODUCTFEED_PAGE_TITLE',
                        'desc' => $this->trans('The title displayed on the feed page and in the browser tab.', [], 'Modules.Productfeed.Admin'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('URL slug', [], 'Modules.Productfeed.Admin'),
                        'name' => 'PRODUCTFEED_URL_SLUG',
                        'desc' => $this->trans('The feed will be accessible at: yoursite.com/{slug} (e.g., "feed", "blog", "posts"). Only lowercase letters, numbers, and hyphens.', [], 'Modules.Productfeed.Admin'),
                        'prefix' => $this->context->shop->getBaseURL(true),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Products per page', [], 'Modules.Productfeed.Admin'),
                        'name' => 'PRODUCTFEED_PER_PAGE',
                        'class' => 'fixed-width-sm',
                        'desc' => $this->trans('Number of products to show per page/load.', [], 'Modules.Productfeed.Admin'),
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->trans('Scroll type', [], 'Modules.Productfeed.Admin'),
                        'name' => 'PRODUCTFEED_SCROLL_TYPE',
                        'options' => [
                            'query' => [
                                ['id' => 'pagination', 'name' => $this->trans('Pagination', [], 'Modules.Productfeed.Admin')],
                                ['id' => 'infinite', 'name' => $this->trans('Infinite Scroll', [], 'Modules.Productfeed.Admin')],
                            ],
                            'id' => 'id',
                            'name' => 'name',
                        ],
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->trans('Sort by', [], 'Modules.Productfeed.Admin'),
                        'name' => 'PRODUCTFEED_SORT_BY',
                        'options' => [
                            'query' => [
                                ['id' => 'date_add', 'name' => $this->trans('Date Added', [], 'Modules.Productfeed.Admin')],
                                ['id' => 'date_upd', 'name' => $this->trans('Date Updated', [], 'Modules.Productfeed.Admin')],
                                ['id' => 'position', 'name' => $this->trans('Position', [], 'Modules.Productfeed.Admin')],
                            ],
                            'id' => 'id',
                            'name' => 'name',
                        ],
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->trans('Sort order', [], 'Modules.Productfeed.Admin'),
                        'name' => 'PRODUCTFEED_SORT_ORDER',
                        'options' => [
                            'query' => [
                                ['id' => 'DESC', 'name' => $this->trans('Newest first', [], 'Modules.Productfeed.Admin')],
                                ['id' => 'ASC', 'name' => $this->trans('Oldest first', [], 'Modules.Productfeed.Admin')],
                            ],
                            'id' => 'id',
                            'name' => 'name',
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Show category', [], 'Modules.Productfeed.Admin'),
                        'name' => 'PRODUCTFEED_SHOW_CATEGORY',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'active_on', 'value' => 1, 'label' => $this->trans('Yes', [], 'Admin.Global')],
                            ['id' => 'active_off', 'value' => 0, 'label' => $this->trans('No', [], 'Admin.Global')],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Show date', [], 'Modules.Productfeed.Admin'),
                        'name' => 'PRODUCTFEED_SHOW_DATE',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'active_on', 'value' => 1, 'label' => $this->trans('Yes', [], 'Admin.Global')],
                            ['id' => 'active_off', 'value' => 0, 'label' => $this->trans('No', [], 'Admin.Global')],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Show price', [], 'Modules.Productfeed.Admin'),
                        'name' => 'PRODUCTFEED_SHOW_PRICE',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'active_on', 'value' => 1, 'label' => $this->trans('Yes', [], 'Admin.Global')],
                            ['id' => 'active_off', 'value' => 0, 'label' => $this->trans('No', [], 'Admin.Global')],
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->trans('Save', [], 'Admin.Actions'),
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->submit_action = 'submit_productfeed_settings';
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');

        foreach (static::CONFIG_KEYS as $key) {
            $helper->fields_value[$key] = Configuration::get($key);
        }

        return $helper->generateForm([$fields]);
    }

    /**
     * Add CSS/JS on the feed page
     */
    public function hookDisplayHeader(array $params): void
    {
        // Assets are registered directly in the front controller's setMedia()
    }

    /**
     * Register custom friendly URL for the feed page
     */
    public function hookModuleRoutes(): array
    {
        $slug = Configuration::get('PRODUCTFEED_URL_SLUG') ?: 'feed';

        return [
            'module-productfeed-feed' => [
                'rule' => $slug,
                'keywords' => [
                    'page' => [
                        'regexp' => '[0-9]+',
                        'param' => 'page',
                    ],
                ],
                'controller' => 'feed',
                'params' => [
                    'fc' => 'module',
                    'module' => 'productfeed',
                ],
            ],
        ];
    }

    /**
     * Auto-add new products to the feed
     */
    public function hookActionProductSave(array $params): void
    {
        $idProduct = (int) ($params['id_product'] ?? 0);
        if ($idProduct <= 0) {
            return;
        }

        $repo = new \PrestaShop\Module\ProductFeed\Repository\ProductFeedRepository();
        if (!$repo->productExists($idProduct)) {
            $repo->addProduct($idProduct);
        }
    }

    /**
     * Remove deleted products from the feed
     */
    public function hookActionObjectProductDeleteAfter(array $params): void
    {
        $idProduct = (int) ($params['object']->id ?? 0);
        if ($idProduct <= 0) {
            return;
        }

        $repo = new \PrestaShop\Module\ProductFeed\Repository\ProductFeedRepository();
        $repo->removeProduct($idProduct);
    }
}
