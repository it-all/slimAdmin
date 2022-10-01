<?php
declare(strict_types=1);

namespace Domain;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Infrastructure\BaseEntity\BaseMVC\View\FrontendView;

class HomeView extends FrontendView
{
    public function routeIndex(Request $request, Response $response)
    {
        return $this->view->render(
            $response,
            'Frontend/home.php',
            ['notice' => $this->getNotice()]
        );
    }

    public function routeGc(Request $request, Response $response)
    {
        return $this->view->render(
            $response,
            'Frontend/home.php'
        );
    }
    
}
