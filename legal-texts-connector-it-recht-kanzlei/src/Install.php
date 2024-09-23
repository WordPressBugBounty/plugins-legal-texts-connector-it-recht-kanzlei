<?php
namespace ITRechtKanzlei\LegalTextsConnector;

use DateTime;

class Install {
    const OLD_PLUGIN = 'agb-connector/agb-connector.php';

    const SETUP_INCOMPLETE = 1;
    const SETUP_CONVERTED  = 2;
    const SETUP_COMPLETE   = 3;

    public static function getStatus() {
        $status = (int)get_option(Plugin::OPTION_SETUP_STATUS);
        if ($status === 0) {
            $status = self::SETUP_INCOMPLETE;
            self::setStatus($status);
        }
        return $status;
    }

    public static function isStatus($status) {
        return self::getStatus() === $status;
    }

    public static function isSetup() {
        return in_array(self::getStatus(), [self::SETUP_CONVERTED, self::SETUP_COMPLETE], true);
    }

    public static function setStatus($status) {
        if (!in_array($status, [self::SETUP_INCOMPLETE, self::SETUP_CONVERTED, self::SETUP_COMPLETE], true)) {
            throw new \InvalidArgumentException('Invalid status provided.');
        }
        update_option(Plugin::OPTION_SETUP_STATUS, $status);
    }

    public static function activate() {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if (self::canMigrateOldPlugin()) {
            try {
                // Copy the token first
                update_option(
                    Plugin::OPTION_USER_AUTH_TOKEN,
                    get_option('agb_connector_user_auth_token')
                );

                // Try to migrate the api before
                self::migrateRemoteApi();

                // converting the pages because if migrating the api fails
                // the legal texts are left intact.
                self::convertOldSettings();

            } catch (\RuntimeException $e) {
                // The migration failed. Reset the migrated options to prompt the
                // user with the login screen.
                delete_option(Plugin::OPTION_USER_AUTH_TOKEN);
                self::setStatus(self::SETUP_INCOMPLETE);
            }
        }

        // Disable the old plugin to avoid compatibility issues.
        try {
            if (is_plugin_active(self::OLD_PLUGIN)) {
                deactivate_plugins([self::OLD_PLUGIN]);
            }
        } catch (\Exception $e) {}
    }

    public static function deinstallOldPlugin() {
        deactivate_plugins([self::OLD_PLUGIN]);
        delete_plugins([self::OLD_PLUGIN]);
    }

    public static function cleanPluginConfigs() {
        foreach (wp_load_alloptions(true) as $key => $void) {
            if (strpos($key, Plugin::OPTION_PREFIX) === 0) {
                delete_option($key);
            }
        }
    }

    private static function canMigrateOldPlugin() {
        // Do not convert the settings if the old plugin is disabled.
        if (!is_plugin_active(self::OLD_PLUGIN)
            // Do not convert the settings if this plugin has already been setup.
            || self::isSetup()
        ) {
            return false;
        }

        $oldToken = get_option('agb_connector_user_auth_token', null);
        $agbConnectorOptions = get_option('agb_connector_text_allocations', []);

        // Additionally do not convert if the old plugin is just active but not set up.
        if (empty($oldToken) || empty($agbConnectorOptions)) {
            return false;
        }
        return true;
    }

    private static function migrateRemoteApi() {
        $response = wp_remote_post(Plugin::BACKEND_URL . 'shop-apps-api/Wordpress/migrate.php', ['body' => [
            'token' => get_option(Plugin::OPTION_USER_AUTH_TOKEN),
            'apiUrl' => home_url()
        ]]);
        // Any exception thrown at this point will result in the user being
        // prompted to provide credentials to access the service and
        // establish a new connection.
        if (is_wp_error($response)) {
            throw new \RuntimeException($response->get_error_messages());
        }
        // For php versions >= 7 but < 7.3 hide any error output of json_decode
        // since JSON_THROW_ON_ERROR does not exist yet.
        ob_start();
        $jres = json_decode(wp_remote_retrieve_body($response), true);
        ob_end_clean();
        if (!is_array($jres) || !isset($jres['status'])) {
            throw new \RuntimeException('Unable to parse or process response.');
        }
        if (($jres['status'] !== 'success')
            // This is an ok error as this plugin will be able to receive new legal texts.
            && !(isset($jres['error-code']) && ($jres['error-code'] === 'SYSTEM_ALREADY_EXISTS'))
        ) {
            throw new \RuntimeException(
                'Migration failed.'.(isset($jres['error-code']) ? ' Code: '.$jres['error-code'] : '')
            );
        }
        if (isset($jres['interfaceId']) && isset($jres['sid'])) {
            update_option(Plugin::OPTION_SID, $jres['sid']);
            update_option(Plugin::OPTION_INTERFACE_ID, $jres['interfaceId']);
        }
        if (isset($jres['sessionName']) && isset($jres['sessionId'])) {
            \WP_Session_Tokens::get_instance(get_current_user_id())->update('itrk-session', [
                'itrk_session_name' => $jres['sessionName'],
                'itrk_session_id'   => $jres['sessionId'],
                'expiration'        => time() + 3600,
                'login'             => time(),
            ]);
        }
    }

    private static function convertOldSettings() {
        require_once __DIR__ . '/Helper.php';
        $fs = Helper::getFs();

        $docLocation = dirname(Document::getFilePath('xx', 'xx', 'xx'));
        if (!$fs->is_dir($docLocation) && !$fs->mkdir($docLocation)) {
            throw new \RuntimeException('Unable to create the directory for storing the pdf documents.');
        }

        // Disable processing of the plugin's shortcode. Both plugins use the same shortcode.
        // This can lead to unwanted recursion.
        require_once __DIR__ . '/ShortCodes.php';
        foreach (ShortCodes::settings() as $sc => $void) {
            remove_shortcode($sc);
        }

        $usedPageIds = [];
        foreach (get_option('agb_connector_text_allocations', []) as $optionsKey => $optionsValue) {
            foreach ($optionsValue as $option) {
                $identifier = Document::createIdentifier($optionsKey, $option['language'], $option['country']);
                $document = null;

                if (!array_key_exists($option['pageId'], $usedPageIds)) {
                    $post = get_post($option['pageId']);
                    if (($post === null) || !($post instanceof \WP_Post) || empty(trim($post->post_content))) {
                        // Post has been deleted but is still referenced
                        continue;
                    }

                    $document = new Document(
                        $post->post_title,
                        $option['country'],
                        $option['language'],
                        $post->post_content,
                        $optionsKey,
                        $optionsKey,
                        DateTime::createFromFormat('Y-m-d H:i:s', $post->post_modified_gmt)
                    );

                    $post->post_content = ShortCodes::createShortCode($optionsKey, $option['language'], $option['country']);

                    if (!self::savePost($post)) {
                        throw new \Exception(__('Error saving updated post!', 'legal-texts-connector-it-recht-kanzlei'), 1);
                    }

                    $usedPageIds[$option['pageId']] = $document;
                } else {
                    $document = $usedPageIds[$option['pageId']];
                    $document = new Document(
                        $document->getTitle(),
                        $option['country'],
                        $option['language'],
                        $document->getContent(),
                        $optionsKey,
                        $optionsKey,
                        $document->getCreationDate()
                    );
                }

                $oldName = trailingslashit(wp_upload_dir()['basedir']) .
                    trim(sprintf(
                        '%s%s.pdf',
                        $optionsKey,
                        (($option['language'] != 'de') ? '_' . strtolower($option['language']) : '')
                    ));
                $newName = Document::getFilePath($option['language'], $option['country'], $optionsKey);

                if ($fs->exists($oldName)) {
                    $fs->copy($oldName, $newName);
                }

                update_option($identifier, $document);
            }
        }

        self::setStatus(self::SETUP_CONVERTED);
    }

    private static function savePost(\WP_Post $post) {
        remove_filter('content_save_pre', 'wp_filter_post_kses');
        $postId = wp_update_post($post);

        return !is_wp_error($postId);
    }
}
