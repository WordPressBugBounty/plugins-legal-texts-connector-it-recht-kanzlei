<?php
use ITRechtKanzlei\LegalTextsConnector\Plugin;

if (!defined('ABSPATH')) exit;
?>
<?php if (in_array(ini_get('display_errors'), ['On', 'on', '1', 1])) { ?>
	<div class="notice notice-error is-dismissible"><p>
		<?php echo str_replace(
			'display_errors',
			'<a href="'.Plugin::BACKEND_URL.'support/php-display-errors/wordpress" target="_blank">display_errors</a>',
			esc_html(__(
				'The PHP setting "display_errors" is active. This may cause errors or warnings triggered by other plugins to be displayed and thus interfere with the transmission of the legal texts. Please set the "display_errors" setting to off or ask your service provider to do so.',
				'legal-texts-connector-it-recht-kanzlei'
			))
		); ?>
	</p></div>
<?php } ?>
<?php if (defined('ITRK_DISABLE_MAIL_ATTACHMENTS') && (ITRK_DISABLE_MAIL_ATTACHMENTS === true)) { ?>
	<div class="notice notice-error is-dismissible"><p>
		<?php echo esc_html(__(
			'You have disabled the automatic email attachments (T&C and cancellation policy) for the order confirmation email to buyers. Please make sure that buyers are made aware of their rights elsewhere in the order confirmation, otherwise you are at risk of legal notices.',
			'legal-texts-connector-it-recht-kanzlei'
		));?>
	</p></div>
<?php } ?>
<?php
$wgpStaticTerms = (int)get_option('woocommerce_gzdp_legal_page_terms_pdf') > 0;
$wgpStaticRevocation = (int)get_option('woocommerce_gzdp_legal_page_revocation_pdf') > 0;
if (class_exists(\WooCommerce_Germanized_Pro::class) && ($wgpStaticTerms || $wgpStaticRevocation)) {
	$legalTextPdfs = [];
		if ($wgpStaticTerms) {
		$legalTextPdfs[] = esc_html(Plugin::getDocumentName('agb'));
	}
	if ($wgpStaticRevocation) {
		$legalTextPdfs[] = esc_html(Plugin::getDocumentName('widerruf'));
	}
?>
	<div class="notice notice-warning is-dismissible"><p>
		<?php echo strtr(
			// translators: Keep the [link][/link] tag intact. %1$s is a list of legal texts.
			esc_html(__(
				'You have stored manually generated PDF files for some legal texts (%1$s) in the [link]Germanized > Emails > PDF Attachments[/link] settings. Please remember to check these regularly to ensure they are up to date.',
				'legal-texts-connector-it-recht-kanzlei'
			)),
			[
				'[link]' => sprintf('<a href="%s" target="_blank">', esc_url(add_query_arg(['page' => 'wc-settings', 'tab' => 'germanized-emails', 'section' => 'attachments'], admin_url('admin.php')))),
				'[/link]' => '</a>',
				'%1$s' => implode(', ', $legalTextPdfs),
			]
		);?>
	</p></div>
<?php } ?>

<div class="itrk-card" id="itrk-info-box">
	<div class="itrk-logo-block">
		<a href="https://www.it-recht-kanzlei.de" id="itrk-kanzlei-logo" title="IT-Recht Kanzlei München"></a>
	</div>
	<div class="itrk-instructions-block">
		<p>
			<?php esc_html_e('The \'Assign documents\' button takes you to the interface overview for this presence in the client portal.', 'legal-texts-connector-it-recht-kanzlei') ?><br>
			<?php esc_html_e('There you can use the sliders to assign the documents you want/need here.', 'legal-texts-connector-it-recht-kanzlei') ?><br>
			<?php esc_html_e('These are then transmitted to the plugin.', 'legal-texts-connector-it-recht-kanzlei') ?>
		</p>
	</div>
	<div class="itrk-buttons-block">
		<form method="post" target="_blank" action="<?php echo esc_url(sprintf('%s%s', Plugin::BACKEND_URL, 'shop-apps-api/logon.php')); ?>">
			<a target="_blank" href="https://www.it-recht-kanzlei.de/wordpress-schritt-fuer-schritt-anleitung.html" class="itrk-button invert"><?php esc_html_e('Step by step guide', 'legal-texts-connector-it-recht-kanzlei') ?></a>
			<input type="submit" name="redirect" class="itrk-button" value="<?php echo esc_html_e('Assign documents', 'legal-texts-connector-it-recht-kanzlei') ?>">
			<input type="hidden" name="sessionName" value="<?php echo esc_attr($session['itrk_session_name']); ?>">
			<input type="hidden" name="sessionId" value="<?php echo esc_attr($session['itrk_session_id']); ?>">
			<input type="hidden" name="sid" value="<?php echo esc_attr(get_option(Plugin::OPTION_SID)); ?>">
			<input type="hidden" name="targetPage" value="<?php echo esc_attr(sprintf(Plugin::TARGET_PAGE, get_option(Plugin::OPTION_INTERFACE_ID))); ?>">
		</form>
	</div>
</div>
