<?php
declare(strict_types=1);

namespace Infrastructure\Security\Authentication;

use Infrastructure\SlimAdmin;
use Infrastructure\BaseEntity\BaseMVC\View\AdminView;
use Infrastructure\BaseEntity\BaseMVC\View\Forms\FormHelper;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Routing\RouteContext;

class AuthenticationView extends AdminView
{
    public function routeGetLogin(Request $request, Response $response, $args)
    {
        $authenticationService = $this->container->get('authentication');
        $csrfService = $this->container->get('csrf');
        if ($authenticationService->tooManyFailedLogins()) {
            return $response
                ->withHeader('Location', $this->container->get('routeParser')->urlFor(ROUTE_HOME))
                ->withStatus(302);
        }

        $usernameValue = (isset($args[SlimAdmin::USER_INPUT_KEY])) ? $args[SlimAdmin::USER_INPUT_KEY][$authenticationService->getUsernameFieldName()] : null;

        $form = $authenticationService->getForm($csrfService->getTokenNameKey(), $csrfService->getTokenName(), $csrfService->getTokenValueKey(), $csrfService->getTokenValue(), $this->container->get('routeParser')->urlFor(ROUTE_LOGIN_POST), $usernameValue);

        FormHelper::unsetSessionFormErrors();

        $renderStatus = (array_key_exists('status', $args)) ? $args['status'] : 200;

        // render page
        return $this->container->get('view')->render(
            $response,
            'Admin/login.php',
            [
                'title' => '::Login',
                'formHtml' => $form->generate(),
                'focusFieldId' => $form->getFocusFieldId(),
                'notice' => $this->getNotice(),
            ]
        )->withStatus($renderStatus);
    }
}
