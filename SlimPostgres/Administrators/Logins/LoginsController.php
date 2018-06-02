<?php
declare(strict_types=1);

namespace SlimPostgres\Administrators\Logins;

use SlimPostgres\Database\SingleTable\SingleTableController;
use Slim\Container;

class LoginsController extends SingleTableController
{
    public function __construct(Container $container)
    {
        parent::__construct($container, new LoginsModel(), new LoginsView($container), 'logins');
    }
}
