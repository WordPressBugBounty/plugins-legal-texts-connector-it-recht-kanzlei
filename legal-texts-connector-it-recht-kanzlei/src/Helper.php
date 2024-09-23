<?php
namespace ITRechtKanzlei\LegalTextsConnector;

class Helper {
    public static function getFs() {
        global $wp_filesystem;

        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            require_once ABSPATH . '/wp-admin/includes/template.php';
        }

        if ($wp_filesystem instanceof WP_Filesystem_Base) {
            return $wp_filesystem;
        }

        ob_start();
        $credentials = request_filesystem_credentials($_SERVER['REQUEST_URI']);
        $data = ob_get_clean();

        if ($credentials === false) {
            $credentials = [];
        }

        $initilized = WP_Filesystem($credentials);

        if (!$initilized || !$wp_filesystem instanceof \WP_Filesystem_Base) {
            throw new \UnexpectedValueException(__(
                'WP_Filesystem cannot be initialized. Please make sure the filesystem is writable.',
                'legal-texts-connector-it-recht-kanzlei'
            ));
        }

        if (!empty($wp_filesystem->errors->errors)) {
            throw new \RuntimeException(sprintf(
                __(
                     // translators: first %s is the method, second %s is a list of error messages.
                    'WP_Filesystem (%1$s) encounterd errors during the initialization: %2$s',
                    'legal-texts-connector-it-recht-kanzlei'
                ),
                $wp_filesystem->method,
                implode('; ', $wp_filesystem->errors->get_error_messages())
            ));
        }

        return $wp_filesystem;
    }

    /**
     * Like php unserialize but throws an Exception incase the data is corrupted.
     * @see https://www.php.net/unserialize
     * @param string $data
     * @param array $options
     * @return mixed
     * @throws \RuntimeException
     */
    public static function unserializeWithException($data, array $options = []) {
        if ($data === 'b:0;') {
            return false;
        }
        $r = @unserialize($data, $options);
        if ($r !== false) {
            return $r;
        }
        $e = error_get_last();
        if (!empty($e) && isset($e['file']) && ($e['file'] === __FILE__)) {
            throw new \RuntimeException($e['message'], (int)$e['type']);
        }
        return $r;
    }

}
