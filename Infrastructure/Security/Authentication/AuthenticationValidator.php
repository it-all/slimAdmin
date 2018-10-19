<?php
declare(strict_types=1);

namespace Infrastructure\Security\Authentication;

use Infrastructure\Validation\ValitronValidatorExtension;

// sets validation rules for login form
class AuthenticationValidator extends ValitronValidatorExtension
{
    public function __construct(array $inputData, AuthenticationService $authentication)
    {
        parent::__construct($inputData, $authentication->getLoginFields());
        $this->rules($authentication->getLoginFieldValidationRules());
    }
}
