<?php
namespace ITRechtKanzlei\LegalTextsConnector;

require_once __DIR__ . '/Plugin.php';
require_once __DIR__ . '/Document.php';

class ShortCodes {
    private $registeredShortCodes = [];

    public function __construct() {
        add_action('init', [$this, 'setup']);
    }

    public static function settings() {
        return (array)apply_filters('agb_shortcodes', [
            'agb_imprint' => [
                'name' => Plugin::getDocumentName('impressum'),
                'setting_key' => 'impressum',
            ],
            'agb_terms' => [
                'name' => Plugin::getDocumentName('agb'),
                'setting_key' => 'agb',
            ],
            'agb_privacy' => [
                'name' => Plugin::getDocumentName('datenschutz'),
                'setting_key' => 'datenschutz',
            ],
            'agb_revocation' => [
                'name' => Plugin::getDocumentName('widerruf'),
                'setting_key' => 'widerruf',
            ],
        ]);
    }

    public function setup() {
        foreach (self::settings() as $shortCode => $setting) {
            if (!$setting) {
                return;
            }

            $this->registeredShortCodes[$shortCode] = $setting;

            remove_shortcode($shortCode);
            add_shortcode($shortCode, [$this, 'doShortCodeCallback']);
        }
    }

    public function doShortCodeCallback($attr, $content, $shortCode) {
        if (!isset($this->registeredShortCodes[$shortCode])) {
            return '';
        }
        $setting = $this->registeredShortCodes[$shortCode];

        $attr = shortcode_atts([
            'id' => '',
            'class' => '',
            'country' => '',
            'language' => '',
        ], $attr, $shortCode);

        if ((empty($attr['language']) || empty($attr['country']))
            && ($locale = get_bloginfo('language'))
            && preg_match('/^([a-z]{2,3})(-([A-Z]{2}))?$/', $locale, $match)
        ) {
            if (empty($attr['language'])) {
                $attr['language'] = $match[1];
            }
            if (empty($attr['country'])) {
                if (isset($match[3])) {
                    $attr['country'] = $match[3];
                } else {
                    $attr['country'] = strtoupper($attr['language']);
                }
            }
        }

        $document = get_option(Document::createIdentifier($setting['setting_key'], $attr['language'], $attr['country']), null);

        if (!$document) {
            $documentContent = esc_html(__(
                'Please use the copy function to the right of the shortcodes in the plugin and paste it here. Make sure you select the correct language.',
                'legal-texts-connector-it-recht-kanzlei'
            ));
        } else {
            $array = [
                '<p>['       => '[',
                ']</p>'      => ']',
                '<br /></p>' => '</p>',
                ']<br />'    => ']',
            ];
            $documentContent = strtr($document->getContent(), $array);
        }

        if (empty($documentContent)) {
            // translators: %s will be replaced with the short code name.
            $documentContent = esc_html(sprintf(__('No content found for %s. Please transfer the legal texts again.', 'legal-texts-connector-it-recht-kanzlei'), $setting['name']));
        }

        $id = !empty($attr['id']) ? 'id="' . $attr['id'] . '"' : '';
        $classes = implode(' ', array_map('sanitize_html_class', array_unique(array_merge(
            ['agb_content', $shortCode],
            preg_split('#\s+#', $attr['class'])
        ))));
        // Prevent google tranlsate from translating the legal texts.
        $classes .= ' notranslate';

        return sprintf(
            '<div %1$s class="%2$s">%3$s</div>',
            esc_attr($id),
            esc_attr($classes),
            $documentContent
        );
    }

    public static function createShortCode($type, $language, $country) {
        static $map = [];
        if (empty($map)) {
            foreach (self::settings() as $tag => $v) {
                $map[$v['setting_key']] = $tag;
            }
        }
        $tag = isset($map[$type]) ? $map[$type] : 'itrk_missing_key';
        return sprintf('[%s language="%s" country="%s"]', $tag, $language, $country);
    }

    public static function getPageIdForShortCode($shortcode) {
        global $wpdb;
        $query = $wpdb->prepare("
            SELECT ID
              FROM `{$wpdb->posts}`
             WHERE post_type = 'page'
                   AND post_status = 'publish'
                   AND post_content LIKE %s
          ORDER BY ID DESC
            ",
            '%'.$wpdb->esc_like($shortcode).'%'
        );
        return (int)$wpdb->get_var($query);
    }

    public static function isShortcodeUsed($shortcode) {
        return self::getPageIdForShortCode($shortcode) > 0;
    }

    public static function getPageLinkShortCode($shortcode) {
        return the_permalink(self::getPageIdForShortCode($shortcode));
    }
}
