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
use Infrastructure\BaseEntity\BaseMVC\View\ResponseUtilities;

class DatabaseTableUpdateView extends AdminView
{
    use ResponseUtilities;

    private $tableName;
    private $primaryKey;
    protected $mapper;

    public function __construct(Container $container, string $tableName, $primaryKey)
    {
        $this->tableName = $tableName;
        $this->primaryKey = $primaryKey;
        $this->mapper = new TableMapper($this->tableName);
        parent::__construct($container);
    }

    /** this can be called for both the initial get and the posted form if errors exist (from controller) */
    public function updateView(Request $request, Response $response, $args)
    {
        // make sure there is a record for the mapper
        if (null === $record = $this->mapper->selectForPrimaryKey($this->primaryKey)) {
            $this->events->insertWarning(EVENT_QUERY_NO_RESULTS, [$this->mapper->getPrimaryKeyColumnName() => $this->primaryKey, 'table' => $this->tableName]);
            SlimAdmin::addAdminNotice("Record $this->primaryKey Not Found", 'failure');
            return $response
                ->withHeader('Location', $this->container->get('routeParser')->urlFor(ROUTE_DATABASE_TABLES, [ROUTEARG_DATABASE_TABLE_NAME => $this->tableName]))
                ->withStatus(302);
        }

        $formFieldData = ($request->getMethod() === 'PUT' && isset($args[SlimAdmin::USER_INPUT_KEY])) ? $args[SlimAdmin::USER_INPUT_KEY] : $record;

        $form = new DatabaseTableForm($this->mapper, $this->container->get('routeParser')->urlFor(ROUTE_DATABASE_TABLES_UPDATE_PUT, [ROUTEARG_DATABASE_TABLE_NAME => $this->tableName, ROUTEARG_PRIMARY_KEY => $this->primaryKey]), $this->csrf->getTokenNameKey(), $this->csrf->getTokenName(), $this->csrf->getTokenValueKey(), $this->csrf->getTokenValue(), 'update', $formFieldData, null, false);

        FormHelper::unsetSessionFormErrors();

        return $this->view->render(
            $response,
            'Admin/form.php',
            [
                'title' => 'Update '. $this->mapper->getFormalTableName(),
                'formHtml' => $form->generate(),
                'focusFieldId' => $form->getFocusFieldId(),
                'navigationItems' => $this->navigationItems,
                'notice' => $this->getNotice(),
            ]
        );
    }
}
