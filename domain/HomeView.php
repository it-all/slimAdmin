<?php
declare(strict_types=1);

namespace Domain;

use SlimPostgres\UserInterface\Views\BaseView;
use Slim\Http\Request;
use Slim\Http\Response;

class HomeView extends BaseView
{
    public function index(Request $request, Response $response)
    {
        return $this->view->render(
            $response,
            'frontend/home.php',
            ['pageType' => 'public']
        );
    }
}
