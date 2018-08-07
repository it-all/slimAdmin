<?php
declare(strict_types=1);

namespace SlimPostgres\Security\Authentication;

use It_All\FormFormer\Form;
use SlimPostgres\Administrators\Administrator;
use SlimPostgres\Administrators\AdministratorsMapper;
use SlimPostgres\Administrators\LoginAttempts\LoginAttemptsMapper;
use SlimPostgres\App;
use SlimPostgres\Forms\DatabaseTableForm;
use SlimPostgres\Forms\FormHelper;

class AuthenticationService
{
    private $maxFailedLogins;
    private $administratorHomeRoutes;

    const USERNAME_FIELD = 'username';
    const PASSWORD_FIELD = 'password_hash';

    public function __construct(int $maxFailedLogins, array $administratorHomeRoutes)
    {
        $this->maxFailedLogins = $maxFailedLogins;
        $this->administratorHomeRoutes = $administratorHomeRoutes;
    }

    /** returns session info for logged in administrator */
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
        // note highest role comes first
        foreach ($this->getAdministratorRoles() as $roleId => $roleInfo) {
            if (isset($this->administratorHomeRoutes['roles'][$roleInfo[App::SESSION_ADMINISTRATOR_KEY_ROLES_NAME]])) {
                return $this->administratorHomeRoutes['roles'][$roleInfo[App::SESSION_ADMINISTRATOR_KEY_ROLES_NAME]];
            }
        }
        
        // default
        return ROUTE_ADMIN_HOME_DEFAULT;
    }

    public function attemptLogin(string $username, string $password): bool
    {
        $administratorsMapper = AdministratorsMapper::getInstance();
        // check if administrator exists
        if (null === $administrator = $administratorsMapper->getObjectByUsername($username, false)) {
            $this->loginFailed($username, null);
            return false;
        }

        // verify administrator is active
        if (!$administrator->isActive()) {
            $this->loginFailed($username, $administrator);
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

    /** this should only be called when the logged in administrator updates her/his own info */
    public function updateAdministratorSession(Administrator $administrator)
    {
        // be sure the current id matches the new one
        if ($this->getAdministratorId() != $administrator->getId()) {
            throw new \InvalidArgumentException("Administrator id to update must match current administrator id");
        }
        
        $this->setAdministratorSession($administrator);
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
        /** Unset all of the session variables */
        $_SESSION = [];

        /** Destroy the session */
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }

    public function getLoginFields(): array
    {
        return [self::USERNAME_FIELD, self::PASSWORD_FIELD];
    }

    /** note there should be different validation rules for logging in than creating users.
     * ie no minlength or character rules on password here
     */
    public function getLoginFieldValidationRules(): array
    {
        return [
            'required' => [[self::USERNAME_FIELD], [self::PASSWORD_FIELD]]
        ];
    }

    /** no need to refresh passwordValue */
    public function getForm(string $csrfNameKey, string $csrfNameValue, string $csrfValueKey, string $csrfValueValue, string $action, ?string $usernameValue = null)
    {
        $administratorsMapper = AdministratorsMapper::getInstance();

        $fields = [];
        $fields[] = DatabaseTableForm::getFieldFromDatabaseColumn($administratorsMapper->getColumnByName(self::USERNAME_FIELD), null, $usernameValue);
        $fields[] = DatabaseTableForm::getFieldFromDatabaseColumn($administratorsMapper->getColumnByName(self::PASSWORD_FIELD), null, null, 'Password', 'password');
        $fields[] = FormHelper::getCsrfNameField($csrfNameKey, $csrfNameValue);
        $fields[] = FormHelper::getCsrfValueField($csrfValueKey, $csrfValueValue);
        $fields[] = FormHelper::getSubmitField();

        return new Form($fields, ['method' => 'post', 'action' => $action, 'novalidate' => 'novalidate'], FormHelper::getGeneralError());
    }

    public function getUsernameFieldName(): string 
    {
        return self::USERNAME_FIELD;
    }

    public function getPasswordFieldName(): string 
    {
        return self::PASSWORD_FIELD;
    }
}
