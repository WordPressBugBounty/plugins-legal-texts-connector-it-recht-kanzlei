<?php
if (!defined('ABSPATH')) exit;

if ($this->messages) {
    foreach ($this->messages as $message) {
        echo $message->toHtml(); // @Review Team: Uses message.php, which escapes the content.
    }
}
