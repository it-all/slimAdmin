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
            'Admin/adminBase.php',
            [
                'mainHtml' => 'Welcome to the default home page of the admin.',
                'navigationItems' => $this->navigationItems,
                'notice' => $this->getNotice(),
            ]
        )->withStatus($responseStatus);
    }
}
