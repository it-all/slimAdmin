<?php
declare(strict_types=1);

namespace Templates;

class TemplateContent 
{
    private $title;
    private $body;
    private $isPublic; // default false
    private $metaKeywords;
    private $metaDescription;
    private $metaRobots;
    private $headMeta;
    private $headCss;
    private $headJs;
    private $headLink; // ie <link ... >
    private $bodyJs;

    public function __construct(?string $title = null, ?string $body = "Hello World", ?string $bodyJs = null, ?bool $isPublic = false, ?string $metaKeywords = null, ?string $metaDescription = null, ?string $headMeta = null, ?string $headCss = null, ?string $headJs = null, ?string $headLink = null)
    {
        $this->title = $title ?? '';
        $this->body = $body;
        $this->isPublic = $isPublic;
        $this->metaKeywords = $this->isPublic && $metaKeywords != null ? '<meta name="keywords" content="' . $metaKeywords . '">' : '';
        $this->metaDescription = $this->isPublic && $metaDescription != null  ? '<meta name="description" content="' . $metaDescription . '">' : '';
        $this->metaRobots = !$this->isPublic ? '<meta name="robots" content="noindex, nofollow">' : '';
        $this->headMeta = $headMeta ?? '';
        $this->headCss = $headCss ?? '';
        $this->headJs = $headJs ?? '';
        $this->headLink = $headLink ?? '';
        $this->bodyJs = $bodyJs ?? '';
    }

    public function getTitle(): string 
    {
        return $this->title;
    }

    public function getBody(): string 
    {
        return $this->body;
    }

    public function getMetaKeywords(): string 
    {
        return $this->metaKeywords;
    }

    public function getMetaDescription(): string 
    {
        return $this->metaDescription;
    }

    public function getMetaRobots(): string 
    {
        return $this->metaRobots;
    }

    public function getHeadMeta(): string 
    {
        return $this->headMeta; 
    }

    public function getHeadCss(): string 
    {
        return $this->headCss;
    }

    public function getHeadJs(): string 
    {
        return $this->headJs;
    }

    public function getHeadLink(): string 
    {
        return $this->headLink;
    }

    public function getBodyJs(): string 
    {
        return $this->bodyJs;
    }
}