<?php
declare(strict_types=1);

namespace SlimPostgres\Security\Authentication;

use SlimPostgres\App;
use SlimPostgres\Controllers\BaseController;
use SlimPostgres\UserInterface\Forms\FormHelper;
use Slim\Http\Request;
use Slim\Http\Response;

class AuthenticationController extends BaseController
{
    function postLogin(Request $request, Response $response, $args)
    {
        $this->setRequestInput($request);
        $username = $_SESSION[App::SESSION_KEY_REQUEST_INPUT]['username'];
        $password = $_SESSION[App::SESSION_KEY_REQUEST_INPUT]['password_hash'];

        $this->validator = $this->validator->withData($_SESSION[App::SESSION_KEY_REQUEST_INPUT], $this->authentication->getLoginFields());

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

                // redirect to home page with error message
                $_SESSION[App::SESSION_KEY_NOTICE] = ['Login disabled. Too many failed logins.', 'error'];
                return $response->withRedirect($this->router->pathFor(ROUTE_HOME));
            }

            FormHelper::setGeneralError('Login Unsuccessful');

            // reset password.
            $_SESSION[App::SESSION_KEY_REQUEST_INPUT]['password_hash'] = '';

            // redisplay the form with input values and error(s), and with 401 unauthenticated status
            $args = array_merge($args, ['status' => 401]);
            return (new AuthenticationView($this->container))->getLogin($request, $response, $args);
        }

        // successful login
        FormHelper::unsetFormSessionVars();
        $this->systemEvents->insertInfo('Login', (int) $this->authentication->getAdministratorId());

        // redirect to proper resource
        if (isset($_SESSION[App::SESSION_KEY_GOTO_ADMIN_PATH])) {
            $redirect = $_SESSION[App::SESSION_KEY_GOTO_ADMIN_PATH];
            unset($_SESSION[App::SESSION_KEY_GOTO_ADMIN_PATH]);
        } else {
            $redirect = $this->router->pathFor($this->authentication->getAdminHomeRouteForAdministrator());
        }

        return $response->withRedirect($redirect);
    }

    public function getLogout(Request $request, Response $response)
    {
        if (!$username = $this->authentication->getAdministratorUsername()) {
            $this->systemEvents->insertWarning('Attempted logout for non-logged-in visitor');
        } else {
            $this->systemEvents->insertInfo('Logout', (int) $this->authentication->getAdministratorId());
            $this->authentication->logout();
        }

        return $response->withRedirect($this->router->pathFor(ROUTE_HOME));
    }
}
