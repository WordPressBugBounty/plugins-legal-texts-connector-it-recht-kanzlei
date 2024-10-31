<?php
namespace ITRechtKanzlei\LegalTextsConnector;

class RestEndpoint {

    public static function handleRestRequest(\WP_REST_Request $req) {
        if ((defined('DOING_AJAX') && DOING_AJAX)
            || (defined('DOING_CRON') && DOING_CRON)
        ) {
            return;
        }

        require_once __DIR__ . '/sdk/LTI.php';
        require_once __DIR__ . '/ItrkLtiHandler.php';

        // Weglot Plugin: Disable translation of xml content when handling
        // legal text API requests.
        add_filter('weglot_active_translation', function () {
            return false;
        });
        // Disable translation for TranslatePress-Multilingual
        add_filter('trp_stop_translating_page', function () {
            return true;
        });

        $itrkLtiHandler = new ItrkLtiHandler();
        $lti = new \ITRechtKanzlei\LTI(
            $itrkLtiHandler,
            self::getTargetSystemVersion(),
            \LegalTextsConnector::VERSION
        );

        $xml = $req->get_param('xml');
        $xml = is_string($xml) ? $xml : '';
        // Older versions of WordPress may auto-escape the input.
        if ((($xmlHeadEndPos = strpos($xml, '?>')) > 0) && (strpos(substr($xml, 0, $xmlHeadEndPos), '\"') > 0)) {
            $xml = wp_unslash($xml);
        }

        return $lti->handleRequest($xml);
    }

    public static function registerRoutes() {
        register_rest_route(
            \LegalTextsConnector::PLUGIN_NAME.'/v1',
            'lti',
            [
                [
                    'methods'  => 'POST',
                    'callback' => [self::class, 'handleRestRequest'],
                    'permission_callback' => '__return_true'
                ]
            ]
        );

        add_filter('rest_pre_serve_request', function ($served, $result, $request, $server) {
            $resonseData = $result->get_data();
            if ($resonseData instanceof \ITRechtKanzlei\LTIResult) {
                $server->send_header('Content-Type', 'application/xml; charset=utf-8');
                // Helper to return a XML instead of a JSON response for the REST-API endpoint.
                // $resonseData contains a SimpleXML object that is converted to string.
                // Therefore the data is sanitized already.
                echo $resonseData; // @Review Team: No escaping. See comment above.
                return true;
            }
            return $served;
        }, 10, 4);
    }

    private static function getTargetSystemVersion() {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $version = sprintf("Wordpress: %s", get_bloginfo('version'));
        $all_plugins = get_plugins();

        // get the plugins data
        if (is_plugin_active('woocommerce/woocommerce.php')) {
            $version = sprintf(
                "%s - %s: %s",
                $version,
                $all_plugins['woocommerce/woocommerce.php']['Name'],
                $all_plugins['woocommerce/woocommerce.php']['Version']
            );
        }

        return $version;
    }

}
