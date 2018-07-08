<?php
declare(strict_types=1);

namespace SlimPostgres\Administrators;

use It_All\FormFormer\Fields\InputFields\CheckboxRadioInputField;
use It_All\FormFormer\Fieldset;
use SlimPostgres\Administrators\Administrator;
use SlimPostgres\Administrators\Roles\Roles;
use SlimPostgres\Administrators\Roles\RolesMapper;
use It_All\FormFormer\Fields\InputField;
use It_All\FormFormer\Form;
use SlimPostgres\App;
use SlimPostgres\ResponseUtilities;
use SlimPostgres\Database\Queries\QueryBuilder;
use SlimPostgres\AdminListView;
use SlimPostgres\Forms\DatabaseTableForm;
use SlimPostgres\Forms\FormHelper;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

class AdministratorsView extends AdminListView
{
    use ResponseUtilities;

    protected $routePrefix;
    protected $administratorsMapper;

    public function __construct(Container $container)
    {
        $this->routePrefix = ROUTEPREFIX_ADMINISTRATORS;
        $this->administratorsMapper = AdministratorsMapper::getInstance();

        parent::__construct($container, 'administrators', ROUTE_ADMINISTRATORS, $this->administratorsMapper, ROUTE_ADMINISTRATORS_RESET, 'admin/lists/administratorsList.php');

        $insertLink = ($this->authorization->isAuthorized($this->getPermissions('insert'))) ? ['text' => 'Insert '.$this->administratorsMapper->getPrimaryTableName(false), 'route' => App::getRouteName(true, $this->routePrefix, 'insert')] : false;
        $this->setInsert($insertLink);

        $this->setUpdate($this->authorization->isAuthorized($this->getPermissions('update')), $this->administratorsMapper->getUpdateColumnName(), App::getRouteName(true, $this->routePrefix, 'update'));

        $this->setDelete($this->container->authorization->isAuthorized($this->getPermissions('delete')), App::getRouteName(true, $this->routePrefix, 'delete'));
    }

    private function getForm(Request $request, string $action = 'insert', int $primaryKey = null, Administrator $administrator = null)
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
        } else { // update
            if ($request->isGet()) { // database values
                $fieldValues['name'] = $administrator->getName();
                $fieldValues['username'] = $administrator->getUsername();
                $fieldValues['password'] = $administrator->getPasswordHash();
                $fieldValues['roles'] = $administrator->getRoleIds();
            } else {
                $fieldValues = $_SESSION[App::SESSION_KEY_REQUEST_INPUT];
            }
            $formAction = $this->router->pathFor(App::getRouteName(true, $this->routePrefix, 'update', 'put'), ['primaryKey' => $primaryKey]);
            $passwordLabel = 'Password [leave blank to keep existing]';
            $passwordFieldsRequired = false;
            $fields[] = FormHelper::getPutMethodField();
        }

        // Name Field
        $nameValue = (isset($fieldValues['name'])) ? $fieldValues['name'] : '';
        $fields[] = DatabaseTableForm::getFieldFromDatabaseColumn($this->administratorsMapper->getPrimaryTableMapper()->getColumnByName('name'), null, $nameValue);

        // Username Field
        $usernameValue = (isset($fieldValues['username'])) ? $fieldValues['username'] : '';
        $fields[] = DatabaseTableForm::getFieldFromDatabaseColumn($this->administratorsMapper->getPrimaryTableMapper()->getColumnByName('username'), null, $usernameValue);

        // Password Fields
        // determine values of pw and pw conf fields
        // values will persist if no errors in either field
        if ($request->isGet()) {
            $passwordValue = '';
            $passwordConfirmationValue = '';
        } else {
            if (mb_strlen(FormHelper::getFieldError('password')) > 0 || mb_strlen(FormHelper::getFieldError('password_confirm')) > 0) {
                $passwordValue = '';
                $passwordConfirmationValue = '';
            } else  {
                $passwordValue = $fieldValues['password'];
                $passwordConfirmationValue = $fieldValues['password_confirm'];
            }
        }

        $passwordFieldAttributes = ['name' => 'password', 'id' => 'password', 'type' => 'password', 'value' => $passwordValue];
        $passwordConfirmationFieldAttributes = ['name' => 'password_confirm', 'id' => 'password_confirm', 'type' => 'password', 'value' => $passwordConfirmationValue];
        if ($passwordFieldsRequired) {
            $passwordFieldAttributes = array_merge($passwordFieldAttributes, ['required' => 'required']);
            $passwordConfirmationFieldAttributes = array_merge($passwordConfirmationFieldAttributes, ['required' => 'required']);
        }

        $fields[] = new InputField($passwordLabel, $passwordFieldAttributes, FormHelper::getFieldError($passwordFieldAttributes['name']));

        $fields[] = new InputField('Confirm Password', $passwordConfirmationFieldAttributes, FormHelper::getFieldError($passwordConfirmationFieldAttributes['name']));

        // Roles Checkboxes
        $rolesMapper = RolesMapper::getInstance();
        $rolesCheckboxes = [];
        foreach ($rolesMapper->getRoles() as $roleId => $roleData) {
            $rolesCheckboxAttributes = [
                'type' => 'checkbox',
                'name' => 'roles[]',
                'value' => $roleId,
                'id' => 'roles' . $roleData['role'],
                'class' => 'inlineFormField'
            ];
            // checked?
            if (isset($fieldValues['roles']) && in_array($roleId, $fieldValues['roles'])) {
                $rolesCheckboxAttributes['checked'] = 'checked';
            }
            $rolesCheckboxes[] = new CheckboxRadioInputField($roleData['role'], $rolesCheckboxAttributes);
        }
        $fields[] = new Fieldset($rolesCheckboxes, [], true, 'Roles', null, FormHelper::getFieldError('roles', true));

        // CSRF Fields
        $fields[] = FormHelper::getCsrfNameField($this->csrf->getTokenNameKey(), $this->csrf->getTokenName());
        $fields[] = FormHelper::getCsrfValueField($this->csrf->getTokenValueKey(), $this->csrf->getTokenValue());

        // Submit Field
        $fields[] = FormHelper::getSubmitField();

        $form = new Form($fields, ['method' => 'post', 'action' => $formAction, 'novalidate' => 'novalidate'], FormHelper::getGeneralError());
        FormHelper::unsetFormSessionVars();

        return $form;
    }

    /** this can be called for both the initial get and the posted form if errors exist (from controller) */
    public function getInsert(Request $request, Response $response, $args)
    {
        return $this->view->render(
            $response,
            'admin/form.php',
            [
                'title' => 'Insert '. $this->administratorsMapper->getPrimaryTableName(false),
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
        // make sure there is an administrator for the primary key
        if (!$administrator = $this->administratorsMapper->getObjectById((int) $args['primaryKey'])) {
            return $this->databaseRecordNotFound($response, $args['primaryKey'], $this->administratorsMapper->getPrimaryTableMapper(), 'update');
        }

        return $this->view->render(
            $response,
            'admin/form.php',
            [
                'title' => 'Update ' . $this->administratorsMapper->getPrimaryTableMapper()->getFormalTableName(false),
                'form' => $this->getForm($request, 'update', (int) $args['primaryKey'], $administrator),
                'primaryKey' => $args['primaryKey'],
                'navigationItems' => $this->navigationItems
            ]
        );
    }

    /**
     * override in order to populate roles with multiple if necessary
     * @param Response $response
     * @param bool $resetFilter
     * @return AdministratorsView|AdminListView
     */
    public function indexView(Response $response, bool $resetFilter = false)
    {
        if ($resetFilter) {
            return $this->resetFilter($response, $this->indexRoute);
        }

        $filterColumnsInfo = (isset($_SESSION[$this->sessionFilterColumnsKey])) ? $_SESSION[$this->sessionFilterColumnsKey] : null;
        if ($results = $this->mapper->selectArray($this->mapper->getSelectColumnsString(), $filterColumnsInfo)) {
            $numResults = count($results);
        } else {
            $results = [];
            $numResults = 0;
        }

        $filterFieldValue = $this->getFilterFieldValue();
        $filterErrorMessage = FormHelper::getFieldError($this->sessionFilterFieldKey);

        // make sure all session input necessary to send to template is produced above
        FormHelper::unsetFormSessionVars();

        return $this->view->render(
            $response,
            $this->template,
            [
                'title' => $this->mapper->getFormalTableName(),
                'insertLink' => $this->insertLink,
                'filterOpsList' => QueryBuilder::getWhereOperatorsText(),
                'filterValue' => $filterFieldValue,
                'filterErrorMessage' => $filterErrorMessage,
                'filterFormActionRoute' => $this->indexRoute,
                'filterFieldName' => $this->sessionFilterFieldKey,
                'isFiltered' => $filterColumnsInfo,
                'resetFilterRoute' => $this->filterResetRoute,
                'updateColumn' => $this->updateColumn,
                'updatePermitted' => $this->updatePermitted,
                'updateRoute' => $this->updateRoute,
                'addDeleteColumn' => $this->addDeleteColumn,
                'deleteRoute' => $this->deleteRoute,
                'results' => $results,
                'numResults' => $numResults,
                'numColumns' => $this->mapper->getCountSelectColumns(),
                'sortColumn' => $this->mapper->getOrderByColumnName(),
                'sortByAsc' => $this->mapper->getOrderByAsc(),
                'navigationItems' => $this->navigationItems
            ]
        );
    }


}
