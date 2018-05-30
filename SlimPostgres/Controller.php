<?php
declare(strict_types=1);

namespace SlimPostgres;

use SlimPostgres\Database\Queries\QueryBuilder;
use SlimPostgres\Forms\FormHelper;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

abstract class Controller
{
    protected $container; // dependency injection container

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function __get($name)
    {
        return $this->container->{$name};
    }

    /** may want a config var bool trimAllInputs */
    protected function setRequestInput(Request $request)
    {
        $_SESSION[App::SESSION_KEYS['requestInput']] = [];
        foreach ($request->getParsedBody() as $key => $value) {
            $_SESSION[App::SESSION_KEYS['requestInput']][$key] = ($this->settings['trimAllUserInput']) ? trim($value) : $value;
        }
    }

    protected function setIndexFilter(Request $request, Response $response, $args, array $listViewColumns, string $redirectRoute, ListView $view)
    {
        $this->setRequestInput($request);

        if (!isset($_SESSION[App::SESSION_KEYS['requestInput']][$view->getSessionFilterFieldKey()])) {
            throw new \Exception("session filter input must be set");
        }

        if (!$filterColumnsInfo = $this->getFilterColumns($view->getSessionFilterFieldKey(), $listViewColumns)) {
            // redisplay form with error
            return $view->indexView($response);
        } else {
            $_SESSION[$view->getSessionFilterColumnsKey()] = $filterColumnsInfo;
            $_SESSION[$view->getSessionFilterValueKey()] = $_SESSION[App::SESSION_KEYS['requestInput']][$view->getSessionFilterFieldKey()];
            FormHelper::unsetSessionVars();
            return $response->withRedirect($this->router->pathFor($redirectRoute));
        }
    }

    // parse the where filter field
    protected function getFilterColumns(string $filterFieldName, array $listViewColumns): ?array
    {
        $filterColumnsInfo = [];
        $filterParts = explode(",", $_SESSION[App::SESSION_KEYS['requestInput']][$filterFieldName]);
        if (strlen($filterParts[0]) == 0) {
            FormHelper::setFieldErrors([$filterFieldName => 'Not Entered']);
            return null;
        } else {

            foreach ($filterParts as $whereFieldOperatorValue) {
                //field:operator:value
                $whereFieldOperatorValueParts = explode(":", $whereFieldOperatorValue);
                if (count($whereFieldOperatorValueParts) != 3) {
                    FormHelper::setFieldErrors([$filterFieldName => 'Malformed']);
                    return null;
                }
                $columnName = trim($whereFieldOperatorValueParts[0]);
                $whereOperator = strtoupper(trim($whereFieldOperatorValueParts[1]));
                $whereValue = trim($whereFieldOperatorValueParts[2]);

                // validate the column name
                if (isset($listViewColumns[strtolower($columnName)])) {
                    $columnNameSql = $listViewColumns[strtolower($columnName)];
                } else {
                    FormHelper::setFieldErrors([$filterFieldName => "$columnName not found"]);
                    return null;
                }

                // validate the operator
                if (!QueryBuilder::validateWhereOperator($whereOperator)) {
                    FormHelper::setFieldErrors([$filterFieldName => "Invalid Operator $whereOperator"]);
                    return null;
                }

                // null value only valid with IS and IS NOT operators
                if (strtolower($whereValue) == 'null') {
                    if ($whereOperator != 'IS' && $whereOperator != 'IS NOT') {
                        FormHelper::setFieldErrors([$filterFieldName => "Mismatched null, $whereOperator"]);
                        return null;
                    }
                    $whereValue = null;
                }

                if (!isset($filterColumnsInfo[$columnNameSql])) {
                    $filterColumnsInfo[$columnNameSql] = [
                        'operators' => [$whereOperator],
                        'values' => [$whereValue]
                    ];
                } else {
                    $filterColumnsInfo[$columnNameSql]['operators'][] = $whereOperator;
                    $filterColumnsInfo[$columnNameSql]['values'][] = $whereValue;
                }
            }
        }

        return $filterColumnsInfo;
    }
}
