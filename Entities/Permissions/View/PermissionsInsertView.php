<?php
declare(strict_types=1);

namespace Entities\Permissions\View;

use Infrastructure\SlimAdmin;
use Infrastructure\BaseEntity\BaseMVC\View\ResponseUtilities;
use Entities\Permissions\Model\PermissionsEntityMapper;
use Entities\Permissions\Model\PermissionsTableMapper;
use Entities\Permissions\View\Forms\PermissionForm;
use Entities\Permissions\View\Forms\PermissionInsertForm;
use Psr\Container\ContainerInterface as Container;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Infrastructure\BaseEntity\BaseMVC\View\AdminView;
use Infrastructure\BaseEntity\BaseMVC\View\Forms\FormHelper;

class PermissionsInsertView extends AdminView
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

    public function routeGetInsert(Request $request, Response $response, $args)
    {
        return $this->insertView($request, $response, $args);
    }

    /** this can be called for both the initial get and the posted form if errors exist (from controller) */
    public function insertView(Request $request, Response $response, $args)
    {
        $formAction = $this->container->get('routeParser')->urlFor(SlimAdmin::getRouteName(true, $this->routePrefix, 'insert', 'post'));

        // set $fieldValues
        if ($request->getMethod() === 'POST' && isset($args[SlimAdmin::USER_INPUT_KEY])) {
            $fieldValues = [];
            $fieldValues[PermissionForm::TITLE_FIELD_NAME] = $args[SlimAdmin::USER_INPUT_KEY][PermissionForm::TITLE_FIELD_NAME];
            $fieldValues[PermissionForm::DESCRIPTION_FIELD_NAME] = $args[SlimAdmin::USER_INPUT_KEY][PermissionForm::DESCRIPTION_FIELD_NAME];
            $fieldValues[PermissionForm::ROLES_FIELDSET_NAME] = $args[SlimAdmin::USER_INPUT_KEY][PermissionForm::ROLES_FIELDSET_NAME];
            $activeInput = $args[SlimAdmin::USER_INPUT_KEY][PermissionForm::ACTIVE_FIELD_NAME];
            $fieldValues[PermissionForm::ACTIVE_FIELD_NAME] = isset($activeInput) && $activeInput == 'on';
        } else {
            $fieldValues = null;
        }

        $form = (new PermissionForm($formAction, $this->container, false, $fieldValues))->getForm();

        FormHelper::unsetSessionFormErrors();

        return $this->view->render(
            $response,
            'Admin/form.php',
            [
                'title' => $this->permissionsEntityMapper->getInsertTitle(),
                'formHtml' => $form->generate(),
                'focusFieldId' => $form->getFocusFieldId(),
                'navigationItems' => $this->navigationItems,
                'notice' => $this->getNotice(),
            ]
        );
    }
}
