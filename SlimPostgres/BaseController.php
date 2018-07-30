<?php
declare(strict_types=1);

namespace SlimPostgres;

use SlimPostgres\App;
use SlimPostgres\Database\Queries\QueryBuilder;
use SlimPostgres\Forms\FormHelper;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;
use SlimPostgres\AdminListView;

abstract class BaseController
{
    protected $container; // dependency injection container
    protected $requestInput; // user input data

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function __get($name)
    {
        return $this->container->{$name};
    }

    protected function setRequestInputNew(Request $request, array $booleanFieldNames = [])
    {
        $this->requestInput = [];
        foreach ($request->getParsedBody() as $key => $value) {
            if (is_string($value) && $this->settings['trimAllUserInput']) {
                $this->requestInput[$key] = trim($value);
            } elseif (is_array($value)) {
                // go 1 level deeper only
                foreach ($value as $deeperKey => $deeperValue) {
                    if (is_string($deeperValue) && $this->settings['trimAllUserInput']) {
                        $this->requestInput[$key][$deeperKey] = trim($deeperValue);
                    }
                }
            }
        }

        if (count($booleanFieldNames) > 0) {
            $this->addBooleanFieldsToRequestInput($booleanFieldNames);
        }
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

    // give them the same boolean value as in the database
    private function addBooleanFieldToRequestInputNew(string $booleanFieldName)
    {
        if (!isset($this->requestInput[$booleanFieldName])) {
            $this->requestInput[$booleanFieldName] = $this->database::BOOLEAN_FALSE;
        } elseif ($this->requestInput[$booleanFieldName] == 'on') {
            $this->requestInput[$booleanFieldName] = $this->database::BOOLEAN_TRUE;
        } else {
            throw new \Exception('Invalid value for boolean input var '.$booleanFieldName.': '.$this->requestInput[$booleanFieldName]);
        }
    }

    /** called by children for posted filter form entry methods */
    protected function setIndexFilter(Request $request, Response $response, $args, array $listViewColumns, AdminListView $view)
    {
        $this->setRequestInput($request);

        if (!isset($_SESSION[App::SESSION_KEY_REQUEST_INPUT][$view->getSessionFilterFieldKey()])) {
            throw new \Exception("session filter input must be set");
        }

        if (null === $filterColumnsInfo = $this->getFilterColumns($view->getSessionFilterFieldKey(), $listViewColumns)) {
            // redisplay form with error
            FormHelper::setFieldErrors([$view->getSessionFilterFieldKey() => 'Not Entered']);
            return $view->indexView($response);
        } else {
            /** store in session to remember filtration */
            $_SESSION[App::SESSION_KEY_ADMIN_LIST_VIEW_FILTER][$view->getFilterKey()][$view::SESSION_FILTER_COLUMNS_KEY] = $filterColumnsInfo;

            /** store in session so form field can be repopulated */
            $_SESSION[App::SESSION_KEY_ADMIN_LIST_VIEW_FILTER][$view->getFilterKey()][$view::SESSION_FILTER_VALUE_KEY] = $_SESSION[App::SESSION_KEY_REQUEST_INPUT][$view->getSessionFilterFieldKey()];

            FormHelper::unsetFormSessionVars();

            return $view->indexView($response);
        }
    }

    // parse the where filter field
    protected function getFilterColumns(string $filterFieldName, array $listViewColumns): ?array
    {
        $filterColumnsInfo = [];
        $filterParts = explode(",", $_SESSION[App::SESSION_KEY_REQUEST_INPUT][$filterFieldName]);
        if (mb_strlen($filterParts[0]) == 0) {
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

    /** 
     * @param string $emailTo must be in $settings['emails'] array or error will be inserted to system events
     * @param string $mainBody
     * @param bool $addEventLogStatement defaults true, if true adds 'See event log for details' after $mainBody
     * @param bool $throwExceptionOnError defaults false, if true exception is thrown if no match for $emailTo
     */
    protected function sendEventNotificationEmail(string $emailTo, string $mainBody, bool $addEventLogStatement = true, bool $throwExceptionOnError = false)
    {
        if ($emailTo !== null) {
            $settings = $this->container->get('settings');
            if (isset($settings['emails'][$emailTo])) {
                $emailBody = $mainBody;
                if ($addEventLogStatement) {
                    $emailBody .= PHP_EOL . "See event log for details.";
                }
                $this->mailer->send(
                    $_SERVER['SERVER_NAME'] . " Event",
                    $emailBody,
                    [$settings['emails'][$emailTo]]
                );
            } else {
                $this->systemEvents->insertError("Email Not Found", (int) $this->authentication->getAdministratorId(), $emailTo);
                if ($throwExceptionOnError) {
                    throw new \InvalidArgumentException("Email Not Found: $emailTo");
                }
            }
        }
    }
}
