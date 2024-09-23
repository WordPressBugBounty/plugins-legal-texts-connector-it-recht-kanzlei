<?php
use ITRechtKanzlei\LegalTextsConnector\SettingsPage;
?>
<div class="notice notice-warning">
    <p><?php esc_html_e('Do you want to uninstall the old AGB Connector plugin right away?', 'legal-texts-connector-it-recht-kanzlei'); ?></p>
    <form method="post" action="<?php echo esc_url(add_query_arg(['page' => SettingsPage::PAGE_SETTINGS], admin_url('options-general.php'))); ?>">
        <?php echo submit_button(esc_html(__('Uninstall', 'legal-texts-connector-it-recht-kanzlei')), 'primary', 'uninstall', false) ?>
        <?php echo submit_button(esc_html(__('Keep', 'legal-texts-connector-it-recht-kanzlei')), 'secondary', 'keep', false) ?>
    </form>
</div>
