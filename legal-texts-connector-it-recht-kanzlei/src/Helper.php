<?php
namespace ITRechtKanzlei\LegalTextsConnector;

class Helper {
    public static function getFs() {
        global $wp_filesystem;

        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            require_once ABSPATH . '/wp-admin/includes/template.php';
        }

        if ($wp_filesystem instanceof \WP_Filesystem_Base) {
            return $wp_filesystem;
        }

        ob_start();
        $credentials = request_filesystem_credentials('');
        ob_end_clean();

        if ($credentials === false) {
            $credentials = [];
        }

        $initialized = WP_Filesystem($credentials);

        if (!$initialized || !$wp_filesystem instanceof \WP_Filesystem_Base) {
            throw new \UnexpectedValueException(__(
                'WP_Filesystem cannot be initialized. Please make sure the filesystem is writable.',
                'legal-texts-connector-it-recht-kanzlei'
            ));
        }

        if (!empty($wp_filesystem->errors->errors)) {
            throw new \RuntimeException(sprintf(
                // translators: %1$s will be replaced with the method name.
                // translators: %2$s will be replaced with a list of error messages.
                __(
                    'WP_Filesystem (%1$s) encountered errors during the initialization: %2$s',
                    'legal-texts-connector-it-recht-kanzlei'
                ),
                $wp_filesystem->method,
                implode('; ', $wp_filesystem->errors->get_error_messages())
            ));
        }

        return $wp_filesystem;
    }

}
