<?php
declare(strict_types=1);

namespace SlimPostgres\Security\Authentication;

use It_All\FormFormer\Form;
use SlimPostgres\Administrators\Administrator;
use SlimPostgres\Administrators\AdministratorsMapper;
use SlimPostgres\Administrators\Logins\LoginAttemptsMapper;
use SlimPostgres\App;
use SlimPostgres\Forms\DatabaseTableForm;
use SlimPostgres\Forms\FormHelper;

class AuthenticationService
{
    private $maxFailedLogins;
    private $administratorHomeRoutes;

    public function __construct(int $maxFailedLogins, array $administratorHomeRoutes)
    {
        $this->maxFailedLogins = $maxFailedLogins;
        $this->administratorHomeRoutes = $administratorHomeRoutes;
    }

    public function getAdministrator(): ?array
    {
        if (isset($_SESSION[App::SESSION_KEY_ADMINISTRATOR])) {
            return $_SESSION[App::SESSION_KEY_ADMINISTRATOR];
        }
        return null;
    }

    public function isAuthenticated(): bool
    {
        return isset($_SESSION[App::SESSION_KEY_ADMINISTRATOR]);
    }

    public function getAdministratorId(): ?int
    {
        if (isset($_SESSION[App::SESSION_KEY_ADMINISTRATOR][App::SESSION_ADMINISTRATOR_KEY_ID])) {
            return (int) $_SESSION[App::SESSION_KEY_ADMINISTRATOR][App::SESSION_ADMINISTRATOR_KEY_ID];
        }
        return null;
    }

    public function getAdministratorName(): ?string
    {
        if (isset($_SESSION[App::SESSION_KEY_ADMINISTRATOR][App::SESSION_ADMINISTRATOR_KEY_NAME])) {
            return $_SESSION[App::SESSION_KEY_ADMINISTRATOR][App::SESSION_ADMINISTRATOR_KEY_NAME];
        }
        return null;
    }

    public function getAdministratorUsername(): ?string
    {
        if (isset($_SESSION[App::SESSION_KEY_ADMINISTRATOR][App::SESSION_ADMINISTRATOR_KEY_USERNAME])) {
            return $_SESSION[App::SESSION_KEY_ADMINISTRATOR][App::SESSION_ADMINISTRATOR_KEY_USERNAME];
        }
        return null;
    }

    public function getAdministratorRoles(): array
    {
        if (isset($_SESSION[App::SESSION_KEY_ADMINISTRATOR][App::SESSION_ADMINISTRATOR_KEY_ROLES])) {
            return $_SESSION[App::SESSION_KEY_ADMINISTRATOR][App::SESSION_ADMINISTRATOR_KEY_ROLES];
        }
        return null;
    }
 // determine home route: either by username, by role, or default
    public function getAdminHomeRouteForAdministrator(): string
    {
        // by username
        if (isset($this->administratorHomeRoutes['usernames'][$this->getAdministratorUsername()])) {
            return $this->administratorHomeRoutes['usernames'][$this->getAdministratorUsername()];
        }

        // by role
        foreach ($this->getAdministratorRoles() as $roleId => $roleInfo) {
            if (isset($this->administratorHomeRoutes['roles'][$roleInfo['roleName']])) {
                $homeRoute = $this->administratorHomeRoutes['roles'][$roleInfo['roleName']];
            }
        }
        
        // default
        return ROUTE_ADMIN_HOME_DEFAULT;
    }

    public function attemptLogin(string $username, string $password): bool
    {
        $administratorsMapper = AdministratorsMapper::getInstance();
        // check if administrator exists
        if (!$administrator = $administratorsMapper->getObjectByUsername($username)) {
            $this->loginFailed($username, null);
            return false;
        }

        // verify password for that user
        if (password_verify($password, $administrator->getPasswordHash())) {
            $this->loginSucceeded($username, $administrator);
            return true;
        } else {
            $this->loginFailed($username, $administrator);
            return false;
        }
    }

    private function setAdministratorSession(Administrator $administrator)
    {
        $_SESSION[App::SESSION_KEY_ADMINISTRATOR] = [
            App::SESSION_ADMINISTRATOR_KEY_ID => $administrator->getId(),
            App::SESSION_ADMINISTRATOR_KEY_NAME => $administrator->getName(),
            App::SESSION_ADMINISTRATOR_KEY_USERNAME => $administrator->getUsername(),
            App::SESSION_ADMINISTRATOR_KEY_ROLES => $administrator->getRoles()
        ];
    }

    private function loginSucceeded(string $username, Administrator $administrator)
    {
        $this->setAdministratorSession($administrator);
        unset($_SESSION[App::SESSION_KEY_NUM_FAILED_LOGINS]);
        $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = ["Logged in", App::STATUS_ADMIN_NOTICE_SUCCESS];
        (LoginAttemptsMapper::getInstance())->insertSuccessfulLogin($administrator);
    }

    private function incrementNumFailedLogins()
    {
        if (isset($_SESSION[App::SESSION_KEY_NUM_FAILED_LOGINS])) {
            $_SESSION[App::SESSION_KEY_NUM_FAILED_LOGINS]++;
        } else {
            $_SESSION[App::SESSION_KEY_NUM_FAILED_LOGINS] = 1;
        }
    }

    private function loginFailed(string $username, ?Administrator $administrator)
    {
        $this->incrementNumFailedLogins();

        // insert login_attempts record
        (LoginAttemptsMapper::getInstance())->insertFailedLogin($username, $administrator);
    }

    public function tooManyFailedLogins(): bool
    {
        return isset($_SESSION[App::SESSION_KEY_NUM_FAILED_LOGINS]) &&
            $_SESSION[App::SESSION_KEY_NUM_FAILED_LOGINS] >= $this->maxFailedLogins;
    }

    public function getNumFailedLogins(): int
    {
        return (isset($_SESSION[App::SESSION_KEY_NUM_FAILED_LOGINS])) ? $_SESSION[App::SESSION_KEY_NUM_FAILED_LOGINS] : 0;
    }

    public function logout()
    {
        unset($_SESSION[App::SESSION_KEY_ADMINISTRATOR]);
    }

    public function getLoginFields(): array
    {
        return ['username', 'password_hash'];
    }

    /** note there should be different validation rules for logging in than creating users.
     * ie no minlength or character rules on password here
     */
    public function getLoginFieldValidationRules(): array
    {
        return [
            'required' => [['username'], ['password_hash']]
        ];
    }

    public function getForm(string $csrfNameKey, string $csrfNameValue, string $csrfValueKey, string $csrfValueValue, string $action)
    {
        $administratorsMapper = AdministratorsMapper::getInstance();

        $fields = [];
        $fields[] = DatabaseTableForm::getFieldFromDatabaseColumn($administratorsMapper->getColumnByName('username'));
        $fields[] = DatabaseTableForm::getFieldFromDatabaseColumn($administratorsMapper->getColumnByName('password_hash'), null, null, 'Password', 'password');
        $fields[] = FormHelper::getCsrfNameField($csrfNameKey, $csrfNameValue);
        $fields[] = FormHelper::getCsrfValueField($csrfValueKey, $csrfValueValue);
        $fields[] = FormHelper::getSubmitField();

        return new Form($fields, ['method' => 'post', 'action' => $action, 'novalidate' => 'novalidate'], FormHelper::getGeneralError());
    }
}
