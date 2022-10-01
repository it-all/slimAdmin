<?php
declare(strict_types=1);

namespace Infrastructure\BaseEntity\DatabaseTable\View;

use Infrastructure\Database\DataMappers\TableMapper;
use Psr\Container\ContainerInterface as Container;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Infrastructure\BaseEntity\BaseMVC\View\AdminView;
use Infrastructure\SlimAdmin;
use Infrastructure\BaseEntity\BaseMVC\View\Forms\FormHelper;

class DatabaseTableInsertView extends AdminView
{
    private $tableName;
    protected $mapper;

    public function __construct(Container $container, string $tableName)
    {
        $this->tableName = $tableName;
        $this->mapper = new TableMapper($this->tableName);
        parent::__construct($container);
    }

    /** this can be called for both the initial get and the posted form if errors exist (from controller) */
    public function insertView(Request $request, Response $response, $args)
    {
        $formFieldData = ($request->getMethod() === 'POST' && isset($args[SlimAdmin::USER_INPUT_KEY])) ? $args[SlimAdmin::USER_INPUT_KEY] : null;

        $form = new DatabaseTableForm($this->mapper, $this->container->get('routeParser')->urlFor(ROUTE_DATABASE_TABLES_INSERT_POST, [ROUTEARG_DATABASE_TABLE_NAME => $this->tableName]), $this->csrf->getTokenNameKey(), $this->csrf->getTokenName(), $this->csrf->getTokenValueKey(), $this->csrf->getTokenValue(), 'insert', $formFieldData);
        
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
