<?php
namespace ITRechtKanzlei\LegalText\Plugin\Wordpress;

class Document
{
    private $title = null;
    private $country = null;
    private $language = null;
    private $content = null;
    private $type = null;
    private $fileName = null;
    private $creationDate = null;

    public function __construct($title, $country, $language, $content, $type, $fileName, $creationDate)
    {
        $this->title = $title;
        $this->country = $country;
        $this->language = $language;
        $this->content = $content;
        $this->type = $type;
        $this->fileName = $fileName;
        $this->creationDate = $creationDate;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function getCountryName(): string
    {
        return Plugin::getCountry($this->getCountry());
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function getLanguageName(): string
    {
        return Plugin::getLanguage($this->getLanguage());
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getDocumentName(): string
    {
        return Plugin::getDocumentName($this->getType());
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function getCreationDate(): \DateTime
    {
        return $this->creationDate;
    }

    public function getIdentifier(): string
    {
        return self::createIdentifier($this->type, $this->language, $this->country);
    }

    public static function createIdentifier($type, $language, $country): string
    {
        return sprintf('%s%s_%s_%s', Plugin::OPTION_DOC_PREFIX, $language, strtoupper($country), $type);
    }

    public static function getFilePath($language, $country, $type): string
    {
        $fileName = sprintf(
            'itrk_%s_%s_%s.pdf',
            $language,
            $country,
            $type
        );
        return trailingslashit(wp_upload_dir()['basedir']) . 'legal_texts/' . $fileName;
    }

    public function getFile(): string
    {
        return self::getFilePath($this->getLanguage(), $this->getCountry(), $this->getType());
    }

    public function getShortCode(): string
    {
        return ShortCodes::createShortCode($this->getType(), $this->getLanguage(), $this->getCountry());
    }

    public function __serialize(): array
    {
        return [
            'title' => $this->title,
            'country' => $this->country,
            'language' => $this->language,
            'content' => $this->content,
            'type' => $this->type,
            'fileName' => $this->fileName,
            'creationDate' => $this->creationDate,
        ];
    }

    public function __unserialize(array $data): void
    {
        foreach ($data as $key => $value) {
            // The previous version of this class didn't implement this method. In order to unserialize the old
            // versions of the data, the key has to be extracted.
            $key = str_contains($key, "\0") ? substr($key, strrpos($key, "\0") + 1) : $key;
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }
}


// Make sure the document can be read if the namespace has not been updated in the serialized format yet.
$oldDocNs = 'ITRechtKanzlei\\LegalTextsConnector\\Document';
if (!class_exists($oldDocNs, false)) {
    class_alias(Document::class, $oldDocNs);
}
