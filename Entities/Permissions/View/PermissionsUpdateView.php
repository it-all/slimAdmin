<?php
declare(strict_types=1);

namespace Entities\Permissions\View;

use Infrastructure\SlimAdmin;
use Infrastructure\BaseEntity\BaseMVC\View\AdminView;
use Infrastructure\BaseEntity\BaseMVC\View\ResponseUtilities;
use Entities\Permissions\Model\PermissionsEntityMapper;
use Entities\Permissions\Model\PermissionsTableMapper;
use Entities\Permissions\View\Forms\PermissionForm;
use Entities\Permissions\View\Forms\PermissionUpdateForm;
use Psr\Container\ContainerInterface as Container;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Infrastructure\BaseEntity\BaseMVC\View\Forms\FormHelper;

class PermissionsUpdateView extends AdminView
{
    use ResponseUtilities;

    private $permissionsEntityMapper;
    private $permissionsTableMapper;
    protected $routePrefix;

    public function __construct(Container $container)
    {
        $this->permissionsEntityMapper = PermissionsEntityMapper::getInstance();
        $this->permissionsTableMapper = PermissionsTableMapper::getInstance();
        $this->routePrefix = ROUTEPREFIX_PERMISSIONS;

        parent::__construct($container);
    }

    public function routeGetUpdate(Request $request, Response $response, $args)
    {
        return $this->updateView($request, $response, $args);
    }

    /** this can be called for both the initial get and the posted form if errors exist (from controller) */
    public function updateView(Request $request, Response $response, $args)
    {
        // make sure there is a permission for the primary key
        if (null === $permission = $this->permissionsEntityMapper->getObjectById((int) $args[ROUTEARG_PRIMARY_KEY])) {
            return $this->databaseRecordNotFound($response, $args[ROUTEARG_PRIMARY_KEY], $this->permissionsTableMapper, 'update');
        }

        $formAction = $this->container->get('routeParser')->urlFor(SlimAdmin::getRouteName(true, $this->routePrefix, 'update', 'put'), [ROUTEARG_PRIMARY_KEY => $args[ROUTEARG_PRIMARY_KEY]]);

        $fieldValues = [];
        if ($request->getMethod() === 'PUT' && isset($args[SlimAdmin::USER_INPUT_KEY])) {
            $fieldValues[PermissionForm::TITLE_FIELD_NAME] = $args[SlimAdmin::USER_INPUT_KEY][PermissionForm::TITLE_FIELD_NAME];
            $fieldValues[PermissionForm::DESCRIPTION_FIELD_NAME] = $args[SlimAdmin::USER_INPUT_KEY][PermissionForm::DESCRIPTION_FIELD_NAME];
            $fieldValues[PermissionForm::ROLES_FIELDSET_NAME] = $args[SlimAdmin::USER_INPUT_KEY][PermissionForm::ROLES_FIELDSET_NAME];
            $activeInput = $args[SlimAdmin::USER_INPUT_KEY][PermissionForm::ACTIVE_FIELD_NAME];
            $fieldValues[PermissionForm::ACTIVE_FIELD_NAME] = isset($activeInput) && $activeInput == 'on';
        } else {
            $fieldValues[PermissionForm::TITLE_FIELD_NAME] = $permission->getTitle();
            $fieldValues[PermissionForm::DESCRIPTION_FIELD_NAME] = $permission->getDescription();
            $fieldValues[PermissionForm::ROLES_FIELDSET_NAME] = $permission->getRoleIds();
            $fieldValues[PermissionForm::ACTIVE_FIELD_NAME] = $permission->getActive();
        }

        $updateForm = new PermissionForm($formAction, $this->container, true, $fieldValues);
        $form = $updateForm->getForm();

        FormHelper::unsetSessionFormErrors();

        return $this->view->render(
            $response,
            'Admin/form.php',
            [
                'title' => $this->permissionsEntityMapper->getUpdateTitle(),
                'formHtml' => $form->generate(),
                'primaryKey' => $args[ROUTEARG_PRIMARY_KEY],
                'navigationItems' => $this->navigationItems,
                'hideFocus' => true,
                'notice' => $this->getNotice(),
            ]
        );
    }
}
