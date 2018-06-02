<?php
declare(strict_types=1);

namespace Domain;

use SlimPostgres\AdminView;

class AdminHomeView extends AdminView
{
    public function index($request, $response, $args)
    {
        return $this->view->render(
            $response,
            'admin/home.php',
            [
                'title' => 'Admin',
                'navigationItems' => $this->navigationItems,
                'authentication' => $this->authentication,
                'businessDba' => $this->settings['businessDba'],
                'businessName' => $this->settings['businessName'],
                'isLive' => $this->settings['isLive'],
            ]
        );
    }
}
