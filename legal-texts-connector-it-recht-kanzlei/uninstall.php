<?php
/**
 * The uninstallation routine.
 */
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die();
}

require_once __DIR__ . '/src/Plugin.php';
\ITRechtKanzlei\LegalText\Plugin\Wordpress\Plugin::cleanPluginConfigs();
