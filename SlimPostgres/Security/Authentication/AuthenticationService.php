<?php
declare(strict_types=1);

namespace SlimPostgres\Security\Authentication;

use It_All\FormFormer\Form;
use Domain\Administrators\AdministratorsModel;
use Domain\Administrators\Logins\LoginsModel;
use SlimPostgres\App;
use SlimPostgres\Forms\DatabaseTableForm;
use SlimPostgres\Forms\FormHelper;

class AuthenticationService
{
    private $maxFailedLogins;
    private $adminHomeRoutes;

    public function __construct(int $maxFailedLogins, array $adminHomeRoutes)
    {
        $this->maxFailedLogins = $maxFailedLogins;
        $this->adminHomeRoutes = $adminHomeRoutes;
    }

    public function getUser(): ?array
    {
        if (isset($_SESSION[App::SESSION_KEYS['user']])) {
            return $_SESSION[App::SESSION_KEYS['user']];
        }
        return null;
    }

    public function check(): bool
    {
        return isset($_SESSION[App::SESSION_KEYS['user']]);
    }

    public function getUserId(): ?int
    {
        if (isset($_SESSION[App::SESSION_KEYS['user']][App::SESSION_KEYS['userId']])) {
            return (int) $_SESSION[App::SESSION_KEYS['user']][App::SESSION_KEYS['userId']];
        }
        return null;
    }

    public function getUserName(): ?string
    {
        if (isset($_SESSION[App::SESSION_KEYS['user']][App::SESSION_KEYS['userName']])) {
            return $_SESSION[App::SESSION_KEYS['user']][App::SESSION_KEYS['userName']];
        }
        return null;
    }

    public function getUserUsername(): ?string
    {
        if (isset($_SESSION[App::SESSION_KEYS['user']][App::SESSION_KEYS['userUsername']])) {
            return $_SESSION[App::SESSION_KEYS['user']][App::SESSION_KEYS['userUsername']];
        }
        return null;
    }

    public function getUserRole(): ?string
    {
        if (isset($_SESSION[App::SESSION_KEYS['user']][App::SESSION_KEYS['userRole']])) {
            return $_SESSION[App::SESSION_KEYS['user']][App::SESSION_KEYS['userRole']];
        }
        return null;
    }

    public function getAdminHomeRouteForUser(): string
    {
        // determine home route: either by username, by role, or default
        if (isset($this->adminHomeRoutes['usernames'][$this->getUserUsername()])) {
            $homeRoute = $this->adminHomeRoutes['usernames'][$this->getUserUsername()];
        } elseif (isset($this->adminHomeRoutes['roles'][$this->getUserRole()])) {
            $homeRoute = $this->adminHomeRoutes['roles'][$this->getUserRole()];
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
        // set session for user
        $_SESSION[App::SESSION_KEYS['user']] = [
            App::SESSION_KEYS['userId'] => $userRecord['id'],
            App::SESSION_KEYS['userName'] => $userRecord['name'],
            App::SESSION_KEYS['userUsername'] => $username,
            App::SESSION_KEYS['userRole'] => $userRecord['role']
        ];

        unset($_SESSION[App::SESSION_KEYS['numFailedLogins']]);

        // insert login_attempts record
        (new LoginsModel())->insertSuccessfulLogin($username, (int) $userRecord['id']);
    }

    private function loginFailed(string $username, int $adminId = null)
    {
        if (isset($_SESSION[App::SESSION_KEYS['numFailedLogins']])) {
            $_SESSION[App::SESSION_KEYS['numFailedLogins']]++;
        } else {
            $_SESSION[App::SESSION_KEYS['numFailedLogins']] = 1;
        }

        // insert login_attempts record
        (new LoginsModel())->insertFailedLogin($username, $adminId);
    }

    public function tooManyFailedLogins(): bool
    {
        return isset($_SESSION[App::SESSION_KEYS['numFailedLogins']]) &&
            $_SESSION[App::SESSION_KEYS['numFailedLogins']] > $this->maxFailedLogins;
    }

    public function getNumFailedLogins(): int
    {
        return (isset($_SESSION[App::SESSION_KEYS['numFailedLogins']])) ? $_SESSION[App::SESSION_KEYS['numFailedLogins']] : 0;
    }

    public function logout()
    {
        unset($_SESSION[App::SESSION_KEYS['user']]);
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
