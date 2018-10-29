<?php
declare(strict_types=1);

namespace Infrastructure\Security\Authentication;

use Infrastructure\SlimPostgres;
use Infrastructure\BaseMVC\Controller\BaseController;
use Infrastructure\BaseMVC\View\Forms\FormHelper;
use Slim\Http\Request;
use Slim\Http\Response;

class AuthenticationController extends BaseController
{
    function routePostLogin(Request $request, Response $response, $args)
    {
        $this->setRequestInput($request, $this->authentication->getLoginFields());
        $username = $this->requestInput[$this->authentication->getUsernameFieldName()];
        $password = $this->requestInput[$this->authentication->getPasswordFieldName()];

        $validator = new AuthenticationValidator($this->requestInput, $this->authentication);

        if (!$validator->validate()) {
            // redisplay the form with input values and error(s)
            FormHelper::setFieldErrors($validator->getFirstErrors());
            $av = new AuthenticationView($this->container);
            $args[SlimPostgres::USER_INPUT_KEY] = $this->requestInput;
            return (new AuthenticationView($this->container))->routeGetLogin($request, $response, $args);
        }

        if (!$this->authentication->attemptLogin($username, $password)) {
            $this->insertFailureEvent();

            if ($this->authentication->tooManyFailedLogins()) {
                $eventTitle = 'Maximum unsuccessful login attempts exceeded';
                $eventNotes = 'Failed:'.$this->authentication->getNumFailedLogins();
                $this->events->insertAlert($eventTitle, null, $eventNotes);

                // redirect to home page with error message
                $_SESSION[SlimPostgres::SESSION_KEY_NOTICE] = ['Login disabled. Too many failed logins.', 'error'];
                return $response->withRedirect($this->router->pathFor(ROUTE_HOME));
            }

            FormHelper::setGeneralError('Login Unsuccessful');

            // reset password for security.
            $this->requestInput[$this->authentication->getPasswordFieldName()] = '';

            // redisplay the form with input values and error(s), and with 401 unauthenticated status
            $args = array_merge($args, ['status' => 401]);
            return (new AuthenticationView($this->container))->routeGetLogin($request, $response, $args);
        }

        /** successful login */
        $administratorId = $this->authentication->getAdministratorId();
        $administratorUsername = $this->authentication->getAdministratorUsername();
        /** enter null in administrator_id event arg since not logged in at time of request */
        $this->events->insertInfo('Login', null, "administrator id: $administratorId|username: $administratorUsername");
        SlimPostgres::setAdminNotice("Logged in");

        // redirect to proper resource
        if (isset($_SESSION[SlimPostgres::SESSION_KEY_GOTO_ADMIN_PATH])) {
            $redirect = $_SESSION[SlimPostgres::SESSION_KEY_GOTO_ADMIN_PATH];
            unset($_SESSION[SlimPostgres::SESSION_KEY_GOTO_ADMIN_PATH]);
        } else {
            $redirect = $this->router->pathFor($this->authentication->getAdminHomeRouteForAdministrator());
        }

        return $response->withRedirect($redirect);
    }

    private function insertFailureEvent() 
    {
        if (null === $reason = $this->authentication->getFailReason()) {
            throw new \Exception("Failure Reason Required");
        }

        /** insert event */
        switch ($reason) {
            case 'password':
                $eventMethod = 'insertInfo';
                $eventNotes = 'Incorrect Password';   
                break;

            case 'nonexistent':
                $eventMethod = 'insertWarning';
                $eventNotes = 'Non-existent Administrator';  
                break;  

            case 'inactive':
                $eventMethod = 'insertSecurity';
                $eventNotes = 'Inactive Administrator';    
                break;
        }

        $this->events->{$eventMethod}('Login Failure', $this->authentication->getFailAdministratorId(), $eventNotes);
    }

    public function routeGetLogout(Request $request, Response $response)
    {
        if (null === $username = $this->authentication->getAdministratorUsername()) {
            $this->events->insertWarning('Attempted logout for non-logged-in visitor');
        } else {
            $this->events->insertInfo('Logout', (int) $this->authentication->getAdministratorId());
            $this->authentication->logout();
        }

        return $response->withRedirect($this->router->pathFor(ROUTE_HOME));
    }
}
