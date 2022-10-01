<?php
declare(strict_types=1);

namespace Templates\Frontend;

use Templates\TemplateContent;

class HomeContent extends TemplateContent 
{
    public function __construct(string $businessName, ?array $notice = null)
    {
        $title = $businessName;
        $body = $this->getBodyString($businessName, $notice);
        $isPublic = true;
        $headCss = '<link href="'.CSS_DIR_PATH.'/frontend.css" rel="stylesheet" type="text/css">';
        parent::__construct($title, $body, null, $isPublic, null, null, null, $headCss);
    }

    private function getNoticeDiv(?array $notice = null): string 
    {
        return $notice == null ? '' : '<div class="'.$notice['class'].'">'.$notice['content'].'</div>';
    }

    private function getBodyString(string $businessName, ?array $notice = null): string 
    {
        $noticeDiv = $this->getNoticeDiv($notice);
        
        return <<< EOL
    <main>
        $noticeDiv
        <h1>Homepage of $businessName</h1>
    </main>
EOL;
    }
}
