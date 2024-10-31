<?php
namespace ITRechtKanzlei\LegalTextsConnector;

require_once __DIR__ . '/Helper.php';
require_once __DIR__ . '/Message.php';

class SettingsPage {
    const PAGE_SETTINGS = 'legal_texts_connector_settings';

    /** @var Messages[] */
    private $messages = [];

    public function addMenu() {
        add_options_page(
            __('Legal Texts Connector of the IT-Recht Kanzlei', 'legal-texts-connector-it-recht-kanzlei'),
            'IT-Recht Kanzlei',
            'edit_pages',
            self::PAGE_SETTINGS,
            [$this, $this->getPage()]
        );
    }

    public function enqueueScripts() {
        if (Plugin::isSetup()) {
            $this->enqueueSettingsPageScripts();
        } else {
            $this->enqueueLoginDialogScripts();
        }
    }

    private function getPage() {
        if (Plugin::isSetup()) {
            return 'settingsPageView';
        }
        return 'loginDialogView';
    }

    public function addActionLinks($links) {
        $links[] =
            '<a href="' . admin_url('options-general.php?page='.self::PAGE_SETTINGS) . '">' . esc_html(__(
                'Settings',
                'legal-texts-connector-it-recht-kanzlei'
            )) . '</a>';
        $links[] =
            '<a href="' . admin_url('options-general.php?page='.self::PAGE_SETTINGS.'&'.\LegalTextsConnector::PLUGIN_NAME.'-reset=true') . '" style="color:#900">' . esc_html(__(
                'Reset Settings',
                'legal-texts-connector-it-recht-kanzlei'
            )) . '</a>';
        return $links;
    }

    public function loginDialogAction() {
        if (!isset($_REQUEST['nonce'])
            || !wp_verify_nonce(
                    sanitize_text_field(wp_unslash($_REQUEST['nonce'])),
                    \LegalTextsConnector::PLUGIN_NAME.'-action-login'
            )
        ) {
            wp_send_json([
                'status-code' => -1,
                'status' => 'error',
                'error-code' => 'NONCE_EXPIRED',
            ]);
            wp_die();
            return;
        }

        if (empty(get_option(Plugin::OPTION_USER_AUTH_TOKEN))) {
            add_option(
                Plugin::OPTION_USER_AUTH_TOKEN,
                md5(wp_generate_password(32, true, true))
            );
        }

        $data = [
            'email'    => isset($_POST['itrk-email']) ? sanitize_email($_POST['itrk-email']) : '',
            // Please notice that the password is explicitly allowed to contain any combinations of characters,
            // even ones considered harmful like "Robert'); DROP TABLE Students; --" (https://xkcd.com/327/)
            // The password is sent to an external service and validated there.
            'password' => isset($_POST['itrk-password']) ? wp_unslash($_POST['itrk-password']) : '', // @Review Team: See comment above.
            'token'    => get_option(Plugin::OPTION_USER_AUTH_TOKEN),
            'apiUrl'   => home_url(),
            'sid'      => isset($_POST['itrk-sid']) ? (int)$_POST['itrk-sid'] : '',
        ];
        if (($trinityBrand = Plugin::getTrinityBrand()) && !empty($trinityBrand)) {
            $data['trinity'] = $trinityBrand;
        }

        $url = Plugin::BACKEND_URL . 'shop-apps-api/Wordpress/install.php';
        $response = wp_remote_post($url, ['body' => $data]);

        if (is_wp_error($response)) {
            wp_send_json([
                'status-code' => -1,
                'status' => 'error',
                'error-code' => 'CONNECTION',
                'error-details' => $response->get_error_message(),
            ]);
            wp_die();
            return;
        }

        $responseBody = wp_remote_retrieve_body($response);
        // For php versions >= 7 but < 7.3 hide any error output of json_decode
        // since JSON_THROW_ON_ERROR does not exist yet.
        ob_start();
        $json = json_decode($responseBody, true);
        ob_end_clean();

        if (!is_array($json)) {
            $json = [
                'error-code' => 'INVALID_RESPONSE',
                'raw-response' => $responseBody,
            ];
        }
        $json['status-code'] = wp_remote_retrieve_response_code($response);
        if (!isset($json['status'])) {
            $json['status'] = 'error';
        }

        if (($json['status'] === 'success') && ($json['status-code'] == 200)) {
            if (isset($json['sid']) && isset($json['interfaceId'])) {
                update_option(Plugin::OPTION_SID, $json['sid']);
                update_option(Plugin::OPTION_INTERFACE_ID, $json['interfaceId']);
            } else {
                $json['status'] = 'error';
                $json['error-code'] = 'INVALID_RESPONSE';
            }

            if (isset($json['interfaceId']) && isset($json['sessionName']) && isset($json['sessionId'])) {
                \WP_Session_Tokens::get_instance(get_current_user_id())->update('itrk-session', [
                    'itrk_interface_id' => $json['interfaceId'],
                    'itrk_session_name' => $json['sessionName'],
                    'itrk_session_id'   => $json['sessionId'],
                    'expiration'        => time() + 3600,
                    'login'             => time(),
                ]);
            }
        }

        wp_send_json($json);
        wp_die();
    }

    private function enqueueLoginDialogScripts() {
        wp_enqueue_script(
            \LegalTextsConnector::PLUGIN_NAME,
            plugins_url('/assets/js/login.js', __DIR__),
            [],
            \LegalTextsConnector::VERSION
        );
        wp_add_inline_script(
            \LegalTextsConnector::PLUGIN_NAME,
            'const ITRK_LOGIN_MESSAGES = ' . wp_json_encode([
                'UNKNOWN' => __('An unknown error occurred.', 'legal-texts-connector-it-recht-kanzlei'),
                'CONNECTION' => __('A connection to the server of IT-Recht Kanzlei could not be established. Error Details:', 'legal-texts-connector-it-recht-kanzlei'),
                'INVALID_PARAMETERS' => __('Your provided credentials are incomplete.', 'legal-texts-connector-it-recht-kanzlei'),
                'INVALID_CREDENTIALS' => __('Your provided credentials are invalid.', 'legal-texts-connector-it-recht-kanzlei'),
                'MISSING_IMPRINTS' => __('You do not have any imprints configured. Please log into the Client Portal of IT-Recht Kanzlei.', 'legal-texts-connector-it-recht-kanzlei'),
                'IMPRINT_INACTIVE' => __('The selected imprint is not active anymore. Please reload the page and repeat the process.', 'legal-texts-connector-it-recht-kanzlei'),
            ]),
            'before'
        );
    }

    public function loginDialogView() {
        require(__DIR__ . '/views/messages.php');
        require(__DIR__ . '/views/login.php');
    }

    private function enqueueSettingsPageScripts() {
        wp_enqueue_script(
            \LegalTextsConnector::PLUGIN_NAME,
            plugins_url('/assets/js/settings-page.js', __DIR__),
            [],
            \LegalTextsConnector::VERSION
        );
    }

    public function settingsPageView() {
        if (isset($_POST['document_id'])
            // Please note that the regex validates the value of $_POST['document_id'].
            // It is therefore safe to use as the value cannot contain anything that could trigger an exploit.
            && preg_match('/^'.Plugin::OPTION_DOC_PREFIX.'[a-z]{2}_[A-Z]{2}_[a-z]+$/', $_POST['document_id']) // @Review Team: See comment above.
        ) {
            $documentId = $_POST['document_id']; // @Review Team: Validated using the regex above.
            $document = get_option($documentId);
            $documentPath = $document->getFile();
            if (file_exists($documentPath)) {
                unlink($documentPath);
            }
            delete_option($documentId);

            $this->messages[] = new Message(
                Message::SEVERITY_SUCCESS,
                sprintf(
                    // translators: %1$s will be replaced with the document name,
                    // translators: %2$s will be replaced with the document title,
                    // translators: %3$s will be replaced with the country name the document is for and
                    // translators: %4$s will be replaced with the language name the document is for.
                    __(
                        'The document %1$s "%2$s" for the country %3$s in the language %4$s has been deleted.',
                        'legal-texts-connector-it-recht-kanzlei'
                    ),
                    $document->getDocumentName(),
                    $document->getTitle(),
                    $document->getCountryName(),
                    $document->getLanguageName()
                ),
                true
            );
        }

        $session = \WP_Session_Tokens::get_instance(get_current_user_id())->get('itrk-session');
        if (!is_array($session)) {
            $session = [];
        }
        $session = array_replace(['itrk_session_name' => '', 'itrk_session_id' => ''], $session);

        require(__DIR__ . '/views/messages.php');
        require(__DIR__ . '/views/settings-header.php');
        require(__DIR__ . '/views/settings-page.php');

        if (!empty(Plugin::getTrinityBrand())
            && ($infotext = apply_filters('trinity_itrk_settings_page_help_text', null))
            && is_string($infotext)
            && !empty($infotext)
        ) {
            require(__DIR__ . '/views/settings-infotext.php');
        }
    }

}
