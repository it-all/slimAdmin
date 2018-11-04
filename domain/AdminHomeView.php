<?php
declare(strict_types=1);

namespace Domain;

use Infrastructure\BaseEntity\BaseMVC\View\AdminView;

class AdminHomeView extends AdminView
{
    public function routeIndex($request, $response, $args)
    {
        $responseStatus = (array_key_exists('status', $args)) ? $args['status'] : 200;
        return $this->view->render(
            $response,
            'admin/home.php',
            [
                'title' => 'Admin',
                'navigationItems' => $this->navigationItems,
            ]
        )->withStatus($responseStatus);
    }
}
