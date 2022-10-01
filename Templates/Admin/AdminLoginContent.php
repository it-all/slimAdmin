<?php
declare(strict_types=1);

namespace Templates\Admin;

use Slim\Interfaces\RouteParserInterface;

class AdminLoginContent extends AdminContent 
{
    public function __construct(string $formHtml, string $focusFieldId, ?array $notice, bool $isLive, string $title, string $domainName, string $businessName, string $businessDba, $authentication, ?array $navigationItems, RouteParserInterface $routeParser, ?string $debugString = null)
    {
        $mainHtml = $this->getMainHtml($formHtml, $businessDba);
        $headCss = '<link href="'.CSS_DIR_PATH.'/adminFlexSimpleNotLoggedIn.css" rel="stylesheet" type="text/css">';
        parent::__construct($mainHtml, $headCss, $this->getBodyJsString($focusFieldId), $notice, $isLive, $title, $domainName, $businessName, $businessDba, $authentication, $navigationItems, $routeParser, $debugString);
    }

    private function getMainHtml(string $formHtml, string $businessDba): string
    {
        $mainHtml = <<< EOT
        <div id="simpleContainer">
            <h1>Login</h1>
            <h5>Authorized for use by $businessDba employees and associates only.</h5>
            $formHtml
        </div>
EOT;
        return $mainHtml;
    }

    private function getBodyJsString(string $focusFieldId): string
    {
        $bodyJs = '<script type="text/javascript" src="'.JS_DIR_PATH.'/uiHelper.js"></script>';
        if (mb_strlen($focusFieldId) > 0) {
            $bodyJs .= <<< EOT
            <script type="text/javascript">
                window.onload = document.getElementById('$focusFieldId').focus();
            </script>    
EOT;
        }

        return $bodyJs;
    }
}
