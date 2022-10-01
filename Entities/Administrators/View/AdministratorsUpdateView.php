<?php
declare(strict_types=1);

namespace Entities\Administrators\View;

use Entities\Administrators\Model\AdministratorsEntityMapper;
use Entities\Administrators\Model\AdministratorsTableMapper;
use Entities\Administrators\View\Forms\AdministratorForm;
use Entities\Administrators\View\Forms\AdministratorUpdateForm;
use Infrastructure\SlimAdmin;
use Infrastructure\BaseEntity\BaseMVC\View\ResponseUtilities;
use Infrastructure\BaseEntity\BaseMVC\View\AdminView;
use Psr\Container\ContainerInterface as Container;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Infrastructure\BaseEntity\BaseMVC\View\Forms\FormHelper;

class AdministratorsUpdateView extends AdminView
{
    use ResponseUtilities;

    private $administratorsEntityMapper;
    private $administratorsTableMapper;
    protected $routePrefix;

    public function __construct(Container $container)
    {
        $this->administratorsEntityMapper = AdministratorsEntityMapper::getInstance();
        $this->administratorsTableMapper = AdministratorsTableMapper::getInstance();
        $this->routePrefix = ROUTEPREFIX_ADMINISTRATORS;
        parent::__construct($container);
    }

    public function routeGetUpdate(Request $request, Response $response, $args)
    {
        return $this->updateView($request, $response, $args);
    }

    /** this can be called for both the initial get and the posted form if errors exist (from controller) */
    public function updateView(Request $request, Response $response, $args)
    {
        // make sure there is an administrator for the primary key
        if (null === $administrator = $this->administratorsEntityMapper->getObjectById((int) $args[ROUTEARG_PRIMARY_KEY])) {
            return $this->databaseRecordNotFound($response, $args[ROUTEARG_PRIMARY_KEY], $this->administratorsTableMapper, 'update');
        }

        $formAction = $this->container->get('routeParser')->urlFor(SlimAdmin::getRouteName(true, $this->routePrefix, 'update', 'put'), [ROUTEARG_PRIMARY_KEY => $args[ROUTEARG_PRIMARY_KEY]]);

        $fieldValues = [];
        if ($request->getMethod() === 'PUT' && isset($args[SlimAdmin::USER_INPUT_KEY])) {
            $fieldValues[AdministratorForm::NAME_FIELD_NAME] = $args[SlimAdmin::USER_INPUT_KEY][AdministratorForm::NAME_FIELD_NAME];
            $fieldValues[AdministratorForm::USERNAME_FIELD_NAME] = $args[SlimAdmin::USER_INPUT_KEY][AdministratorForm::USERNAME_FIELD_NAME];
            $fieldValues[AdministratorForm::PASSWORD_FIELD_NAME] = $args[SlimAdmin::USER_INPUT_KEY][AdministratorForm::PASSWORD_FIELD_NAME];
            $fieldValues[AdministratorForm::PASSWORDCONFIRM_FIELD_NAME] = $args[SlimAdmin::USER_INPUT_KEY][AdministratorForm::PASSWORDCONFIRM_FIELD_NAME];
            $fieldValues[AdministratorForm::ROLES_FIELDSET_NAME] = $args[SlimAdmin::USER_INPUT_KEY][AdministratorForm::ROLES_FIELDSET_NAME];
            $activeInput = $args[SlimAdmin::USER_INPUT_KEY][AdministratorForm::ACTIVE_FIELD_NAME];
            $fieldValues[AdministratorForm::ACTIVE_FIELD_NAME] = isset($activeInput) && $activeInput == 'on';
        } else {
            $fieldValues[AdministratorForm::NAME_FIELD_NAME] = $administrator->getName();
            $fieldValues[AdministratorForm::USERNAME_FIELD_NAME] = $administrator->getUsername();
            $fieldValues[AdministratorForm::PASSWORD_FIELD_NAME] = '';
            $fieldValues[AdministratorForm::PASSWORDCONFIRM_FIELD_NAME] = '';
            $fieldValues[AdministratorForm::ROLES_FIELDSET_NAME] = $administrator->getRoleIds();
            $fieldValues[AdministratorForm::ACTIVE_FIELD_NAME] = $administrator->getActive();
        }

        $updateForm = new AdministratorUpdateForm($formAction, $this->container, $fieldValues);
        $form = $updateForm->getForm();

        FormHelper::unsetSessionFormErrors();

        return $this->view->render(
            $response,
            'Admin/form.php',
            [
                'title' => $this->administratorsEntityMapper->getUpdateTitle(),
                'formHtml' => $form->generate(),
                'focusFieldId' => $form->getFocusFieldId(),
                'primaryKey' => $args[ROUTEARG_PRIMARY_KEY],
                'navigationItems' => $this->navigationItems,
                'hideFocus' => true,
                'notice' => $this->getNotice(),
            ]
        );
    }
}
