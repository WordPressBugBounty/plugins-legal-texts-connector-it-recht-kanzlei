<?php
use ITRechtKanzlei\LegalTextsConnector\Plugin;
?>
<script>
jQuery(document).ready(function () {
    jQuery('#itrk-login-dialog input[type="submit"]').on('click', function () {
        let errors = <?php echo json_encode([
                'UNKNOWN'    => __('An unknown error occured.', 'legal-texts-connector-it-recht-kanzlei'),
                'CONNECTION' => __('A connection to the server of IT-Recht Kanzlei could not be established. Error Details:', 'legal-texts-connector-it-recht-kanzlei'),
                'INVALID_PARAMETERS'  => __('Your provided credentials are incomplete.', 'legal-texts-connector-it-recht-kanzlei'),
                'INVALID_CREDENTIALS' => __('Your provided credentials are invalid.', 'legal-texts-connector-it-recht-kanzlei'),
                'MISSING_IMPRINTS'    => __('You do not have any imprints configured. Please log into the Client Portal of IT-Recht Kanzlei.', 'legal-texts-connector-it-recht-kanzlei'),
                'IMPRINT_INACTIVE'    => __('The selected imprint is not active anymore. Please reload the page and repeat the process.', 'legal-texts-connector-it-recht-kanzlei'),
            ]); ?>,
            btn = jQuery(this);

        btn.attr('disabled', true);

        jQuery.post(<?php echo json_encode(admin_url('admin-ajax.php')); ?>, {
            'action':  <?php echo json_encode(Plugin::PLUGIN_NAME.'-login'); ?>,
            'nonce':  <?php echo json_encode(wp_create_nonce(Plugin::PLUGIN_NAME.'-action-login')); ?>,
            'itrk-email': jQuery('input[name="itrk-email"]').val(),
            'itrk-password': jQuery('input[name="itrk-password"]').val(),
            'itrk-sid': jQuery('select[name=itrk-sid]').val()
        })
            .done(function(response, textStatus, jqXHR) {
                let errorMessage = errors.UNKNOWN;
                if (typeof response !== 'object') {
                    response = {};
                }
                response = {
                    'status': 'error',
                    'status-code': -1,
                    'error-code': 'UNKNOWN',
                    ...response,
                };

                if (response['error-code'] === 'NONCE_EXPIRED') {
                    window.location.reload();
                    return;
                }

                btn.attr('disabled', false);

                if (typeof errors[response['error-code']] !== 'undefined') {
                    errorMessage = errors[response['error-code']];
                }

                if (response.status !== 'success') {
                    if (typeof response['error-details'] === 'string') {
                        errorMessage += '<br>' + response['error-details'];
                    }
                    jQuery('#itrk-login-error-message').html(errorMessage);
                    jQuery('#itrk-login-error-message').css('display', 'block');

                    return;
                }

                if (response['status-code'] === 409) {
                    jQuery('#itrk-login-error-message').css('display', 'none');
                    jQuery('#itrk-multi-imprint-container, input[type=submit]#multidocument-button').css('display', 'block');
                    jQuery('input[name=itrk-password], input[name=itrk-email]').attr('readonly', true)
                    jQuery('input[type=submit]#login-button, #itrk-login-input-container').css('display', 'none');

                    jQuery.each(response.configs, (key, value) => jQuery('#sid-select').append(new Option(value, key)));
                    return;
                }

                if (response['status-code'] === 200) {
                    let param = 'legal-texts-connector-reset',
                        url = window.location.href.split('?')[0]+'?',
                        sPageURL = decodeURIComponent(window.location.search.substring(1)),
                        sURLVariables = sPageURL.split('&'),
                        sParameterName,
                        i;

                    for (i = 0; i < sURLVariables.length; i++) {
                        sParameterName = sURLVariables[i].split('=');
                        if (sParameterName[0] != param) {
                            url = url + sParameterName[0] + '=' + sParameterName[1] + '&'
                        }
                    }
                    window.location = url.substring(0, url.length - 1);
                    return;
                }

                jQuery('#itrk-login-error-message').html(errorMessage);
                jQuery('#itrk-login-error-message').css('display', 'block');
                console.log(jqXHR);
            })
            .fail(function (jqXHR) {
                jQuery('#itrk-login-error-message').html(errors.UNKNOWN);
                jQuery('#itrk-login-error-message').css('display', 'block');
                btn.attr('disabled', false);
                console.log(jqXHR);
            });
    });
});
</script>

<div id="itrk-login-dialog" class="itrk-card">
    <div id="itrk-kanzlei-logo"> </div>
    <div class="itrk-divider"></div>
    <h4><?php esc_html_e('In a few steps to legal texts on this Wordpress installation that are safe from warning letters', 'legal-texts-connector-it-recht-kanzlei'); ?></h4>
    <p><strong><?php esc_html_e('Note: Prerequisite for the use of plugins is the booking of the AGB service of IT-Recht Kanzlei.', 'legal-texts-connector-it-recht-kanzlei'); ?> <a target="_blank" href="https://www.it-recht-kanzlei.de/schutzpakete.html?pid=5"><?php esc_html_e('If necessary, you can book this service here.', 'legal-texts-connector-it-recht-kanzlei'); ?></a></strong></p>
    <div class="itrk-divider"></div>
    <h4><?php esc_html_e('Login', 'legal-texts-connector-it-recht-kanzlei'); ?></h4>
    <div id="itrk-login-input-container">
        <p><?php esc_html_e('Please enter the access data for your account at IT-Recht Kanzlei below.', 'legal-texts-connector-it-recht-kanzlei'); ?> <br /> <?php esc_html_e('Part 1 of the AGB interface is thus automatically set up for you.', 'legal-texts-connector-it-recht-kanzlei'); ?> <br /> <?php esc_html_e('On the following page you will see the next steps.', 'legal-texts-connector-it-recht-kanzlei'); ?></p>
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
