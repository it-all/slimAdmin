<?php
declare(strict_types=1);

namespace Domain;

use Infrastructure\BaseEntity\BaseMVC\View\BaseView;
use Slim\Http\Request;
use Slim\Http\Response;

class HomeView extends BaseView
{
    public function routeIndex($request, Response $response)
    {
        return $this->view->render(
            $response,
            'Frontend/home.php',
            ['pageType' => 'public']
        );
    }
}
