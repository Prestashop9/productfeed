<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_0_2(ProductFeed $module): bool
{
    if (!$module->ensureDatabase()) {
        return false;
    }

    if (!$module->isRegisteredInHook('displayBackOfficeHeader') && !$module->registerHook('displayBackOfficeHeader')) {
        return false;
    }

    return $module->ensureAdminTab();
}
