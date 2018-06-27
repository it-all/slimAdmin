<?php
declare(strict_types=1);

namespace SlimPostgres\Security\Authentication;

use SlimPostgres\UserInterface\AdminView;
use SlimPostgres\Forms\Form;
use SlimPostgres\UserInterface\Forms\FormHelper;
use Slim\Http\Request;
use Slim\Http\Response;

class AuthenticationView extends AdminView
{
    public function getLogin(Request $request, Response $response, $args)
    {
        if ($this->authentication->tooManyFailedLogins()) {
            return $response->withRedirect($this->router->pathFor(ROUTE_HOME));
        }

        $form = $this->authentication->getForm($this->csrf->getTokenNameKey(), $this->csrf->getTokenName(), $this->csrf->getTokenValueKey(), $this->csrf->getTokenValue(), $this->router->pathFor(ROUTE_LOGIN_POST));

        FormHelper::unsetFormSessionVars();

        $renderStatus = (array_key_exists('status', $args)) ? $args['status'] : 200;

        // render page
        return $this->view->render(
            $response,
            'admin/login.php',
            [
                'title' => '::Login',
                'form' => $form,
            ]
        )->withStatus($renderStatus);
    }
}
