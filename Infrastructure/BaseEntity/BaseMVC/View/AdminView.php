<?php
declare(strict_types=1);

namespace Infrastructure\BaseEntity\BaseMVC\View;

use Infrastructure\BaseEntity\BaseMVC\View\AdminNavigation;
use Psr\Container\ContainerInterface as Container;
use Infrastructure\SlimAdmin;

class AdminView extends BaseView
{
    protected $navigationItems;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        if ($this->container->get('authentication')->isAuthenticated()) {
            $this->navigationItems = (new AdminNavigation($container))->getNavForAdministrator();
            $this->events->setAdministratorId($this->authentication->getAdministratorId());
        }
    }

    public function getNotice(): ?array 
    {
        $notices = null;
        if (isset($_SESSION[SlimAdmin::SESSION_KEY_ADMIN_NOTICE])) {
            $notices = [];
            foreach ($_SESSION[SlimAdmin::SESSION_KEY_ADMIN_NOTICE] as $notice) {
                $notices[] = [
                    'class' => $notice[1],
                    'content' => $notice[0],
                ];    
            }

            unset($_SESSION[SlimAdmin::SESSION_KEY_ADMIN_NOTICE]);
        }

        return $notices;
    }
}
