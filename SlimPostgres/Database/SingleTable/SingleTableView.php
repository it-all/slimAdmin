<?php
declare(strict_types=1);

namespace SlimPostgres\Database\SingleTable;

use SlimPostgres\App;
use SlimPostgres\UserInterface\AdminListView;
use SlimPostgres\Forms\DatabaseTableForm;
use SlimPostgres\Forms\FormHelper;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

class SingleTableView extends AdminListView
{
    protected $routePrefix;
    protected $model;

    public function __construct(Container $container, SingleTableModel $model, string $routePrefix, bool $addDeleteColumnToListView = true)
    {
        $this->model = $model;
        $this->routePrefix = $routePrefix;

        parent::__construct($container, $routePrefix, App::getRouteName(true, $routePrefix, 'index'), $this->model, App::getRouteName(true, $routePrefix, 'index.reset'));

        $insertLink = ($this->authorization->check($this->getPermissions('insert'))) ? ['text' => 'Insert '.$this->model->getFormalTableName(false), 'route' => App::getRouteName(true, $this->routePrefix, 'insert')] : false;
        $this->setInsert($insertLink);

        $allowUpdate = $this->authorization->check($this->getPermissions('update')) && $this->model->getPrimaryKeyColumnName() !== null;

        $this->setUpdate($allowUpdate, $this->model->getPrimaryKeyColumnName(), App::getRouteName(true, $this->routePrefix, 'update', 'put'));

        $this->setDelete($this->container->authorization->check($this->getPermissions('delete')), App::getRouteName(true, $this->routePrefix, 'delete'));

    }

    public function getInsert(Request $request, Response $response, $args)
    {
        return $this->insertView($request, $response, $args);
    }

    /** this can be called for both the initial get and the posted form if errors exist (from controller) */
    public function insertView(Request $request, Response $response, $args)
    {
        $formFieldData = ($request->isGet()) ? null : $_SESSION[App::SESSION_KEY_REQUEST_INPUT];

        $form = new DatabaseTableForm($this->model, $this->router->pathFor(App::getRouteName(true, $this->routePrefix, 'insert', 'post')), $this->csrf->getTokenNameKey(), $this->csrf->getTokenName(), $this->csrf->getTokenValueKey(), $this->csrf->getTokenValue(), 'insert', $formFieldData);
        FormHelper::unsetFormSessionVars();

        return $this->view->render(
            $response,
            'admin/form.php',
            [
                'title' => 'Insert '. $this->model->getFormalTableName(false),
                'form' => $form,
                'navigationItems' => $this->navigationItems
            ]
        );
    }

    public function getUpdate(Request $request, Response $response, $args)
    {
        return $this->updateView($request, $response, $args);
    }

    /** this can be called for both the initial get and the posted form if errors exist (from controller) */
    public function updateView(Request $request, Response $response, $args)
    {
        // make sure there is a record for the model
        if (!$record = $this->model->selectForPrimaryKey($args['primaryKey'])) {
            return SingleTableHelper::updateRecordNotFound($this->container, $response, $args['primaryKey'], $this->model, $this->routePrefix);
        }

        $formFieldData = ($request->isGet()) ? $record : $_SESSION[App::SESSION_KEY_REQUEST_INPUT];

        $form = new DatabaseTableForm($this->model, $this->router->pathFor(App::getRouteName(true, $this->routePrefix, 'update', 'put'), ['primaryKey' => $args['primaryKey']]), $this->csrf->getTokenNameKey(), $this->csrf->getTokenName(), $this->csrf->getTokenValueKey(), $this->csrf->getTokenValue(), 'update', $formFieldData);
        FormHelper::unsetFormSessionVars();

        return $this->view->render(
            $response,
            'admin/form.php',
            [
                'title' => 'Update ' . $this->model->getFormalTableName(false),
                'form' => $form,
                'primaryKey' => $args['primaryKey'],
                'navigationItems' => $this->navigationItems
            ]
        );
    }
}
