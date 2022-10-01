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
use Infrastructure\BaseEntity\BaseMVC\View\ResponseUtilities;

class RolesUpdateView extends AdminView
{
    use ResponseUtilities;
    
    protected $mapper;

    public function __construct(Container $container)
    {
        $this->mapper = RolesTableMapper::getInstance();
        $this->routePrefix = ROUTEPREFIX_ROLES;
        parent::__construct($container);
    }

    public function routeGetUpdate(Request $request, Response $response, $args)
    {
        return $this->updateView($request, $response, $args);
    }

    /** this can be called for both the initial get and the posted form if errors exist (from controller) */
    public function updateView(Request $request, Response $response, $args)
    {
        $primaryKeyValue = $args[ROUTEARG_PRIMARY_KEY];

        // make sure there is a record for the mapper
        if (null === $record = $this->mapper->selectForPrimaryKey($primaryKeyValue)) {
            return $this->databaseRecordNotFound($response, $primaryKeyValue, $this->mapper, 'update');
        }

        $formFieldData = ($request->getMethod() === 'PUT' && isset($args[SlimAdmin::USER_INPUT_KEY])) ? $args[SlimAdmin::USER_INPUT_KEY] : $record;

        $form = new DatabaseTableForm($this->mapper, $this->container->get('routeParser')->urlFor(SlimAdmin::getRouteName(true, $this->routePrefix, 'update', 'put'), [ROUTEARG_PRIMARY_KEY => $primaryKeyValue]), $this->csrf->getTokenNameKey(), $this->csrf->getTokenName(), $this->csrf->getTokenValueKey(), $this->csrf->getTokenValue(), 'update', $formFieldData);
        
        FormHelper::unsetSessionFormErrors();

        return $this->view->render(
            $response,
            'Admin/form.php',
            [
                'title' => 'Update ' . $this->mapper->getFormalTableName(),
                'formHtml' => $form->generate(),
                'primaryKey' => $primaryKeyValue,
                'navigationItems' => $this->navigationItems,
                'hideFocus' => true,
                'notice' => $this->getNotice(),
            ]
        );
    }
}
