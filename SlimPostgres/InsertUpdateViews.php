<?php
declare(strict_types=1);

namespace SlimPostgres;

use Slim\Http\Request;
use Slim\Http\Response;

/** defines functions used in view classes which handle inserts and updates to database tables/entites */
interface InsertUpdateViews
{
    public function routeGetInsert(Request $request, Response $response, $args);
    public function insertView(Request $request, Response $response, $args);
    public function routeGetUpdate(Request $request, Response $response, $args);
    public function updateView(Request $request, Response $response, $args);
}
