<?php
if (!defined('ABSPATH')) exit;

return sprintf(
    '<div class="notice notice-%s%s"><p>%s</p></div>',
    $this->getSeverity(),
    $this->isDismissible() ? ' is-dismissible' : '',
    esc_html($this->getContent())
);
