<?php
declare(strict_types=1);

namespace Infrastructure\Security\Authentication;

use It_All\FormFormer\Form;
use Entities\Administrators\Model\Administrator;
use Entities\Administrators\Model\AdministratorsEntityMapper;
use Entities\Administrators\Model\AdministratorsTableMapper;
use Infrastructure\SlimPostgres;
use Infrastructure\BaseEntity\DatabaseTable\View\DatabaseTableForm;
use Infrastructure\BaseEntity\BaseMVC\View\Forms\FormHelper;

class AuthenticationService
{
    private $maxFailedLogins;
    private $administratorHomeRoutes;

    /** @var string must be one of FAIL_REASONS */
    private $failReason;

    /** @var Administrator if username maps to existing administrator but authentication fails due to incorrect password or inactive administrator, this will be populated */
    private $failAdministrator;

    /** @var string stores username entered when login fails because username is nonexistent */
    private $nonexistentUsername;

    /** @var \SlimPostgres\Administrators\Administrator|null the logged in administrator model or null */
    private $administrator;

    const USERNAME_FIELD = 'username';
    const PASSWORD_FIELD = 'password_hash';

    /** incorrect password, inactive administrator, non-existent administrator */
    const FAIL_REASONS = ['password', 'inactive', 'nonexistent'];

    public function __construct(int $maxFailedLogins, array $administratorHomeRoutes)
    {
        $this->maxFailedLogins = $maxFailedLogins;
        $this->administratorHomeRoutes = $administratorHomeRoutes;
        /** set administrator property to administrator id found in session or null */
        $this->administrator = (isset($_SESSION[SlimPostgres::SESSION_KEY_ADMINISTRATOR_ID])) ? (AdministratorsEntityMapper::getInstance()->getObjectById($_SESSION[SlimPostgres::SESSION_KEY_ADMINISTRATOR_ID])) : null;
    }

    public function getFailReason(): ?string 
    {
        return $this->failReason;
    }

    private function setFailReason(string $reason) 
    {
        if (!\in_array($reason, self::FAIL_REASONS)) {
            throw new \InvalidArgumentException("Invalid fail reason $reason");
        }

        $this->failReason = $reason;
    }

    public function getFailAdministrator(): ?Administrator 
    {
        return $this->failAdministrator;
    }

    private function setFailAdministrator(Administrator $administrator) 
    {
        $this->failAdministrator = $administrator;
    }

    public function getFailAdministratorId(): ?int 
    {
        if ($this->failAdministrator !== null) {
            return $this->failAdministrator->getId();
        }
        return null;
    }

    /** stores username entered when login fails because username is nonexistent */
    private function setNonexistentUsername(string $username) 
    {
        $this->nonexistentUsername = $username;
    }

    public function getNonexistentUsername(): ?string 
    {
        return $this->nonexistentUsername;
    }

    /** check that the administrator session is set and that the administrator is still active */
    public function isAuthenticated(): bool
    {
        if ($this->administrator !== null) {
            return $this->isAdministratorActive();
        }
        return false;
    }

    public function attemptLogin(string $username, string $password): bool
    {
        $administratorsEntityMapper = AdministratorsEntityMapper::getInstance();
        // check if administrator exists
        if (null === $administrator = $administratorsEntityMapper->getObjectByUsername($username, false)) {
            $this->loginFailed($username, 'nonexistent', $administrator);
            return false;
        }

        // verify administrator is active
        if (!$administrator->isActive()) {
            $this->loginFailed($username, 'inactive', $administrator);
            return false;
        }

        // verify password for that user
        if (password_verify($password, $administrator->getPasswordHash())) {
            $this->loginSucceeded($username, $administrator);
            return true;
        } else {
            $this->loginFailed($username, 'password', $administrator);
            return false;
        }
    }

    private function incrementNumFailedLogins()
    {
        if (isset($_SESSION[SlimPostgres::SESSION_KEY_NUM_FAILED_LOGINS])) {
            $_SESSION[SlimPostgres::SESSION_KEY_NUM_FAILED_LOGINS]++;
        } else {
            $_SESSION[SlimPostgres::SESSION_KEY_NUM_FAILED_LOGINS] = 1;
        }
    }

    private function loginSucceeded(string $username, Administrator $administrator)
    {
        $_SESSION[SlimPostgres::SESSION_KEY_ADMINISTRATOR_ID] = $administrator->getId();
        unset($_SESSION[SlimPostgres::SESSION_KEY_NUM_FAILED_LOGINS]);
        $this->administrator = $administrator;        
    }

    private function loginFailed(string $username, string $reason, ?Administrator $administrator = null)
    {
        $this->setFailReason($reason);
        if (null !== $administrator) {
            $this->setFailAdministrator($administrator);
        } elseif ($reason == 'nonexistent') {
            $this->setNonexistentUsername($username);
        }
        $this->incrementNumFailedLogins();
    }

    public function tooManyFailedLogins(): bool
    {
        return isset($_SESSION[SlimPostgres::SESSION_KEY_NUM_FAILED_LOGINS]) &&
            $_SESSION[SlimPostgres::SESSION_KEY_NUM_FAILED_LOGINS] >= $this->maxFailedLogins;
    }

    public function getNumFailedLogins(): int
    {
        return (isset($_SESSION[SlimPostgres::SESSION_KEY_NUM_FAILED_LOGINS])) ? $_SESSION[SlimPostgres::SESSION_KEY_NUM_FAILED_LOGINS] : 0;
    }

    public function logout()
    {
        unset($_SESSION[SlimPostgres::SESSION_KEY_ADMINISTRATOR_ID]);
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
        $administratorsTableMapper = AdministratorsTableMapper::getInstance();

        $fields = [];
        $fields[] = DatabaseTableForm::getFieldFromDatabaseColumn($administratorsTableMapper->getColumnByName(self::USERNAME_FIELD), null, $usernameValue);
        $fields[] = DatabaseTableForm::getFieldFromDatabaseColumn($administratorsTableMapper->getColumnByName(self::PASSWORD_FIELD), null, null, 'Password', 'password');
        $fields[] = FormHelper::getCsrfNameField($csrfNameKey, $csrfNameValue);
        $fields[] = FormHelper::getCsrfValueField($csrfValueKey, $csrfValueValue);
        $fields[] = FormHelper::getSubmitField();

        return new Form($fields, ['method' => 'post', 'action' => $action, 'novalidate' => 'novalidate'], FormHelper::getGeneralError());
    }

    /** returns logged in administrator model object */
    public function getAdministrator(): ?Administrator
    {
        return $this->administrator;
    }

    public function getAdministratorId(): ?int 
    {
        if ($this->administrator !== null) {
            return $this->administrator->getId();
        }
        return null;
    }

    public function getAdministratorUsername(): ?string 
    {
        if ($this->administrator !== null) {
            return $this->administrator->getUsername();
        }
        return null;
    }

    public function getAdministratorName(): ?string 
    {
        if ($this->administrator !== null) {
            return $this->administrator->getName();
        }
        return null;
    }

    public function isAdministratorActive(): bool 
    {
        if ($this->administrator !== null) {
            return $this->administrator->getActive();
        }
        return false;
    }

    // determine home route: either by username, by role, or default
    public function getAdminHomeRouteForAdministrator(): string
    {
        if (array_key_exists($this->getAdministratorUsername(), $this->administratorHomeRoutes)) {
            return $this->administratorHomeRoutes[$this->getAdministratorUsername()];
        }
        
        // default
        return ROUTE_ADMIN_HOME_DEFAULT;
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
