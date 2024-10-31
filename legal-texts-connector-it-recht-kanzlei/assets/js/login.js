jQuery(document).ready(function () {
    jQuery('#itrk-login-dialog input[type="submit"]').on('click', function () {
        let btn = jQuery(this),
            formHref   = jQuery('#itrk-login-dialog').data('href'),
            formAction = jQuery('#itrk-login-dialog').data('action'),
            formNonce  = jQuery('#itrk-login-dialog').data('nonce');

        btn.attr('disabled', true);

        jQuery.post(formHref, {
            'action': formAction,
            'nonce': formNonce,
            'itrk-email': jQuery('input[name="itrk-email"]').val(),
            'itrk-password': jQuery('input[name="itrk-password"]').val(),
            'itrk-sid': jQuery('select[name=itrk-sid]').val()
        })
            .done(function(response, textStatus, jqXHR) {
                let errorMessage = ITRK_LOGIN_MESSAGES.UNKNOWN;
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

                if (typeof ITRK_LOGIN_MESSAGES[response['error-code']] !== 'undefined') {
                    errorMessage = ITRK_LOGIN_MESSAGES[response['error-code']];
                }

                if (response.status !== 'success') {
                    if (typeof response['error-details'] === 'string') {
                        errorMessage += '<br>' + response['error-details'];
                    }
                    jQuery('#itrk-login-error-message').html(errorMessage).css('display', 'block');

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

                jQuery('#itrk-login-error-message').html(errorMessage).css('display', 'block');
                console.log(jqXHR);
            })
            .fail(function (jqXHR) {
                jQuery('#itrk-login-error-message').html(ITRK_LOGIN_MESSAGES.UNKNOWN).css('display', 'block');
                btn.attr('disabled', false);
                console.log(jqXHR);
            });
    });
});
