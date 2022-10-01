<?php
declare(strict_types=1);

namespace Infrastructure\BaseEntity\BaseMVC\View;

use Psr\Container\ContainerInterface as Container;
use Infrastructure\SlimAdmin;

class FrontendView extends BaseView
{
    protected $navigationItems;

    public function __construct(Container $container)
    {
        parent::__construct($container);
    }

    public function getNotice(): ?array 
    {
        $notice = null;
        if ((isset($_SESSION[SlimAdmin::SESSION_KEY_NOTICE]))) {
            $notice = [
                'class' => $_SESSION[SlimAdmin::SESSION_KEY_NOTICE][1],
                'content' => $_SESSION[SlimAdmin::SESSION_KEY_NOTICE][0],
            ];
            unset($_SESSION[SlimAdmin::SESSION_KEY_NOTICE]);
        } 

        return $notice;
    }
}
