<?php
declare(strict_types=1);

namespace SlimPostgres\Security\Authentication;

use SlimPostgres\App;
use SlimPostgres\Controller;
use SlimPostgres\Forms\FormHelper;
use Slim\Http\Request;
use Slim\Http\Response;

class AuthenticationController extends Controller
{
    function postLogin(Request $request, Response $response, $args)
    {
        $this->setRequestInput($request);
        $username = $_SESSION[App::SESSION_KEYS['requestInput']]['username'];
        $password = $_SESSION[App::SESSION_KEYS['requestInput']]['password_hash'];

        $this->validator = $this->validator->withData($_SESSION[App::SESSION_KEYS['requestInput']], $this->authentication->getLoginFields());

        $this->validator->rules($this->authentication->getLoginFieldValidationRules());

        if (!$this->validator->validate()) {
            // redisplay the form with input values and error(s)
            FormHelper::setFieldErrors($this->validator->getFirstErrors());
            $av = new AuthenticationView($this->container);
            return $av->getLogin($request, $response, $args);
        }

        if (!$this->authentication->attemptLogin($username, $password)) {
            $this->systemEvents->insertWarning('Unsuccessful login', null, 'Username: '.$username);

            if ($this->authentication->tooManyFailedLogins()) {
                $eventTitle = 'Maximum unsuccessful login attempts exceeded';
                $eventNotes = 'Failed:'.$this->authentication->getNumFailedLogins();
                $this->systemEvents->insertAlert($eventTitle, null, $eventNotes);
                throw new \Exception($eventTitle . ' '. $eventNotes);
            }

            FormHelper::setGeneralError('Login Unsuccessful');

            // redisplay the form with input values and error(s). reset password.
            $_SESSION[App::SESSION_KEYS['requestInput']]['password_hash'] = '';
            return $response->withRedirect($this->router->pathFor(ROUTE_LOGIN));
        }

        // successful login
        FormHelper::unsetSessionVars();
        $this->systemEvents->insertInfo('Login', (int) $this->authentication->getUserId());

        // redirect to proper resource
        if (isset($_SESSION[App::SESSION_KEYS['gotoAdminPath']])) {
            $redirect = $_SESSION[App::SESSION_KEYS['gotoAdminPath']];
            unset($_SESSION[App::SESSION_KEYS['gotoAdminPath']]);
        } else {
            $redirect = $this->router->pathFor($this->authentication->getAdminHomeRouteForUser());
        }

        return $response->withRedirect($redirect);
    }

    public function getLogout(Request $request, Response $response)
    {
        if (!$username = $this->authentication->getUserUsername()) {
            $this->systemEvents->insertWarning('Attempted logout for non-logged-in visitor');
        } else {
            $this->systemEvents->insertInfo('Logout', (int) $this->authentication->getUserId());
            $this->authentication->logout();
        }

        return $response->withRedirect($this->router->pathFor(ROUTE_HOME));
    }
}
