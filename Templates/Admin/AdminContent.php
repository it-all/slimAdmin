<?php
declare(strict_types=1);

namespace Templates\Admin;

use Slim\Interfaces\RouteParserInterface;
use Templates\TemplateContent;

class AdminContent extends TemplateContent 
{
    protected $authentication;
    protected $notices;
    protected $isLive;
    protected $domainName;
    protected $businessName;
    protected $businessDba;
    protected $router;
    protected $routeParser;
    protected $debugString;

    public function __construct(string $mainHtml, ?string $headCss, ?string $bodyJs, ?array $notices, bool $isLive, string $title, string $domainName, string $businessName, string $businessDba, $authentication, ?array $navigationItems, RouteParserInterface $routeParser, ?string $debugString = null)
    {
        $this->authentication = $authentication;
        $this->notices = $notices;
        $this->isLive = $isLive;
        $this->domainName = $domainName;
        $this->businessName = $businessName;
        $this->businessDba = $businessDba;
        $this->routeParser = $routeParser;
        $this->debugString = $debugString;
        
        $body = $this->getBodyString($mainHtml, $navigationItems, $routeParser);
        $isPublic = false;
        
        $faviconPath = ICONS_PATH . 'favicon.ico';
        $headLink = (file_exists($faviconPath)) ? '<link rel="shortcut icon" href="'.ICONS_DIR_PATH.'/favicon.ico">' : null;
        parent::__construct($title, $body, $bodyJs, $isPublic, null, null, null, $headCss, null, $headLink);
    }

    private function getBodyString(string $mainHtml, ?array $navigationItems, RouteParserInterface $routeParser): string 
    {
        $body = $this->getHeader($navigationItems, $routeParser);
        $body .= '<main>' . $mainHtml . '</main>';
        $body .= $this->getFooter();
        return $body;
    }

    private function getNav(?array $navigationItems, RouteParserInterface $routeParser): string
    {
        $navContent = new NavContent($navigationItems, $routeParser, $this->domainName);
        $navMenu = $navContent->getNavMenu();
        $nav = <<< EOT
    <nav id="adminNav">
        <input type="checkbox" id="toggle-nav">
        <label id="toggle-nav-label" for="toggle-nav">
            <img src="/assets/images/vegan-burger.png" width="20" height="20">
        </label>
    
        <div id="navOverlay">
            $navMenu
        </div>
    </nav>
EOT;

        return $nav;
    }

    private function getHeader(?array $navigationItems, RouteParserInterface $routeParser) 
    {
        $header = '<header id="adminPageHeader">'; 

        if ($this->authentication->isAuthenticated()) {
            $header .= $this->getNav($navigationItems, $routeParser);
        }
        
        $header .= '<div id="adminPageHeaderLogo">';
        $header .= '<a href="/">'.$this->businessDba .'</a>';
        if (!$this->isLive) {
            $header .= '<span style="color: grey;">{TESTING}</span>';
        }
        $header .= '</div>';
        
        if ($this->authentication->isAuthenticated()) {
            $header .= '<div id="adminPageHeaderNotice">';
            if ($this->notices != null) {
                foreach ($this->notices as $notice) {
                    $header .= '<div class="'.$notice['class'].'">&raquo; '.$notice['content'].' &laquo;</div>';
                }
            }
            $header .= '</div>';
            $header .= '<div id="adminPageHeaderGreeting">
                Hello '.$this->authentication->getAdministratorFirstName().'
                    [<a href="'.$routeParser->urlFor(ROUTE_LOGOUT).'">logout</a>]
                </div>';
        }
        
        $header .= '</header>';
        
        if ($this->debugString != null && mb_strlen($this->debugString) > 0 && !$this->isLive) {
            $header .= '<div id="debug">$debug</div>';
        }

        return $header;
    }

    private function getFooter(): string
    {
        $footer = '<footer id="adminPageFooter">'.$this->businessName;
        if (!$this->isLive) {
            $footer .= '<span style="color: grey;">{TESTING}</span>';
        }
        $footer .= '</footer>';

        return $footer;
    }
}
