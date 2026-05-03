<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_0_3(ProductFeed $module): bool
{
    return $module->ensureDatabase()
        && $module->ensureConfig()
        && $module->ensureHooks()
        && $module->ensureAdminTab();
}
