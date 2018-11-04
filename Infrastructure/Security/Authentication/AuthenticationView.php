<?php
declare(strict_types=1);

namespace Infrastructure\Security\Authentication;

use Infrastructure\SlimPostgres;
use Infrastructure\BaseEntity\BaseMVC\View\AdminView;
use Infrastructure\Forms\Form;
use Infrastructure\BaseEntity\BaseMVC\View\Forms\FormHelper;
use Slim\Http\Request;
use Slim\Http\Response;

class AuthenticationView extends AdminView
{
    public function routeGetLogin(Request $request, Response $response, $args)
    {
        if ($this->authentication->tooManyFailedLogins()) {
            return $response->withRedirect($this->router->pathFor(ROUTE_HOME));
        }

        $usernameValue = (isset($args[SlimPostgres::USER_INPUT_KEY])) ? $args[SlimPostgres::USER_INPUT_KEY][$this->authentication->getUsernameFieldName()] : null;

        $form = $this->authentication->getForm($this->csrf->getTokenNameKey(), $this->csrf->getTokenName(), $this->csrf->getTokenValueKey(), $this->csrf->getTokenValue(), $this->router->pathFor(ROUTE_LOGIN_POST), $usernameValue);

        FormHelper::unsetSessionFormErrors();

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
