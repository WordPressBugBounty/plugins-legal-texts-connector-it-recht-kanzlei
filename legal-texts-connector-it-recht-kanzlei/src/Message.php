<?php
namespace ITRechtKanzlei\LegalTextsConnector;

class Message {
    const SEVERITY_INFO = 'info';
    const SEVERITY_SUCCESS = 'success';
    const SEVERITY_WARNING = 'warning';
    const SEVERITY_ERROR = 'error';

    const SEVERITIES = [
        self::SEVERITY_INFO,
        self::SEVERITY_SUCCESS,
        self::SEVERITY_WARNING,
        self::SEVERITY_ERROR
    ];

    private $severity = 'info';
    private $content  = '';
    private $dismissible = false;


    public function __construct($severity, $content, $dismissible = false) {
        $this->severity = in_array($severity, self::SEVERITIES, true) ? $severity : self::SEVERITY_INFO;
        $this->content = $content;
        $this->dismissible = $dismissible;
    }

    public function getSeverity() {
        return $this->severity;
    }

    public function getContent() {
        return $this->content;
    }

    public function isDismissible() {
        return $this->dismissible;
    }

    public function toHtml() {
        return require(__DIR__ . '/views/message.php');
    }
}
