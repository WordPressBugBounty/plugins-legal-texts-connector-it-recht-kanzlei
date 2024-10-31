<?php
use ITRechtKanzlei\LegalTextsConnector\Helper;
use ITRechtKanzlei\LegalTextsConnector\Message;
use ITRechtKanzlei\LegalTextsConnector\Plugin;
use ITRechtKanzlei\LegalTextsConnector\SettingsPage;
use ITRechtKanzlei\LegalTextsConnector\ShortCodes;

if (!defined('ABSPATH')) exit;

$tm = !empty(Plugin::getTrinityBrand());

?>
<div class="document-list">
<?php
foreach (ShortCodes::settings() as $tag => $sc) {
    $documents = Plugin::getAvailableDocuments($sc['setting_key']);
    ?>
    <div class="itrk-card itrk-document">
        <h2><?php echo esc_html($sc['name']); ?></h2>
    <?php
    if (empty($documents)) {
    ?>
        <div class="itrk-divider"></div>
        <form method="post" target="_blank"
              action="<?php echo esc_url(sprintf('%s%s', Plugin::BACKEND_URL, 'shop-apps-api/logon.php')); ?>"
        >
            <span><?php esc_html_e('There is no document available for this type yet.', 'legal-texts-connector-it-recht-kanzlei'); ?></span>
            <button type="submit" name="redirect" class="itrk-button-link"><?php esc_html_e('Set up now.', 'legal-texts-connector-it-recht-kanzlei'); ?><span class="dashicons dashicons-external"></span></button>
            <input type="hidden" name="sessionName" value="<?php esc_attr($session['itrk_session_name']); ?>">
            <input type="hidden" name="sessionId" value="<?php echo esc_attr($session['itrk_session_id']); ?>">
            <input type="hidden" name="sid" value="<?php echo esc_attr(get_option(Plugin::OPTION_SID)); ?>">
            <input type="hidden" name="targetPage" value="<?php echo esc_attr(sprintf(Plugin::TARGET_PAGE, get_option(Plugin::OPTION_INTERFACE_ID))); ?>">
        </form>
    </div>
    <?php
        continue;
    }
    ?>
        <div class="itrk-grid-row">
            <div class="itrk-grid-hl"><?php esc_html_e('Country', 'legal-texts-connector-it-recht-kanzlei'); ?></div>
            <div class="itrk-grid-hl"><?php esc_html_e('Language', 'legal-texts-connector-it-recht-kanzlei'); ?></div>
            <?php if (!$tm) { ?><div class="itrk-grid-hl">
                <span><?php esc_html_e('Shortcode', 'legal-texts-connector-it-recht-kanzlei'); ?></span>
                <div class="itrk-tooltip">
                    <div class="dashicons dashicons-info-outline"></div>
                    <div class="itrk-tooltip-text">
                        <div><?php esc_html_e('Copy the respective shortcode and paste it at the position in your Wordpress page where you want the corresponding text to be displayed.', 'legal-texts-connector-it-recht-kanzlei'); ?></div>
                    </div>
                </div>
            </div>
            <?php } ?>
            <div class="itrk-grid-hl"><?php esc_html_e('Last transmission', 'legal-texts-connector-it-recht-kanzlei'); ?></div>
        </div>
        <div class="itrk-divider"></div>
    <?php
    foreach ($documents as $k => $serdoc) {
        try {
            $document  = Helper::unserializeWithException($serdoc, ['Document' => false]);
        } catch (\RuntimeException $e) {
            $country = null;
            $language = null;
            $m = [];
            if (preg_match('/([a-z]{2,3})_([A-Z]{2,3})_([^_]+)$/', $k, $m)) {
                $language = Plugin::getLanguage($m[1]);
                $country  = Plugin::getCountry($m[2]);
            }
            ?>
            <div class="itrk-grid-row itrk-document-row">
                <div><?php echo $country  ? esc_html($country)  : '&mdash;'; ?></div>
                <div><?php echo $language ? esc_html($language) : '&mdash;'; ?></div>
            <?php
            echo (new Message(Message::SEVERITY_ERROR, __(
                'The document is incomplete. Please transfer it again from the client portal. If the error persists, please contact the IT-Recht Kanzlei.',
                'legal-texts-connector-it-recht-kanzlei'
            )))->toHtml();
            ?>
            </div>
            <?php
            continue;
        }

        $shortcode = $document->getShortCode();
        ?>
            <div class="itrk-grid-row itrk-document-row">
                <div><?php echo esc_html($document->getCountryName()); ?></div>
                <div><?php echo esc_html($document->getLanguageName()); ?></div>
                <?php if (!$tm) { ?><div class="itrk-shortcode">
                    <?php if (!ShortCodes::isShortcodeUsed($shortcode)) { ?>
                        <i class="itrk-shortcode-not-used" title="<?php esc_html_e('This shortcode is currently not in use.', 'legal-texts-connector-it-recht-kanzlei'); ?>"></i>
                    <?php } ?>
                    <code><?php echo esc_html($shortcode); ?></code>
                    <a class="dashicons dashicons-admin-page" title="<?php esc_html_e('Copy shortcode', 'legal-texts-connector-it-recht-kanzlei'); ?>"></a>

                    <?php if (!ShortCodes::isShortcodeUsed($shortcode)) { ?>
                        <form class="itrk-inline-form" method="post" action="<?php echo esc_url(add_query_arg([ 'page' => SettingsPage::PAGE_SETTINGS], admin_url('options-general.php'))); ?>">
                            <input type="hidden" name="document_id" value="<?php echo esc_html($k); ?>">
                            <button type="submit" class="itrk-button-link" title="<?php esc_attr_e('Delete document', 'legal-texts-connector-it-recht-kanzlei');?>"><span class="dashicons dashicons-trash"></span></button>
                        </form>
                        <span class="itrk-inactive dashicons dashicons-welcome-view-site" title="<?php esc_attr_e('View Page (Shortcode not in use yet)', 'legal-texts-connector-it-recht-kanzlei'); ?>"></span>
                    <?php } else { ?>
                        <span class="itrk-inactive dashicons dashicons-trash" title="<?php esc_attr_e('Delete document (still in use)', 'legal-texts-connector-it-recht-kanzlei');?>"></span>
                        <a class="dashicons dashicons-welcome-view-site" title="<?php esc_attr_e('View Page', 'legal-texts-connector-it-recht-kanzlei'); ?>" href="<?php esc_url(ShortCodes::getPageLinkShortCode($shortcode)); ?>"></a>
                    <?php } ?>
                </div><?php } ?>
                <div><?php
                    $seconds = time() - $document->getCreationDate()->getTimestamp();
                    $minutes = (int)($seconds / 60);
                    if ($seconds <= 5) {
                        echo '<strong>'.esc_html(__('Now', 'legal-texts-connector-it-recht-kanzlei')).'</strong>';
                    } elseif ($seconds < 60) {
                        // translators: %d will be replaced with the number of seconds
                        echo esc_html(sprintf(__('%d seconds ago', 'legal-texts-connector-it-recht-kanzlei'), $seconds));
                    } elseif ($minutes == 1) {
                        echo esc_html(__('a minute ago', 'legal-texts-connector-it-recht-kanzlei'));
                    } elseif (($minutes > 1) && ($minutes < 60)) {
                        // translators: %d will be replaced with the number of seconds
                        echo esc_html(sprintf(__('%d minutes ago', 'legal-texts-connector-it-recht-kanzlei'), $minutes));
                    } else {
                        // translators: A date time format compliant to https://www.php.net/manual/datetime.format.php
                        echo esc_html($document->getCreationDate()->format(__('M d, Y \a\t g:i a', 'legal-texts-connector-it-recht-kanzlei')));
                    }
                ?></div>
            </div>
        <?php } ?>
    </div>
<?php } ?>
</div>
