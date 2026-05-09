<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_0_4(ProductFeed $module): bool
{
    new \PrestaShop\Module\ProductFeed\Repository\ProductFeedRepository();

    return $module->ensureDatabase()
        && $module->ensureConfig()
        && $module->ensureHooks()
        && $module->ensureAdminTab();
}
