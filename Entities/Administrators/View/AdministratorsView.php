<?php
declare(strict_types=1);

namespace Entities\Administrators\View;

use It_All\FormFormer\Fields\InputFields\CheckboxRadioInputField;
use It_All\FormFormer\Fieldset;
use Exceptions\QueryFailureException;
use Entities\Administrators\Model\Administrator;
use Entities\Administrators\Model\AdministratorsEntityMapper;
use Entities\Administrators\View\Forms\AdministratorInsertForm;
use Entities\Administrators\View\Forms\AdministratorUpdateForm;
use It_All\FormFormer\Fields\InputField;
use It_All\FormFormer\Form;
use Infrastructure\SlimPostgres;
use Infrastructure\BaseMVC\View\ObjectsListViews;
use Infrastructure\BaseMVC\View\InsertUpdateViews;
use Infrastructure\BaseMVC\View\ResponseUtilities;
use Infrastructure\Database\Queries\QueryBuilder;
use Infrastructure\BaseMVC\View\AdminListView;
use Infrastructure\BaseMVC\View\Forms\DatabaseTableForm;
use Infrastructure\BaseMVC\View\Forms\FormHelper;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

class AdministratorsView extends AdminListView implements ObjectsListViews, InsertUpdateViews
{
    use ResponseUtilities;

    private $administratorsEntityMapper;
    protected $routePrefix;

    const FILTER_FIELDS_PREFIX = 'administrators';

    public function __construct(Container $container)
    {
        $this->administratorsEntityMapper = AdministratorsEntityMapper::getInstance();
        $this->routePrefix = ROUTEPREFIX_ADMINISTRATORS;

        parent::__construct($container, self::FILTER_FIELDS_PREFIX, ROUTE_ADMINISTRATORS, $this->administratorsEntityMapper, ROUTE_ADMINISTRATORS_RESET, 'admin/lists/objectsList.php');

        $this->setInsert();
        $this->setUpdate();
        $this->setDelete();
    }

    protected function getResource(string $which): string 
    {
        switch ($which) {
            case 'insert':
                return ADMINISTRATORS_INSERT_RESOURCE;
                break;
            case 'update':
                return ADMINISTRATORS_UPDATE_RESOURCE;
                break;
            case 'delete':
                return ADMINISTRATORS_DELETE_RESOURCE;
                break;
            default:
                throw new \InvalidArgumentException("Undefined resource $which");
        }
    }

    /** overrides in order to get administrator objects and send to indexView */
    public function routeIndex(Request $request, Response $response, $args)
    {
        return $this->indexViewObjects($response);
    }

    /** overrides in order to get administrator objects and send to indexView */
    public function routeIndexResetFilter(Request $request, Response $response, $args)
    {
        // redirect to the clean url
        return $this->indexViewObjects($response, true);
    }

    /** get objects and send to parent indexView */
    public function indexViewObjects(Response $response, bool $resetFilter = false)
    {
        if ($resetFilter) {
            return $this->resetFilter($response, $this->indexRoute);
        }

        try {
            $administrators = $this->mapper->getObjects($this->getFilterColumnsInfo(), null, $this->authentication, $this->authorization);
        } catch (QueryFailureException $e) {
            $administrators = [];
            // warning system event is inserted when query fails
            SlimPostgres::setAdminNotice('Query Failed', 'failure');
        }
        
        return $this->indexView($response, $administrators);
    }

    public function routeGetInsert(Request $request, Response $response, $args)
    {
        return $this->insertView($request, $response, $args);
    }

    /** this can be called for both the initial get and the posted form if errors exist (from controller) */
    public function insertView(Request $request, Response $response, $args)
    {
        $formAction = $this->router->pathFor(SlimPostgres::getRouteName(true, $this->routePrefix, 'insert', 'post'));
        $fieldValues = ($request->isPost() && isset($args[SlimPostgres::USER_INPUT_KEY])) ? $args[SlimPostgres::USER_INPUT_KEY] : [];

        return $this->view->render(
            $response,
            'admin/form.php',
            [
                'title' => $this->administratorsEntityMapper->getInsertTitle(),
                'form' => (new AdministratorInsertForm($formAction, $this->container, $fieldValues))->getForm(),
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
        // make sure there is an administrator for the primary key
        if (null === $administrator = $this->mapper->getObjectById((int) $args['primaryKey'])) {
            return $this->databaseRecordNotFound($response, $args['primaryKey'], $this->administratorsEntityMapper, 'update');
        }

        $formAction = $this->router->pathFor(SlimPostgres::getRouteName(true, $this->routePrefix, 'update', 'put'), ['primaryKey' => $args['primaryKey']]);

        /** if input set we are redisplaying after invalid form submission */
        if ($request->isPut() && isset($args[SlimPostgres::USER_INPUT_KEY])) {
            $updateForm = new AdministratorUpdateForm($formAction, $this->container, $args[SlimPostgres::USER_INPUT_KEY]);
        } else {
            $updateForm = new AdministratorUpdateForm($formAction, $this->container);
            $updateForm->setFieldValuesToAdministrator($administrator);
        }

        return $this->view->render(
            $response,
            'admin/form.php',
            [
                'title' => $this->mapper->getUpdateTitle(),
                'form' => $updateForm->getForm(),
                // 'form' => $this->getForm($request, 'update', (int) $args['primaryKey'], $administrator),
                'primaryKey' => $args['primaryKey'],
                'navigationItems' => $this->navigationItems,
                'hideFocus' => true
            ]
        );
    }
}
