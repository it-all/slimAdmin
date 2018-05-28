<?php
declare(strict_types=1);

namespace It_All\Slim_Postgres\Infrastructure\Utilities;

use Valitron\Validator;

class ValitronValidatorExtension extends Validator
{
    public function getFirstErrors()
    {
        $firstErrors = [];
        foreach (parent::errors() as $fieldName => $fieldErrors)
        {
            $firstErrors[$fieldName] = $this->fixErrorMessage($fieldName, $fieldErrors[0]);
        }

        return $firstErrors;
    }

    private function fixErrorMessage(string $fieldName, string $errorMessage): string
    {
        // ie username -> Username, password_hash -> Password Hash to match incoming $errorMessage
        $messageFieldName = ucwords(str_replace('_', ' ', $fieldName));

        switch ($errorMessage) {
            case $messageFieldName . ' is required':
                $newMessage = 'required';
                break;

            default:
                $newMessage = str_replace($messageFieldName . ' ', '', $errorMessage);
        }
        return $newMessage;
    }
}
