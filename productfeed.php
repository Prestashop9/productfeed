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
        'displayBackOfficeHeader',
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
        $this->version = '1.0.2';
        $this->author = 'PrestashopMD';
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
        return $this->ensureDatabase();
    }

    public function ensureDatabase(): bool
    {
        return $this->executeSqlFile(__DIR__ . '/sql/install.sql');
    }

    private function executeSqlFile(string $path): bool
    {
        $sql = file_get_contents($path);
        if (!$sql) {
            return true;
        }

        $sql = str_replace('PREFIX_', _DB_PREFIX_, $sql);
        $queries = preg_split('/;\s*[\r\n]+/', trim($sql));
        foreach ($queries as $query) {
            $query = trim($query);
            if ($query !== '' && !Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }

    private function uninstallDb(): bool
    {
        return $this->executeSqlFile(__DIR__ . '/sql/uninstall.sql');
    }

    private function installTab(): bool
    {
        return $this->ensureAdminTab();
    }

    public function ensureAdminTab(): bool
    {
        static $checked = false;
        if ($checked) {
            return true;
        }
        $checked = true;

        $idTab = (int) Tab::getIdFromClassName('AdminProductFeed');
        $tab = new Tab();
        if ($idTab > 0) {
            $tab = new Tab($idTab);
        }

        $feedName = Configuration::get('PRODUCTFEED_PAGE_TITLE') ?: 'Feed';
        $idParent = (int) Tab::getIdFromClassName('AdminCatalog');

        $needsUpdate = $idTab <= 0
            || (int) $tab->active !== 1
            || $tab->route_name !== 'admin_productfeed_list'
            || $tab->module !== $this->name
            || ($idParent > 0 && (int) $tab->id_parent !== $idParent);

        $tab->active = 1;
        $tab->class_name = 'AdminProductFeed';
        $tab->route_name = 'admin_productfeed_list';
        $tab->module = $this->name;
        if ($idParent > 0) {
            $tab->id_parent = $idParent;
        }

        foreach (Language::getLanguages(true) as $lang) {
            $idLang = (int) $lang['id_lang'];
            if (($tab->name[$idLang] ?? '') !== $feedName) {
                $tab->name[$idLang] = $feedName;
                $needsUpdate = true;
            }
        }

        if (!$needsUpdate) {
            return true;
        }

        return $idTab > 0 ? (bool) $tab->update() : (bool) $tab->add();
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
     * Redirect Module Manager > Configure to the admin controller
     */
    public function getContent(): string
    {
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminProductFeed'));

        return '';
    }

    /**
     * Add CSS/JS on feed pages
     */
    public function hookDisplayHeader(array $params): string
    {
        return '';
    }

    /**
     * Keep the Catalog menu entry present on existing installs.
     */
    public function hookDisplayBackOfficeHeader(array $params): string
    {
        $this->ensureAdminTab();

        return '';
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
