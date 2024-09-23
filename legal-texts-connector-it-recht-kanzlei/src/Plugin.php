<?php
namespace ITRechtKanzlei\LegalTextsConnector;

require_once __DIR__ . '/sdk/LTI.php';
require_once __DIR__ . '/ItrkLtiHandler.php';

class Plugin {
    const PLUGIN_NAME    = 'legal-texts-connector-it-recht-kanzlei';

    const OPTION_PREFIX             = 'itrk_lti_';
    const OPTION_DOC_PREFIX         = 'itrk_lti_doc_';
    const OPTION_USER_AUTH_TOKEN    = 'itrk_lti_auth_token';
    const OPTION_SETUP_STATUS       = 'itrk_lti_setup_status';
    const OPTION_SID                = 'itrk_lti_sid';
    const OPTION_INTERFACE_ID       = 'itrk_lti_interface_id';

    const BACKEND_URL = ITRK_SERVICE_URL;
    const TARGET_PAGE = '/Portal/schnittstelle_ansicht.php?iid=%d';

    public function init() {
        $pluginIsOpen = is_admin()
            && isset($_GET['page']) && ($_GET['page'] === SettingsPage::PAGE_SETTINGS);

        // Reset all settings.
        if ($pluginIsOpen
            && isset($_GET[self::PLUGIN_NAME.'-reset']) && ($_GET[self::PLUGIN_NAME.'-reset'] === 'true')
        ) {
            Install::cleanPluginConfigs();
            $url = add_query_arg(['page' => SettingsPage::PAGE_SETTINGS], admin_url('options-general.php'));
            if (wp_redirect($url)) {
                exit;
            }
        }

        if (Install::isSetup()) {
            require_once __DIR__ . '/MailAttachmentHandler.php';
            new MailAttachmentHandler();
            new ShortCodes();
        }

        if (!is_admin()) {
            return;
        }

        if ($pluginIsOpen) {
            add_action('admin_enqueue_scripts', function () {
                wp_enqueue_style(
                    'legal-texts-connector-it-recht-kanzlei',
                    plugins_url('/assets/css/styles.css', __DIR__),
                    [],
                    \LegalTextsConnector::VERSION,
                    'all'
                );
            });
        }

        $settingsPage = new SettingsPage();
        add_action('admin_menu', [$settingsPage, 'addMenu']);
        add_filter(
            'plugin_action_links_' . plugin_basename(dirname(__DIR__) . '/legal-texts-connector-it-recht-kanzlei.php'),
            [$settingsPage, 'addActionLinks']
        );
        if (!Install::isSetup()) {
            add_action('wp_ajax_'.Plugin::PLUGIN_NAME.'-login', [$settingsPage, 'loginDialogAction']);
        }
    }

    public static function getAvailableDocuments($type = null) {
        $r = array_filter(
            wp_load_alloptions(),
            function ($k) use ($type) {
                return (strpos($k, self::OPTION_DOC_PREFIX) === 0)
                    && (!$type || (strpos($k, $type) !== false));
            },
            ARRAY_FILTER_USE_KEY
        );
        ksort($r);
        return $r;
    }

    public static function getSupportedLanguages() {
        return [
            'de' => __('German',     'legal-texts-connector-it-recht-kanzlei'),
            'fr' => __('French',     'legal-texts-connector-it-recht-kanzlei'),
            'en' => __('English',    'legal-texts-connector-it-recht-kanzlei'),
            'es' => __('Spanish',    'legal-texts-connector-it-recht-kanzlei'),
            'it' => __('Italian',    'legal-texts-connector-it-recht-kanzlei'),
            'nl' => __('Dutch',      'legal-texts-connector-it-recht-kanzlei'),
            'pl' => __('Polish',     'legal-texts-connector-it-recht-kanzlei'),
            'sv' => __('Swedish',    'legal-texts-connector-it-recht-kanzlei'),
            'da' => __('Danish',     'legal-texts-connector-it-recht-kanzlei'),
            'cs' => __('Czech',      'legal-texts-connector-it-recht-kanzlei'),
            'sl' => __('Slovenian',  'legal-texts-connector-it-recht-kanzlei'),
            'pt' => __('Portuguese', 'legal-texts-connector-it-recht-kanzlei'),
            'no' => __('Norwegian',  'legal-texts-connector-it-recht-kanzlei'),
            'tr' => __('Turkish',    'legal-texts-connector-it-recht-kanzlei'),
        ];
    }

    public static function getLanguage(string $iso): string {
        $l = self::getSupportedLanguages();
        return $l[$iso] ?? $iso;
    }

    public static function getSupportedCountries() {
        return [
            'DE' => __('Germany',        'legal-texts-connector-it-recht-kanzlei'),
            'AT' => __('Austria',        'legal-texts-connector-it-recht-kanzlei'),
            'CH' => __('Switzerland',    'legal-texts-connector-it-recht-kanzlei'),
            'SE' => __('Sweden',         'legal-texts-connector-it-recht-kanzlei'),
            'ES' => __('Spain',          'legal-texts-connector-it-recht-kanzlei'),
            'IT' => __('Italy',          'legal-texts-connector-it-recht-kanzlei'),
            'PL' => __('Poland',         'legal-texts-connector-it-recht-kanzlei'),
            'GB' => __('England',        'legal-texts-connector-it-recht-kanzlei'),
            'FR' => __('France',         'legal-texts-connector-it-recht-kanzlei'),
            'BE' => __('Belgium',        'legal-texts-connector-it-recht-kanzlei'),
            'NL' => __('Netherlands',    'legal-texts-connector-it-recht-kanzlei'),
            'US' => __('USA',            'legal-texts-connector-it-recht-kanzlei'),
            'CA' => __('Canada',         'legal-texts-connector-it-recht-kanzlei'),
            'IE' => __('Ireland',        'legal-texts-connector-it-recht-kanzlei'),
            'CZ' => __('Czech Republic', 'legal-texts-connector-it-recht-kanzlei'),
            'DK' => __('Denmark',        'legal-texts-connector-it-recht-kanzlei'),
            'LU' => __('Luxembourg',     'legal-texts-connector-it-recht-kanzlei'),
            'SI' => __('Slovenia',       'legal-texts-connector-it-recht-kanzlei'),
            'AU' => __('Australia',      'legal-texts-connector-it-recht-kanzlei'),
            'PT' => __('Portugal',       'legal-texts-connector-it-recht-kanzlei'),
        ];
    }

    public static function getCountry(string $iso): string {
        $c = self::getSupportedCountries();
        return $c[$iso] ?? $iso;
    }

    public static function getSupportedDocumentTypes() {
        return [
            'agb'         => __('Terms and Conditions', 'legal-texts-connector-it-recht-kanzlei'),
            'datenschutz' => __('Privacy', 'legal-texts-connector-it-recht-kanzlei'),
            'widerruf'    => __('Refund Policy', 'legal-texts-connector-it-recht-kanzlei'),
            'impressum'   => __('Imprint', 'legal-texts-connector-it-recht-kanzlei'),
        ];
    }

    public static function getDocumentName(string $type): string {
        $t = self::getSupportedDocumentTypes();
        return $t[$type] ?? $type;
    }

}
