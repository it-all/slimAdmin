<?php
declare(strict_types=1);

namespace SlimPostgres;

use SlimPostgres\App;
use SlimPostgres\InsertUpdateViews;
use SlimPostgres\ResponseUtilities;
use SlimPostgres\Forms\DatabaseTableForm;
use SlimPostgres\Database\DataMappers\TableMappers;
use SlimPostgres\Forms\FormHelper;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

// a list view class for a single database table
abstract class DatabaseTableView extends AdminListView implements InsertUpdateViews
{
    use ResponseUtilities;

    protected $routePrefix;
    protected $mapper;

    public function __construct(Container $container, TableMappers $mapper, string $routePrefix, bool $addDeleteColumnToListView = true, string $listViewTemplate = 'admin/lists/resultsList.php')
    {
        $this->mapper = $mapper;
        $this->routePrefix = $routePrefix;

        parent::__construct($container, $routePrefix, App::getRouteName(true, $routePrefix, 'index'), $this->mapper, App::getRouteName(true, $routePrefix, 'index.reset'), $listViewTemplate);

        $insertLinkInfo = ($this->authorization->isAuthorized($this->getPermissions('insert'))) ? ['text' => 'Insert '.$this->mapper->getFormalTableName(false), 'route' => App::getRouteName(true, $this->routePrefix, 'insert')] : false;
        $this->setInsert($insertLinkInfo);

        $allowUpdate = $this->authorization->isAuthorized($this->getPermissions('update')) && $this->mapper->getPrimaryKeyColumnName() !== null;

        $this->setUpdate($allowUpdate, $this->mapper->getPrimaryKeyColumnName(), App::getRouteName(true, $this->routePrefix, 'update', 'put'));

        $this->setDelete($this->container->authorization->isAuthorized($this->getPermissions('delete')), App::getRouteName(true, $this->routePrefix, 'delete'));

    }

    public function routeGetInsert(Request $request, Response $response, $args)
    {
        return $this->insertView($request, $response, $args);
    }

    /** this can be called for both the initial get and the posted form if errors exist (from controller) */
    public function insertView(Request $request, Response $response, $args)
    {
        $formFieldData = ($request->isPost() && isset($args[App::USER_INPUT_KEY])) ? $args[App::USER_INPUT_KEY] : null;

        $form = new DatabaseTableForm($this->mapper, $this->router->pathFor(App::getRouteName(true, $this->routePrefix, 'insert', 'post')), $this->csrf->getTokenNameKey(), $this->csrf->getTokenName(), $this->csrf->getTokenValueKey(), $this->csrf->getTokenValue(), 'insert', $formFieldData);
        
        FormHelper::unsetSessionFormErrors();

        return $this->view->render(
            $response,
            'admin/form.php',
            [
                'title' => 'Insert '. $this->mapper->getFormalTableName(false),
                'form' => $form,
                'navigationItems' => $this->navigationItems
            ]
        );
    }

    public function routeGetUpdate(Request $request, Response $response, $args)
    {
        return $this->updateView($request, $response, $args);
    }

    /** this can be called for both the initial get and the posted form if errors exist (from controller) */
    public function updateView(Request $request, Response $response, $args)
    {
        // make sure there is a record for the mapper
        if (!$record = $this->mapper->selectForPrimaryKey($args['primaryKey'])) {
            return $this->databaseRecordNotFound($response, $args['primaryKey'], $this->mapper, 'update');
        }

        $formFieldData = ($request->isPut() && isset($args[App::USER_INPUT_KEY])) ? $args[App::USER_INPUT_KEY] : $record;

        $form = new DatabaseTableForm($this->mapper, $this->router->pathFor(App::getRouteName(true, $this->routePrefix, 'update', 'put'), ['primaryKey' => $args['primaryKey']]), $this->csrf->getTokenNameKey(), $this->csrf->getTokenName(), $this->csrf->getTokenValueKey(), $this->csrf->getTokenValue(), 'update', $formFieldData);
        
        FormHelper::unsetSessionFormErrors();

        return $this->view->render(
            $response,
            'admin/form.php',
            [
                'title' => 'Update ' . $this->mapper->getFormalTableName(false),
                'form' => $form,
                'primaryKey' => $args['primaryKey'],
                'navigationItems' => $this->navigationItems,
                'hideFocus' => true
            ]
        );
    }
}
