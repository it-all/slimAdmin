<?php
declare(strict_types=1);

namespace It_All\Slim_Postgres\Domain;

use It_All\Slim_Postgres\Infrastructure\Framework\View;
use Slim\Http\Request;
use Slim\Http\Response;

class HomeView extends View
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
