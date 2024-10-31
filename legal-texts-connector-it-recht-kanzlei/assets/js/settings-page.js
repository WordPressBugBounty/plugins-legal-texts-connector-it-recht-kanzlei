jQuery(function ($) {
    $('.dashicons-admin-page').click(function () {
        navigator.clipboard.writeText($(this).parent().find('code').text());
    });
});
