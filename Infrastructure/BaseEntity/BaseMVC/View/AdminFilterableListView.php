<?php
declare(strict_types=1);

namespace Infrastructure\BaseEntity\BaseMVC\View;

use Infrastructure\BaseEntity\BaseMVC\View\Forms\FormHelper;
use Infrastructure\Database\DataMappers\ListViewMappers;
use Infrastructure\Database\Queries\QueryBuilder;
use Infrastructure\SlimAdmin;
use Psr\Container\ContainerInterface as Container;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

abstract class AdminFilterableListView extends AdminListView
{
    protected $initialSortColumn;
    protected $initialSortDirection;
    private $filterFieldName;
    protected $filterResetPath;

    public function __construct(Container $container, ListViewMappers $mapper, string $indexPath, ?string $initialSortColumn, string $routePrefix, string $filterFieldName, string $filterResetPath, ?string $initialSortDirection = 'ASC', string $template = 'Admin/Lists/listFilterable.php')
    {
        if (!in_array($initialSortDirection, ['ASC', 'DESC'])) {
            throw new \InvalidArgumentException("Invalid initial sort direction $initialSortDirection");
        }
        parent::__construct($container, $mapper, $indexPath, $routePrefix, $template);
        $this->initialSortColumn = $initialSortColumn;
        $this->initialSortDirection = $initialSortDirection;
        $this->filterFieldName = $filterFieldName;
        $this->filterResetPath = $filterResetPath;
    }

    public function routeIndexResetFilter(Request $request, Response $response, $args)
    {
        return $this->resetFilter($response);
    }

    /** same as parent except has filterForm */
    public function indexView(Response $response)
    {
        $listArray = $this->getListArray();
        $hasResults = count($listArray) > 0;
        return $this->view->render(
            $response,
            $this->template,
            [
                'title' => $this->mapper->getListViewTitle(),
                'headers' => $this->getHeaders($hasResults),
                'listArray' => $listArray,
                'insertLinkInfo' => $this->getInsertLinkInfo(),
                'filterForm' => $this->getFilterForm(),
                'errorMessage' => $this->getErrorMessage(),
                'navigationItems' => $this->navigationItems,
                'notice' => $this->getNotice(),
                'addBodyJs' => $this->addBodyJs,
                'footers' => $this->getFooters(),
            ]
        );
    }

    protected function getHeaderClass(string $headerName, bool $hasResults = true): string 
    {
        if (!$hasResults) {
            return '';
        }
        
        $initialSortClass = $this->initialSortDirection == 'ASC' ? 'sorttable_sorted' : 'sorttable_sorted_reverse';
        switch ($headerName) {
            case $this->initialSortColumn:
                return $initialSortClass;
            case self::DELETE_COLUMN_TEXT:
                return 'sorttable_nosort';
            default:
                return '';
        }
    }

    public function getFilterFieldName(): string 
    {
        return $this->filterFieldName;
    }

    public function getFilterKey(): string 
    {
        return $this->getFilterFieldName();
    }

    public function getSessionFilterFieldKey(): string
    {
        return $this->getFilterFieldName();
    }

    protected function getFilterForm(): string 
    {
        $ffAction = $this->indexPath;
        
        $ffError = (mb_strlen(FormHelper::getFieldError($this->filterFieldName)) > 0) ? '<span class="ffErrorMsg">'.FormHelper::getFieldError($this->filterFieldName).'</span>' : '';
        FormHelper::unsetSessionFormErrors();
        $isFiltered = $this->getFilterFieldValue() != '';
        $ffReset = ($isFiltered) ? '<a href="'.$this->filterResetPath.'">reset</a>' : '';
        $filterValue = htmlspecialchars($this->getFilterFieldValue(), ENT_QUOTES|ENT_HTML5);
        $csrfFields = FormHelper::getCsrfFieldsString($this->csrf->getTokenNameKey(), $this->csrf->getTokenName(), $this->csrf->getTokenValueKey(), $this->csrf->getTokenValue());
        $filterOpsList = QueryBuilder::getWhereOperatorsText();

        $filterForm = <<< EOT
<form name="filter" method="post" style="display: inline" action="$ffAction">
    SELECT WHERE
    <input type="text" name="$this->filterFieldName" value="$filterValue" size="58" maxlength="500" placeholder="field1:op:val1[,field2...] op in [$filterOpsList]" required>
    <input type="submit" value="Filter">
    $ffError
    $ffReset
    $csrfFields
</form>
EOT;
        return $filterForm;
    }

    protected function getFilterColumnsInfo(): ?array 
    {
        return isset($_SESSION[SlimAdmin::SESSION_KEY_ADMIN_LIST_VIEW_FILTER][$this->filterFieldName][self::SESSION_FILTER_COLUMNS_KEY]) ? $_SESSION[SlimAdmin::SESSION_KEY_ADMIN_LIST_VIEW_FILTER][$this->filterFieldName][self::SESSION_FILTER_COLUMNS_KEY] : null;
    }

    /** either session value or empty string */
    protected function getFilterFieldValue(): string
    {
        return isset($_SESSION[SlimAdmin::SESSION_KEY_ADMIN_LIST_VIEW_FILTER][$this->filterFieldName][self::SESSION_FILTER_VALUE_KEY]) ? $_SESSION[SlimAdmin::SESSION_KEY_ADMIN_LIST_VIEW_FILTER][$this->filterFieldName][self::SESSION_FILTER_VALUE_KEY] : '';
    }
    
    /** unset session filter vars */
    public function resetFilter(Response $response)
    {
        if (isset($_SESSION[SlimAdmin::SESSION_KEY_ADMIN_LIST_VIEW_FILTER][$this->filterFieldName][self::SESSION_FILTER_COLUMNS_KEY])) {
            unset($_SESSION[SlimAdmin::SESSION_KEY_ADMIN_LIST_VIEW_FILTER][$this->filterFieldName][self::SESSION_FILTER_COLUMNS_KEY]);
        }

        if (isset($_SESSION[SlimAdmin::SESSION_KEY_ADMIN_LIST_VIEW_FILTER][$this->filterFieldName][self::SESSION_FILTER_VALUE_KEY])) {
            unset($_SESSION[SlimAdmin::SESSION_KEY_ADMIN_LIST_VIEW_FILTER][$this->filterFieldName][self::SESSION_FILTER_VALUE_KEY]);
        }

        // redirect to the clean url
        return $response
            ->withHeader('Location', $this->container->get('routeParser')->urlFor($this->indexPath))
            ->withStatus(302);
    }
}
