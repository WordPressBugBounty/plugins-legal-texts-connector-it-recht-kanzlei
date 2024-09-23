<?php
if ($this->messages) {
    foreach ($this->messages as $message) {
        echo $message->toHtml();
    }
}
