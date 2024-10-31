<?php
use ITRechtKanzlei\LegalTextsConnector\Plugin;

if (!defined('ABSPATH')) exit;
?>
<div id="itrk-login-dialog" class="itrk-card"
      data-href="<?php echo esc_attr(admin_url('admin-ajax.php'));?>"
      data-action="<?php echo esc_attr(\LegalTextsConnector::PLUGIN_NAME.'-login');?>"
      data-nonce="<?php echo esc_attr(wp_create_nonce(\LegalTextsConnector::PLUGIN_NAME.'-action-login'));?>"
>
    <div id="itrk-kanzlei-logo"></div>
    <div class="itrk-divider"></div>
    <h2><?php esc_html_e('In a few steps to legal texts on this Wordpress installation that are safe from warning letters', 'legal-texts-connector-it-recht-kanzlei'); ?></h2>
    <?php if (empty(Plugin::getTrinityBrand())) { ?>
    <p><strong><?php esc_html_e('Note:', 'legal-texts-connector-it-recht-kanzlei'); ?></strong> <?php esc_html_e('Prerequisite for the use of plugins is the booking of the AGB service of IT-Recht Kanzlei.', 'legal-texts-connector-it-recht-kanzlei'); ?></p>
    <p><strong><a target="_blank" href="https://www.it-recht-kanzlei.de/schutzpakete.html?pid=5"><?php esc_html_e('If necessary, you can book this service here.', 'legal-texts-connector-it-recht-kanzlei'); ?></a></strong></p>
    <div class="itrk-divider"></div>
    <h3><?php esc_html_e('Login', 'legal-texts-connector-it-recht-kanzlei'); ?></h3>
    <?php } ?>
    <div id="itrk-login-input-container">
        <p><?php esc_html_e('Please enter the access data for your account at IT-Recht Kanzlei below.', 'legal-texts-connector-it-recht-kanzlei'); ?><br />
            <?php esc_html_e('The legal text interface is then automatically set up for you.', 'legal-texts-connector-it-recht-kanzlei'); ?><br />
            <?php esc_html_e('On the following page you will see the next steps.', 'legal-texts-connector-it-recht-kanzlei'); ?>
        </p>
        <input class="itrk-input" type="text" placeholder="E-Mail" name="itrk-email" />
        <input class="itrk-input" type="password" placeholder="<?php esc_html_e('Password', 'legal-texts-connector-it-recht-kanzlei'); ?>" name="itrk-password" />
    </div>
    <div id="itrk-login-error-message"></div>
    <div id="itrk-multi-imprint-container">
        <p class="itrk-orange-text">
            <?php esc_html_e('Several imprints/companies are assigned to your account. Select below for which of them this GTC interface should be set up.', 'legal-texts-connector-it-recht-kanzlei'); ?>
        </p>
        <div class="itrk-dropdown">
            <select name="itrk-sid" id="sid-select"></select>
        </div>
    </div>
    <input type="submit" name="itrk-login" id="itrk-login-button" class="itrk-button" value="<?php esc_html_e('Login now', 'legal-texts-connector-it-recht-kanzlei'); ?>">
    <input type="submit" name="itrk-save"  id="itrk-save-button"  class="itrk-button" value="<?php esc_html_e('Save settings', 'legal-texts-connector-it-recht-kanzlei'); ?>">
</div>
