<?php
/**
 * The uninstall routine.
 */
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die();
}

if (!class_exists('ITRechtKanzlei\LegalTextsConnector\Install')) {
    require_once __DIR__ . '/src/Plugin.php';
    require_once __DIR__ . '/src/Install.php';
    \ITRechtKanzlei\LegalTextsConnector\Install::cleanPluginConfigs();
}
