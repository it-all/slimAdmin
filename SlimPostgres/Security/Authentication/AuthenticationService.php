<?php
declare(strict_types=1);

namespace SlimPostgres\Security\Authentication;

use It_All\FormFormer\Form;
use SlimPostgres\Administrators\AdministratorsModel;
use SlimPostgres\Administrators\Logins\LoginsModel;
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

    public function getUser(): ?array
    {
        if (isset($_SESSION[App::SESSION_KEY_ADMINISTRATOR])) {
            return $_SESSION[App::SESSION_KEY_ADMINISTRATOR];
        }
        return null;
    }

    public function check(): bool
    {
        return isset($_SESSION[App::SESSION_KEY_ADMINISTRATOR]);
    }

    public function getUserId(): ?int
    {
        if (isset($_SESSION[App::SESSION_KEY_ADMINISTRATOR][App::SESSION_ADMINISTRATOR_KEY_ID])) {
            return (int) $_SESSION[App::SESSION_KEY_ADMINISTRATOR][App::SESSION_ADMINISTRATOR_KEY_ID];
        }
        return null;
    }

    public function getUserName(): ?string
    {
        if (isset($_SESSION[App::SESSION_KEY_ADMINISTRATOR][App::SESSION_ADMINISTRATOR_KEY_NAME])) {
            return $_SESSION[App::SESSION_KEY_ADMINISTRATOR][App::SESSION_ADMINISTRATOR_KEY_NAME];
        }
        return null;
    }

    public function getUserUsername(): ?string
    {
        if (isset($_SESSION[App::SESSION_KEY_ADMINISTRATOR][App::SESSION_ADMINISTRATOR_KEY_USERNAME])) {
            return $_SESSION[App::SESSION_KEY_ADMINISTRATOR][App::SESSION_ADMINISTRATOR_KEY_USERNAME];
        }
        return null;
    }

    public function getUserRole(): ?string
    {
        if (isset($_SESSION[App::SESSION_KEY_ADMINISTRATOR][App::SESSION_ADMINISTRATOR_KEY_ROLE])) {
            return $_SESSION[App::SESSION_KEY_ADMINISTRATOR][App::SESSION_ADMINISTRATOR_KEY_ROLE];
        }
        return null;
    }

    public function getAdminHomeRouteForUser(): string
    {
        // determine home route: either by username, by role, or default
        if (isset($this->administratorHomeRoutes['usernames'][$this->getUserUsername()])) {
            $homeRoute = $this->administratorHomeRoutes['usernames'][$this->getUserUsername()];
        } elseif (isset($this->administratorHomeRoutes['roles'][$this->getUserRole()])) {
            $homeRoute = $this->administratorHomeRoutes['roles'][$this->getUserRole()];
        } else {
            $homeRoute = ROUTE_ADMIN_HOME_DEFAULT;
        }

        return $homeRoute;
    }

    public function attemptLogin(string $username, string $password): bool
    {
        $administrators = new AdministratorsModel();
        $rs = $administrators->selectForUsername($username);
        $userRecord = pg_fetch_assoc($rs);

        // check if user exists
        if (!$userRecord) {
            $this->loginFailed($username);
            return false;
        }

        // verify password for that user
        if (password_verify($password, $userRecord['password_hash'])) {
            $this->loginSucceeded($username, $userRecord);
            return true;
        } else {
            $this->loginFailed($username, (int) $userRecord['id']);
            return false;
        }
    }

    private function loginSucceeded(string $username, array $userRecord)
    {
        // set session for administrator
        $_SESSION[App::SESSION_KEY_ADMINISTRATOR] = [
            App::SESSION_ADMINISTRATOR_KEY_ID => $userRecord['id'],
            App::SESSION_ADMINISTRATOR_KEY_NAME => $userRecord['name'],
            App::SESSION_ADMINISTRATOR_KEY_USERNAME => $username,
            App::SESSION_ADMINISTRATOR_KEY_ROLE => $userRecord['role']
        ];

        unset($_SESSION[App::SESSION_KEY_NUM_FAILED_LOGINS]);

        $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = ["Logged in", App::STATUS_ADMIN_NOTICE_SUCCESS];

        // insert login_attempts record
        (new LoginsModel())->insertSuccessfulLogin($username, (int) $userRecord['id']);
    }

    private function incrementNumFailedLogins()
    {
        if (isset($_SESSION[App::SESSION_KEY_NUM_FAILED_LOGINS])) {
            $_SESSION[App::SESSION_KEY_NUM_FAILED_LOGINS]++;
        } else {
            $_SESSION[App::SESSION_KEY_NUM_FAILED_LOGINS] = 1;
        }
    }

    private function loginFailed(string $username, int $adminId = null)
    {
        $this->incrementNumFailedLogins();

        // insert login_attempts record
        (new LoginsModel())->insertFailedLogin($username, $adminId);
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
        $administratorsModel = new AdministratorsModel();

        $fields = [];
        $fields[] = DatabaseTableForm::getFieldFromDatabaseColumn($administratorsModel->getColumnByName('username'));
        $fields[] = DatabaseTableForm::getFieldFromDatabaseColumn($administratorsModel->getColumnByName('password_hash'), null, null, 'Password', 'password');
        $fields[] = FormHelper::getCsrfNameField($csrfNameKey, $csrfNameValue);
        $fields[] = FormHelper::getCsrfValueField($csrfValueKey, $csrfValueValue);
        $fields[] = FormHelper::getSubmitField();

        return new Form($fields, ['method' => 'post', 'action' => $action, 'novalidate' => 'novalidate'], FormHelper::getGeneralError());
    }
}
