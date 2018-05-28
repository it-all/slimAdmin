<?php
declare(strict_types=1);

namespace Domain;

use Slim\Http\Request;
use Slim\Http\Response;

class HomeView extends \SlimPostgres\View
{
    public function index(Request $request, Response $response)
    {
        return $this->view->render(
            $response,
            'home.php',
            ['businessName' => $this->settings['businessName'], 'pageType' => 'public']
        );
    }
}
