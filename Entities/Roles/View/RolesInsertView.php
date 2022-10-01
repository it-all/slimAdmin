<?php
declare(strict_types=1);

namespace Entities\Roles\View;

use Entities\Roles\Model\RolesTableMapper;
use Infrastructure\SlimAdmin;
use Infrastructure\BaseEntity\DatabaseTable\View\DatabaseTableForm;
use Infrastructure\BaseEntity\BaseMVC\View\Forms\FormHelper;
use Psr\Container\ContainerInterface as Container;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Infrastructure\BaseEntity\BaseMVC\View\AdminView;

class RolesInsertView extends AdminView
{
    protected $mapper;

    public function __construct(Container $container)
    {
        $this->mapper = RolesTableMapper::getInstance();
        $this->routePrefix = ROUTEPREFIX_ROLES;
        parent::__construct($container);
    }

    public function routeGetInsert(Request $request, Response $response, $args)
    {
        return $this->insertView($request, $response, $args);
    }

    /** this can be called for both the initial get and the posted form if errors exist (from controller) */
    public function insertView(Request $request, Response $response, $args)
    {
        $formFieldData = ($request->getMethod() === 'POST' && isset($args[SlimAdmin::USER_INPUT_KEY])) ? $args[SlimAdmin::USER_INPUT_KEY] : null;

        $form = new DatabaseTableForm($this->mapper, $this->container->get('routeParser')->urlFor(SlimAdmin::getRouteName(true, $this->routePrefix, 'insert', 'post')), $this->csrf->getTokenNameKey(), $this->csrf->getTokenName(), $this->csrf->getTokenValueKey(), $this->csrf->getTokenValue(), 'insert', $formFieldData);
        
        FormHelper::unsetSessionFormErrors();

        return $this->view->render(
            $response,
            'Admin/form.php',
            [
                'title' => 'Insert '. $this->mapper->getFormalTableName(),
                'formHtml' => $form->generate(),
                'focusFieldId' => $form->getFocusFieldId(),
                'navigationItems' => $this->navigationItems,
                'notice' => $this->getNotice(),
            ]
        );
    }
}
