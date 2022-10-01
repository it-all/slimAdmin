<?php
declare(strict_types=1);

namespace Infrastructure\BaseEntity\BaseMVC\Controller;

use Infrastructure\BaseEntity\BaseMVC\View\AdminFilterableListView;
use Psr\Container\ContainerInterface as Container;
use Psr\Http\Message\ServerRequestInterface as Request;
use Infrastructure\SlimAdmin;
use Infrastructure\Database\Queries\QueryBuilder;
use Infrastructure\BaseEntity\BaseMVC\View\Forms\FormHelper;
use Infrastructure\BaseEntity\BaseMVC\View\AdminListView;

abstract class ListViewAdminController extends AdminController
{
    public function __construct(Container $container)
    {
        parent::__construct($container);
    }

    /** called by children for posted filter form entry methods */
    protected function setIndexFilter(Request $request, array $listViewColumns, AdminFilterableListView $view)
    {
        $this->setRequestInput($request, [$view->getSessionFilterFieldKey()]);

        if (!isset($this->requestInput[$view->getSessionFilterFieldKey()])) {
            throw new \Exception("session filter input must be set");
        }

        $this->storeFilterFieldValueInSession($view);

        /** if there is an error in the filter field getFilterColumns will set the form error and return null */
        if (null !== $filterColumnsInfo = $this->getFilterColumns($view->getSessionFilterFieldKey(), $listViewColumns)) {
            $this->storeFilterColumnsInfoInSession($filterColumnsInfo, $view);
        }
    }

    private function storeFilterColumnsInfoInSession(array $filterColumnsInfo, AdminFilterableListView $view)
    {
        $_SESSION[SlimAdmin::SESSION_KEY_ADMIN_LIST_VIEW_FILTER][$view->getFilterKey()][$view::SESSION_FILTER_COLUMNS_KEY] = $filterColumnsInfo;
    }

    private function storeFilterFieldValueInSession(AdminFilterableListView $view) 
    {
        /** store entered field value in session so form field can be repopulated */
        $_SESSION[SlimAdmin::SESSION_KEY_ADMIN_LIST_VIEW_FILTER][$view->getFilterKey()][$view::SESSION_FILTER_VALUE_KEY] = $this->requestInput[$view->getSessionFilterFieldKey()];
    }

    // parse the where filter field into [ column name => [operators, values] ] 
    protected function getFilterColumns(string $filterFieldName, array $listViewColumns): ?array
    {
        $filterColumnsInfo = [];
        $filterParts = explode(",", $this->requestInput[$filterFieldName]);
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
                if (isset($listViewColumns[$columnName])) {
                    $columnNameSql = $listViewColumns[$columnName];
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
                    $filterColumnsInfo[$columnNameSql] = [];
                    $filterColumnsInfo[$columnNameSql]['operators'] = [];
                    $filterColumnsInfo[$columnNameSql]['values'] = [];
                }
                $filterColumnsInfo[$columnNameSql]['operators'][] = $whereOperator;
                $filterColumnsInfo[$columnNameSql]['values'][] = $whereValue;
            }
        }

        return $filterColumnsInfo;
    }    
}
