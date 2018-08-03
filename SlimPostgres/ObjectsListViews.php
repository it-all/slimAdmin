<?php
declare(strict_types=1);

namespace SlimPostgres;

use Slim\Http\Request;
use Slim\Http\Response;

/** AdminListView is set up to select from a mapper and return a fetched recordset array to the list view template. This interface defines the functions that are used to override and send objects to the list view template instead. Note that the object list view template must be set in the implementing class constructor. */
interface ObjectsListViews
{
        /** overrides in order to get objects and send to indexView */
        public function routeIndex($request, Response $response, $args);
    
        /** overrides in order to get objects and send to indexView */
        public function routeIndexResetFilter(Request $request, Response $response, $args);
}
