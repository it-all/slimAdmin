<?php
declare(strict_types=1);

namespace Domain\Administrators;

use Domain\Administrators\Roles\Roles;
use Domain\Administrators\Roles\RolesModel;
use It_All\FormFormer\Fields\InputField;
use It_All\FormFormer\Form;
use SlimPostgres\App;
use SlimPostgres\Database\SingleTable\SingleTableHelper;
use SlimPostgres\ListView;
use SlimPostgres\Forms\DatabaseTableForm;
use SlimPostgres\Forms\FormHelper;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

class AdministratorsView extends ListView
{
    protected $routePrefix;
    protected $administratorsModel;

    public function __construct(Container $container)
    {
        $this->routePrefix = ROUTEPREFIX_ADMIN_ADMINISTRATORS;
        $this->administratorsModel = new AdministratorsModel();

        parent::__construct($container, 'administrators', ROUTE_ADMIN_ADMINISTRATORS, $this->administratorsModel, ROUTE_ADMIN_ADMINISTRATORS_RESET, 'admin/lists/administratorsList.twig');

        $insertLink = ($this->authorization->check($this->container->settings['authorization'][App::getRouteName(true, $this->routePrefix, 'insert')])) ? ['text' => 'Insert '.$this->administratorsModel->getPrimaryTableName(false), 'route' => App::getRouteName(true, $this->routePrefix, 'insert')] : false;
        $this->setInsert($insertLink);

        $this->setUpdate($this->authorization->check($this->getPermissions('update')), $this->administratorsModel->getUpdateColumnName(), App::getRouteName(true, $this->routePrefix, 'update', 'put'));

        $this->setDelete($this->container->authorization->check($this->getPermissions('delete')), App::getRouteName(true, $this->routePrefix, 'delete'));
    }

    private function pwFieldsHaveError(): bool
    {
        return strlen(FormHelper::getFieldError('password')) > 0 || strlen(FormHelper::getFieldError('password_confirm')) > 0;
    }

    private function getForm(Request $request, string $action = 'insert', int $primaryKey = null,  array $record = null)
    {
        if ($action != 'insert' && $action != 'update') {
            throw new \Exception("Invalid action $action");
        }

        $fields = [];

        // pw fields are not required on edit forms (leave blank to keep existing)
        if ($action == 'insert') {
            $fieldValues = ($request->isGet()) ? [] : $_SESSION[App::SESSION_KEY_REQUEST_INPUT];
            $formAction = $this->router->pathFor(App::getRouteName(true, $this->routePrefix, 'insert', 'post'));
            $passwordLabel = 'Password';
            $passwordFieldsRequired = true;
        } else {
            $fieldValues = ($request->isGet()) ? $record : $_SESSION[App::SESSION_KEY_REQUEST_INPUT];
            $formAction = $this->router->pathFor(App::getRouteName(true, $this->routePrefix, 'update', 'put'), ['primaryKey' => $primaryKey]);
            $passwordLabel = 'Password [leave blank to keep existing]';
            $passwordFieldsRequired = false;
            $fields[] = FormHelper::getPutMethodField();
        }

        // Name Field
        $nameValue = (isset($fieldValues['name'])) ? $fieldValues['name'] : '';
        $fields[] = DatabaseTableForm::getFieldFromDatabaseColumn($this->administratorsModel->getPrimaryTableModel()->getColumnByName('name'), null, $nameValue);

        // Username Field
        $usernameValue = (isset($fieldValues['username'])) ? $fieldValues['username'] : '';
        $fields[] = DatabaseTableForm::getFieldFromDatabaseColumn($this->administratorsModel->getPrimaryTableModel()->getColumnByName('username'), null, $usernameValue);

        // Password Fields
        // determine values of pw and pw conf fields
        // values will persist if no errors in either field
        if ($request->isGet()) {
            $passwordValue = '';
            $passwordConfirmationValue = '';
        } else {
            $passwordValue = ($this->pwFieldsHaveError()) ? '' : $fieldValues['password'];
            $passwordConfirmationValue = ($this->pwFieldsHaveError()) ? '' : $fieldValues['password_confirm'];
        }

        $passwordFieldAttributes = ['name' => 'password', 'id' => 'password', 'type' => 'password', 'value' => $passwordValue];
        $passwordConfirmationFieldAttributes = ['name' => 'password_confirm', 'id' => 'password_confirm', 'type' => 'password', 'value' => $passwordConfirmationValue];
        if ($passwordFieldsRequired) {
            $passwordFieldAttributes = array_merge($passwordFieldAttributes, ['required' => 'required']);
            $passwordConfirmationFieldAttributes = array_merge($passwordConfirmationFieldAttributes, ['required' => 'required']);
        }

        $fields[] = new InputField($passwordLabel, $passwordFieldAttributes, FormHelper::getFieldError('password'));

        $fields[] = new InputField('Confirm Password', $passwordConfirmationFieldAttributes, FormHelper::getFieldError('password_confirm'));

        // Role Field
        $rolesModel = new RolesModel($this->container->settings['adminDefaultRole']);
        $selectedOption = (isset($fieldValues['role_id'])) ? (int) $fieldValues['role_id'] : null;
        $fields[] = $rolesModel->getIdSelectField(['name' => 'role_id', 'id' => 'role_id', 'required' => 'required'], 'Role', $selectedOption, true, FormHelper::getFieldError('role_id'));

        // CSRF Fields
        $fields[] = FormHelper::getCsrfNameField($this->csrf->getTokenNameKey(), $this->csrf->getTokenName());
        $fields[] = FormHelper::getCsrfValueField($this->csrf->getTokenValueKey(), $this->csrf->getTokenValue());

        // Submit Field
        $fields[] = FormHelper::getSubmitField();

        $form = new Form($fields, ['method' => 'post', 'action' => $formAction, 'novalidate' => 'novalidate'], FormHelper::getGeneralError());
        FormHelper::unsetSessionVars();

        return $form;
    }

    /** this can be called for both the initial get and the posted form if errors exist (from controller) */
    public function getInsert(Request $request, Response $response, $args)
    {
        return $this->view->render(
            $response,
            'admin/form.php',
            [
                'title' => 'Insert '. $this->administratorsModel->getPrimaryTableName(false),
                'form' => $this->getForm($request),
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
        if (!$record = $this->administratorsModel->getPrimaryTableModel()->selectForPrimaryKey($args['primaryKey'])) {
            return SingleTableHelper::updateRecordNotFound($this->container, $response, $args['primaryKey'], $this->administratorsModel->getPrimaryTableModel(), $this->routePrefix);
        }

        return $this->view->render(
            $response,
            'admin/form.php',
            [
                'title' => 'Update ' . $this->administratorsModel->getPrimaryTableModel()->getFormalTableName(false),
                'form' => $this->getForm($request, 'update', (int) $args['primaryKey'], $record),
                'primaryKey' => $args['primaryKey'],
                'navigationItems' => $this->navigationItems
            ]
        );
    }
}
