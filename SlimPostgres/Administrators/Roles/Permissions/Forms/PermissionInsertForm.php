<?php
declare(strict_types=1);

namespace SlimPostgres\Administrators\Roles\Permissions\Forms;

use Slim\Container;
use SlimPostgres\Administrators\Roles\Permissions\Forms\PermissionForm;

class PermissionInsertForm extends PermissionForm
{
    public function __construct(string $formAction, Container $container, array $fieldValues = [])
    {
        $this->formMethod = 'post';
        parent::__construct($formAction, $container, $fieldValues);
    }
}
