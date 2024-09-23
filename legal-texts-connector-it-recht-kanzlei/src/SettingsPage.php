<?php
namespace ITRechtKanzlei\LegalTextsConnector;

require_once __DIR__ . '/Helper.php';
require_once __DIR__ . '/Message.php';

class SettingsPage {
    const PAGE_SETTINGS = 'legal_texts_connector_settings';

    /** @var Messages[] */
    private $messages = [];

    public function addMenu() {
        $hook = add_options_page(
            __('Legal Texts Connector of the IT-Recht Kanzlei', 'legal-texts-connector-it-recht-kanzlei'),
            'IT-Recht Kanzlei',
            'edit_pages',
            self::PAGE_SETTINGS,
            [$this, $this->getPage()]
        );
        add_action('load-' . $hook, [$this, 'setup']);
    }

    private function getPage() {
        switch (Install::getStatus()) {
            case Install::SETUP_CONVERTED:
            case Install::SETUP_COMPLETE:
                return 'settingsPageView';
            case Install::SETUP_INCOMPLETE:
            default:
                return 'loginDialogView';
        }
    }

    public function setup() {
        if (Install::isStatus(Install::SETUP_CONVERTED, FILTER_SANITIZE_STRING)) {
            if (filter_input(INPUT_POST, 'uninstall')) {
                Install::deinstallOldPlugin();
                Install::setStatus(Install::SETUP_COMPLETE);
                $this->messages[] = new Message(
                    Message::SEVERITY_SUCCESS,
                    __(
                        'The old T&C Connector has been deleted successfully.',
                        'legal-texts-connector-it-recht-kanzlei'
                    ),
                    true
                );
            } elseif (filter_input(INPUT_POST, 'keep', FILTER_SANITIZE_STRING)) {
                Install::setStatus(Install::SETUP_COMPLETE);
                $this->messages[] = new Message(
                    Message::SEVERITY_WARNING,
                    __(
                        'Please note that the old T&C Connector and this plugin '
                            .'cannot be used together, as conflicts may occur. '
                            .'If you uninstall this plugin and reactivate the old '
                            .'T&C Connector, you will have to set it up again.',
                        'legal-texts-connector-it-recht-kanzlei'
                    ),
                    true
                );
            } else {
                $this->messages[] = new Message(
                    Message::SEVERITY_SUCCESS,
                    __(
                        'We have found settings of the old T&C Connector plugin and '
                            .'successfully transferred them to this plugin. The old '
                            .'plugin has been deactivated. Please check whether all '
                            .'desired legal texts are correctly integrated. If '
                            .'necessary, transfer the texts from the client portal '
                            .'again. Please note that errors may occur when '
                            .'reactivating the old plugin.',
                        'legal-texts-connector-it-recht-kanzlei'
                    ),
                    true
                );
            }
        }
    }

    public function addActionLinks($links) {
        $links[] =
            '<a href="' . admin_url('options-general.php?page='.self::PAGE_SETTINGS) . '">' . esc_html(__(
                'Settings',
                'legal-texts-connector-it-recht-kanzlei'
            )) . '</a>';
        $links[] =
            '<a href="' . admin_url('options-general.php?page='.self::PAGE_SETTINGS.'&'.Plugin::PLUGIN_NAME.'-reset=true') . '" style="color:#900">' . esc_html(__(
                'Reset Settings',
                'legal-texts-connector-it-recht-kanzlei'
            )) . '</a>';
        return $links;
    }

    public function loginDialogAction() {
        if (!wp_verify_nonce($_REQUEST['nonce'], Plugin::PLUGIN_NAME.'-action-login')) {
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
            'password' => isset($_POST['itrk-password']) ? wp_unslash($_POST['itrk-password']) : '',
            'token'    => get_option(Plugin::OPTION_USER_AUTH_TOKEN),
            'apiUrl'   => home_url(),
            'sid'      => isset($_POST['itrk-sid']) ? (int)$_POST['itrk-sid'] : '',
        ];

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
                Install::setStatus(Install::SETUP_COMPLETE);
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

    public function loginDialogView() {
        require(__DIR__ . '/views/messages.php');
        require(__DIR__ . '/views/login.php');
    }

    public function settingsPageView() {
        if (isset($_POST['document_id'])
            && preg_match('/^'.Plugin::OPTION_DOC_PREFIX.'[a-z]{2}_[A-Z]{2}_[a-z]+$/', $_POST['document_id'])
        ) {
            $document = get_option($_POST['document_id']);
            $documentPath = $document->getFile();
            if ( file_exists($documentPath) ) {
                unlink($documentPath);
            }
            delete_option( $_POST['document_id'] );

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
        if (Install::isStatus(Install::SETUP_CONVERTED)) {
            require(__DIR__ . '/views/uninstall-old-plugin.php');
        }
        require(__DIR__ . '/views/settings-header.php');
        require(__DIR__ . '/views/settings-page.php');
    }

}
