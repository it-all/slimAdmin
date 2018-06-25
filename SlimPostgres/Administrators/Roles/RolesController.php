<?php
declare(strict_types=1);

namespace SlimPostgres\Administrators\Roles;

use SlimPostgres\App;
use SlimPostgres\Controllers\DatabaseTableController;
use Slim\Container;

class RolesController extends DatabaseTableController
{
    public function __construct(Container $container)
    {
        parent::__construct($container, new RolesMapper(), new RolesView($container), ROUTEPREFIX_ROLES);
    }

    // override to check condition and add custom return column
    protected function delete($primaryKey, string $returnColumn = null, bool $sendEmail = false)
    {
        // make sure role is not being used
        if ($this->mapper::hasAdministrator((int) $primaryKey)) {
            $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = ["Role in use", App::STATUS_ADMIN_NOTICE_FAILURE];
            return false;
        }

        parent::delete($primaryKey, 'role', $sendEmail);
    }
}
