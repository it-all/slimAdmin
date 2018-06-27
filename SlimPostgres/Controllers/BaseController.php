<?php
declare(strict_types=1);

namespace SlimPostgres\Controllers;

use SlimPostgres\App;
use SlimPostgres\Database\Queries\QueryBuilder;
use SlimPostgres\UserInterface\Forms\FormHelper;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;
use SlimPostgres\UserInterface\AdminListView;

abstract class BaseController
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

    protected function setRequestInput(Request $request, array $booleanFieldNames = [])
    {
        $_SESSION[App::SESSION_KEY_REQUEST_INPUT] = [];
        foreach ($request->getParsedBody() as $key => $value) {
            if (is_string($value) && $this->settings['trimAllUserInput']) {
                $_SESSION[App::SESSION_KEY_REQUEST_INPUT][$key] = trim($value);
            } elseif (is_array($value)) {
                // go 1 level deeper only
                foreach ($value as $deeperKey => $deeperValue) {
                    if (is_string($deeperValue) && $this->settings['trimAllUserInput']) {
                        $_SESSION[App::SESSION_KEY_REQUEST_INPUT][$key][$deeperKey] = trim($deeperValue);
                    }
                }
            }
        }

        if (count($booleanFieldNames) > 0) {
            $this->addBooleanFieldsToRequestInput($booleanFieldNames);
        }
    }

    // since a boolean field (ie checkbox) with a false value (ie unchecked) does not appear in the post, they must be added to the input array
    private function addBooleanFieldsToRequestInput(array $booleanFieldNames)
    {
        foreach ($booleanFieldNames as $fieldName) {
            $this->addBooleanFieldToRequestInput($fieldName);
        }
    }

    // give them the same boolean value as in the database
    private function addBooleanFieldToRequestInput(string $booleanFieldName)
    {
        if (!isset($_SESSION[App::SESSION_KEY_REQUEST_INPUT][$booleanFieldName])) {
            $_SESSION[App::SESSION_KEY_REQUEST_INPUT][$booleanFieldName] = $this->database::BOOLEAN_FALSE;
        } elseif ($_SESSION[App::SESSION_KEY_REQUEST_INPUT][$booleanFieldName] == 'on') {
            $_SESSION[App::SESSION_KEY_REQUEST_INPUT][$booleanFieldName] = $this->database::BOOLEAN_TRUE;
        } else {
            throw new \Exception('Invalid value for boolean session var '.$booleanFieldName.': '.$_SESSION[App::SESSION_KEY_REQUEST_INPUT][$booleanFieldName]);
        }
    }

    protected function setIndexFilter(Request $request, Response $response, $args, array $listViewColumns, string $redirectRoute, AdminListView $view)
    {
        $this->setRequestInput($request);

        if (!isset($_SESSION[App::SESSION_KEY_REQUEST_INPUT][$view->getSessionFilterFieldKey()])) {
            throw new \Exception("session filter input must be set");
        }

        if (!$filterColumnsInfo = $this->getFilterColumns($view->getSessionFilterFieldKey(), $listViewColumns)) {
            // redisplay form with error
            return $view->indexView($response);
        } else {
            $_SESSION[$view->getSessionFilterColumnsKey()] = $filterColumnsInfo;
            $_SESSION[$view->getSessionFilterValueKey()] = $_SESSION[App::SESSION_KEY_REQUEST_INPUT][$view->getSessionFilterFieldKey()];
            FormHelper::unsetFormSessionVars();
            return $response->withRedirect($this->router->pathFor($redirectRoute));
        }
    }

    // parse the where filter field
    protected function getFilterColumns(string $filterFieldName, array $listViewColumns): ?array
    {
        $filterColumnsInfo = [];
        $filterParts = explode(",", $_SESSION[App::SESSION_KEY_REQUEST_INPUT][$filterFieldName]);
        if (mb_strlen($filterParts[0]) == 0) {
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
                    FormHelper::setFieldErrors([$filterFieldName => "$columnName column not found"]);
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
