<?php
/**
 * Plugin Name: Legal Text Connector of the IT-Recht Kanzlei
 * Plugin Slug: legal-texts-connector-it-recht-kanzlei
 * Plugin URI: https://www.it-recht-kanzlei.de/anleitung-wordpress-praesenzen-und-woocommerce-shops-einrichten-mit-der-rechtstexte-schnittstelle-der-it-recht-kanzlei.html
 * Description: Ensures that your website is always provided with warning-proof legal texts from IT-Recht Kanzlei after booking the GTC service.
 * Author: IT-Recht Kanzlei
 * Author URI: https://www.it-recht-kanzlei.de/
 * Text Domain: legal-texts-connector-it-recht-kanzlei
 * Version: 1.0.10
 * Stable tag: 1.0.10
 * Requires at least: 4.4
 * Tested up to: 6.7
 * Requires PHP: 7.1
 * License: GPLv3 or later
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */
class LegalTextsConnector {
    const VERSION = '1.0.10';
    const PLUGIN_NAME  = 'legal-texts-connector-it-recht-kanzlei';

    public function __construct() {
        if (!defined('PHP_VERSION_ID') || (PHP_VERSION_ID < 70100) || !function_exists('add_action')) {
            return;
        }

        if (!defined('ITRK_SERVICE_URL')) {
            define('ITRK_SERVICE_URL', 'https://www.it-recht-kanzlei.de/');
        }

        add_action('rest_api_init', function () {
            self::includeRequirements();
            require_once(__DIR__ . '/src/RestEndpoint.php');
            \ITRechtKanzlei\LegalTextsConnector\RestEndpoint::registerRoutes();
        });

        add_action('plugins_loaded', function () {
            $this->includeRequirements();
            (new ITRechtKanzlei\LegalTextsConnector\Plugin())->init();
        });

        add_action('activated_plugin', function ($pluginFile, $network_activation) {
            if ($pluginFile != 'legal-texts-connector-it-recht-kanzlei/legal-texts-connector-it-recht-kanzlei.php') {
                return;
            }
            self::includeRequirements();
            wp_redirect(add_query_arg(
                ['page' => ITRechtKanzlei\LegalTextsConnector\SettingsPage::PAGE_SETTINGS],
                admin_url('options-general.php')
            ));
            exit();
        }, 10, 2);

        add_action('init', function () {
            load_plugin_textdomain('legal-texts-connector-it-recht-kanzlei', false, plugin_basename(__DIR__).'/languages');
        });
    }

    private function includeRequirements() {
        if (class_exists(ITRechtKanzlei\LegalTextsConnector\Plugin::class)) {
            return;
        }
        require_once(__DIR__ . '/src/Document.php');
        require_once(__DIR__ . '/src/ShortCodes.php');
        require_once(__DIR__ . '/src/Plugin.php');
        require_once(__DIR__ . '/src/SettingsPage.php');
    }

}

if (!defined('ABSPATH')) exit;

new LegalTextsConnector();
