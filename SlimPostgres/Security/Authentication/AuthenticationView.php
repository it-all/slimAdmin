<?php
declare(strict_types=1);

namespace SlimPostgres\Security\Authentication;

use SlimPostgres\AdminView;
use SlimPostgres\Forms\Form;
use SlimPostgres\Forms\FormHelper;
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

        FormHelper::unsetSessionVars();

        // render page
        return $this->view->render(
            $response,
            'admin/login.php',
            [
                'businessName' => $this->settings['businessName'],
                'businessDba' => $this->settings['businessDba'],
                'isLive' => $this->settings['isLive'],
                'title' => '::Login',
                'form' => $form,
                'authentication' => $this->authentication
            ]
        );
    }
}
