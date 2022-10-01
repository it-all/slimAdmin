<?php
declare(strict_types=1);

namespace Infrastructure\Security\Authentication;

use Infrastructure\SlimAdmin;
use Infrastructure\BaseEntity\BaseMVC\Controller\BaseController;
use Infrastructure\BaseEntity\BaseMVC\View\Forms\FormHelper;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Routing\RouteCollector;
use Slim\Routing\RouteContext;

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
            $args[SlimAdmin::USER_INPUT_KEY] = $this->requestInput;
            return (new AuthenticationView($this->container))->routeGetLogin($request, $response, $args);
        }

        $routeContext = RouteContext::fromRequest($request);
        $routeParser = $routeContext->getRouteParser();

        if (!$this->authentication->attemptLogin($username, $password)) {
            $this->insertFailureEvent();

            if ($this->authentication->tooManyFailedLogins()) {
                $this->events->insertSecurity(EVENT_MAX_LOGIN_FAULT, ['number' => $this->authentication->getNumFailedLogins()]);

                // redirect to home page with error message
                $_SESSION[SlimAdmin::SESSION_KEY_NOTICE] = ['Login disabled. Too many failed logins.', 'error'];
                return $response
                    ->withHeader('Location', $routeParser->urlFor(ROUTE_HOME))
                    ->withStatus(302);
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
        $this->events->insertInfo(EVENT_LOGIN, ['administratorId' => $administratorId, 'username' => $administratorUsername]);
        $this->sendEventNotificationEmail('owner', 'login');
        SlimAdmin::addAdminNotice("Logged in");

        // redirect to proper resource
        if (isset($_SESSION[SlimAdmin::SESSION_KEY_GOTO_ADMIN_PATH])) {
            $redirect = $_SESSION[SlimAdmin::SESSION_KEY_GOTO_ADMIN_PATH];
            unset($_SESSION[SlimAdmin::SESSION_KEY_GOTO_ADMIN_PATH]);
        } else {
            $redirect = $this->authentication->getAdminHomeRouteForAdministrator();
        }
        // use routeParser for named routes eventually
        return $response
            ->withHeader('Location', $this->container->get('routeParser')->urlFor($redirect))
            ->withStatus(302);
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
                $eventPayload = [
                    'reason' => 'Incorrect Password',
                    'administratorId' => $this->authentication->getFailAdministratorId()
                ];
                break;

            case 'nonexistent':
                $eventMethod = 'insertWarning';
                $eventPayload = [
                    'reason' => 'Non-existent Administrator',
                    'username' => $this->authentication->getNonexistentUsername()
                ];
                break;  

            case 'inactive':
                $eventMethod = 'insertSecurity';
                $eventPayload = [
                    'reason' => 'Inactive Administrator',
                    'administratorId' => $this->authentication->getFailAdministratorId()
                ];
                break;

            default:
                throw new \Exception("Invalid Failure Reason: $reason");
        }

        $this->events->{$eventMethod}(EVENT_LOGIN_FAIL, $eventPayload);
    }

    /** since this extends BaseController and not AdminController, the administratorId property of Events service must be set for the logout function in order to have it know the current logged in administrator */
    public function routeGetLogout(Request $request, Response $response)
    {
        $this->events->setAdministratorId($this->authentication->getAdministratorId());

        if (null === $username = $this->authentication->getAdministratorUsername()) {
            $this->events->insertWarning(EVENT_LOGOUT_FAULT);
        } else {
            $this->events->insertInfo(EVENT_LOGOUT);
            $this->authentication->logout();
        }

        return $response
            ->withHeader('Location', $this->container->get('routeParser')->urlFor(ROUTE_HOME))
            ->withStatus(302);
    }
}
