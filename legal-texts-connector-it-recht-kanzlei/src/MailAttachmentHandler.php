<?php
namespace ITRechtKanzlei\LegalTextsConnector;

require_once __DIR__ . '/sdk/LTI.php';
require_once __DIR__ . '/Helper.php';
require_once __DIR__ . '/ItrkLtiHandler.php';
require_once __DIR__ . '/ShortCodes.php';

class MailAttachmentHandler {
    const META_KEY_ORDER_LOCALE = 'itrk_wc_order_locale';

    public function __construct() {
        // Disable automatic mail attachments if it is an absolute necessity.
        if (defined('ITRK_DISABLE_MAIL_ATTACHMENTS') && (ITRK_DISABLE_MAIL_ATTACHMENTS === true)) {
            return;
        }

        add_action('woocommerce_checkout_update_order_meta', function($order_id, $data) {
            $order  = wc_get_order($order_id);
            if (!$order instanceof \WC_Order) {
                return;
            }
            $order->update_meta_data(self::META_KEY_ORDER_LOCALE, get_bloginfo('language'));
            $order->save_meta_data();
        }, 10, 2);

        add_filter('woocommerce_mail_callback_params', function ($params, $wcMail) {
            return $this->attachPdfToWcEmail($params, $wcMail);
        }, 99999, 2);

        add_action('phpmailer_init', function ($phpmailer) {
            $this->updateAttachmentNames($phpmailer);
        });
    }

    private function documentAvailableInLanguage($documents, string $language, ?string $country = null): ?Document {
        foreach ($documents as $doc) {
            if (!($doc instanceof Document)) {
                continue;
            }
            if (($doc->getLanguage() == $language) && (empty($country) || ($doc->getCountry() == $country))) {
                return $doc;
            }
        }
        return null;
    }

    private function getEmailAttachmentDocument(string $type, string $locale): ?string {
        $documents = [];
        foreach (Plugin::getAvailableDocuments($type) as $serDoc) {
            try {
                $documents[] = Helper::unserializeWithException($serDoc, ['Document' => false]);
            } catch (\RuntimeException $e) {}
        }

        list($language, $country) = explode('-', $locale, 2);
        list($wpLanguage, $wpCountry) = explode('-', get_bloginfo('language'), 2);

        if (($document = $this->documentAvailableInLanguage($documents, $language, $country))
            || ($document = $this->documentAvailableInLanguage($documents, 'en'))
            || ($document = $this->documentAvailableInLanguage($documents, $wpLanguage, $wpCountry))
        ) {
            return Document::getFilePath($document->getLanguage(), $document->getCountry(), $document->getType());
        } elseif (count($documents) != 0) {
            $document = reset($documents);
            return Document::getFilePath($document->getLanguage(), $document->getCountry(), $document->getType());
        }

        return null;
    }

    /**
     * Attach the legal texts to the order mail the customer receives
     */
    private function attachPdfToWcEmail($params, $wcMail) {
        // $params is an array containing 4 elements. 0: receiver, 1: subject, 2: content, 3: headers, 4: attachments.
        if (!is_array($params) || !isset($params[4])
            || !($wcMail instanceof \WC_Email) || !($wcMail->object instanceof \WC_Order)
            || !in_array($wcMail->id, [
                    'customer_processing_order',
                    'customer_invoice',
                ], true)
        ) {
            return $params;
        }

        // Support for woocommerce-germanized-pro
        $attachTC = true;
        $attachRefund = true;
        if (class_exists(\WooCommerce_Germanized_Pro::class)) {
            $attachTC = get_option('woocommerce_gzdp_legal_page_terms_enabled') !== 'yes';
            $attachRefund = get_option('woocommerce_gzdp_legal_page_revocation_enabled') !== 'yes';
        }

        $locale = $wcMail->object->get_meta(self::META_KEY_ORDER_LOCALE);
        $termsAndConditions = $attachTC ? $this->getEmailAttachmentDocument('agb', $locale) : null;
        $privacyPolicy      = $attachRefund ? $this->getEmailAttachmentDocument('widerruf', $locale) : null;

        if ($termsAndConditions && file_exists($termsAndConditions)) {
            $params[4][] = $termsAndConditions;
        }

        if ($privacyPolicy && file_exists($privacyPolicy)) {
            $params[4][] = $privacyPolicy;
        }
        return $params;
    }

    /**
     * Since the filename of the attachment can not be specified in the "woocommerce_email_attachments" hook,
     * the filename will be updated right before the mail gets sent using a small hack.
     */
    private function updateAttachmentNames($phpmailer) {
        $attachments = $phpmailer->getAttachments();

        $requiresUpdate = false;

        foreach ($attachments as $i => $attachment) {
            if (preg_match('/^itrk_([a-z]{2})_([A-Z]{2})_([^\.]+)/', $attachment[1], $infos)) {
                $document = get_option(Document::createIdentifier($infos[3], $infos[1], $infos[2]));
                $attachment[1] = $attachment[2] = $attachment[7] = $document->getFileName().'.pdf';
                $attachments[$i] = $attachment;
                $requiresUpdate = true;
            }
        }

        if (!$requiresUpdate) {
            return;
        }

        try {
            $rfc = new \ReflectionClass($phpmailer);
            $propAttachment = $rfc->getProperty('attachment');
            $propAttachment->setAccessible(true);
            $propAttachment->setValue($phpmailer, $attachments);
        } catch (\Exception $e) {}
    }

}
