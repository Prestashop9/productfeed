<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_0_1(ProductFeed $module): bool
{
    if (!$module->isRegisteredInHook('displayBackOfficeHeader') && !$module->registerHook('displayBackOfficeHeader')) {
        return false;
    }

    return $module->ensureAdminTab();
}
