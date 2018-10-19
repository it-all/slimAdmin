<?php
declare(strict_types=1);

namespace Entities\Permissions\View\Forms;

use Slim\Container;

class PermissionInsertForm extends PermissionForm
{
    public function __construct(string $formAction, Container $container, array $fieldValues = [])
    {
        parent::__construct($formAction, $container, false, $fieldValues);
    }
}
