<?php
namespace ITRechtKanzlei\LegalTextsConnector;
require_once __DIR__ . '/sdk/require_all.php';
require_once __DIR__ . '/ShortCodes.php';
require_once __DIR__ . '/Helper.php';

class ItrkLtiHandler extends \ITRechtKanzlei\LTIHandler {

    public function isTokenValid(string $token): bool {
        return !empty($token) && ($token === get_option(Plugin::OPTION_USER_AUTH_TOKEN, ''));
    }

    public function handleActionGetVersion(): \ITRechtKanzlei\LTIVersionResult {
        $result = new \ITRechtKanzlei\LTIVersionResult();

        $result->includeApacheModules(true);

        $all_plugins = get_plugins();
        foreach ($all_plugins as $key => $plugin) {
            $key = str_replace('.php', '', $key);
            if (($pos = strpos($key, '/')) > 0) {
                $key = substr($key, 0, $pos);
            }

            $result->addPluginInfo($key, $plugin['Version'] ?? '0.0.0');
        }
        return $result;
    }

    public function handleActionGetAccountList(): \ITRechtKanzlei\LTIAccountListResult {
        global $sitepress;

        $result = new \ITRechtKanzlei\LTIAccountListResult();
        $languages = [];

        // WPML
        if ($sitepress instanceof \SitePress) {
            foreach ($sitepress->get_active_languages() as $lk => $lv) {
                $languages[] = $lv['default_locale'] ?? $lv['code'] ?? $lk;
            }
        // WPGlobus
        } elseif (class_exists(\WPGlobus::class) && (($wpglobusConfig = \WPGlobus::Config()) !== null)
            && !empty($wpglobusConfig->enabled_locale)
        ) {
            foreach ($wpglobusConfig->enabled_locale as $locale) {
                $languages[] = $locale;
            }
        }
        $result->addAccount(0, null, $languages);

        return $result;
    }

    public function handleActionPush(\ITRechtKanzlei\LTIPushData $data): \ITRechtKanzlei\LTIPushResult {
        global $wpdb;

        $document = new Document(
            $data->getTitle(),
            $data->getCountry(),
            $data->getLanguageIso639_1(),
            $data->getTextHtml(),
            $data->getType(),
            $data->getLocalizedFileName(),
            new \DateTime()
        );

        $documentUpdated = apply_filters('itrk_process_received_document', $document);
        if ($documentUpdated instanceof Document) {
            $document = $documentUpdated;
        }

        // Since the option contains a timestamp update_option() will always try to update the option.
        // If the function returns false an error occurred.
        $lastDbError = $wpdb->last_error;
        if (!update_option($document->getIdentifier(), $document)) {
            $error = new \ITRechtKanzlei\LTIError(
                __('The legal text could not be stored in the database.', 'legal-texts-connector-it-recht-kanzlei'),
                \ITRechtKanzlei\LTIError::SAVE_DOCUMENT_ERROR
            );
            if ($lastDbError !== $wpdb->last_error) {
                $error->addContext(['wpdb-error' => $wpdb->last_error]);
            }
            throw $error;
        }

        if ($data->hasPdf()) {
            try {
                $fs = Helper::getFs();
                $filePath = Document::getFilePath($data->getLanguageIso639_1(), $data->getCountry(), $data->getType());
                $filePath = str_replace(
                    rtrim(WP_CONTENT_DIR, '/'),
                    rtrim($fs->wp_content_dir(), '/'),
                    $filePath
                );
                $docLocation = dirname($filePath);

                if (!$fs->is_dir($docLocation) && !$fs->mkdir($docLocation)) {
                    throw new \ITRechtKanzlei\LTIError(
                        __(
                            'Unable to create the directory for storing the pdf documents.',
                            'legal-texts-connector-it-recht-kanzlei'
                        ),
                        \ITRechtKanzlei\LTIError::SAVE_PDF_ERROR
                    );
                }
                if (!$fs->put_contents($filePath, $data->getPdf())) {
                    throw new \ITRechtKanzlei\LTIError(
                        __('The file could not be written.', 'legal-texts-connector-it-recht-kanzlei'),
                        \ITRechtKanzlei\LTIError::SAVE_PDF_ERROR
                    );
                }
            } catch (\Exception $e) {
                throw new \ITRechtKanzlei\LTIError(
                    __('The pdf document could not be saved.', 'legal-texts-connector-it-recht-kanzlei')
                        // translators: %s will be replaced with an error message.
                        .' '.sprintf(__('Reason: %s', 'legal-texts-connector-it-recht-kanzlei'), $e->getMessage()),
                    \ITRechtKanzlei\LTIError::SAVE_PDF_ERROR,
                    $e
                );
            }
        }

        $targetUrl = '';
        $postId = ShortCodes::getPageIdForShortCode($document->getShortCode());
        if ($postId > 0) {
            $targetUrl = (string)get_permalink($postId);

            // Trigger a post update for other plugins (eg, cache plugins, WooCommerce_Germanized_Pro, ...)
            wp_update_post([
                'ID'            => $postId,
                'post_date'     => date('Y-m-d H:i:s'),
                'post_date_gmt' => gmdate('Y-m-d H:i:s'),
            ]);

            // WooCommerce_Germanized_Pro: Regenerate PDFs directly.
            // This is necessary in combination with WPML because wc_get_page_id() might not return the correct id.
            if (
                class_exists(\WooCommerce_Germanized_Pro::class)
                && class_exists(\Vendidero\Germanized\Pro\StoreaBill\LegalPages::class)
                && method_exists(\Vendidero\Germanized\Pro\StoreaBill\LegalPages::class, 'refresh_legal_page')
            ) {
                try {
                    \Vendidero\Germanized\Pro\StoreaBill\LegalPages::refresh_legal_page($postId, false);
                } catch (\Throwable $t) {}
            }
        }

        return new \ITRechtKanzlei\LTIPushResult($targetUrl);
    }
}
