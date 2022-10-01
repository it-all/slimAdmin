<?php
declare(strict_types=1);

namespace Infrastructure\BaseEntity\BaseMVC\View;

use Psr\Container\ContainerInterface as Container;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

/** a simple list view report, still sortable */
abstract class AdminSimpleListView extends AdminView
{
    protected $template;
    protected $initialSortColumn;
    protected $initialSortDirection;

    abstract protected function getListArray(): array;
    abstract protected function getHeaders(): array;
    abstract protected function getTitle(): string;

    public function __construct(Container $container, ?string $initialSortColumn = null, ?string $initialSortDirection = 'ASC', ?string $template = 'Admin/Lists/list.php')
    {
        parent::__construct($container);
        $this->template = $template;
        $this->initialSortColumn = $initialSortColumn;
        $this->initialSortDirection = $initialSortDirection;
    }

    public function routeIndex(Request $request, Response $response, $args)
    {
        $listArray = $this->getListArray();
        return $this->view->render(
            $response,
            $this->template,
            [
                'title' => $this->getTitle(),
                'headers' => $this->getHeaders(),
                'listArray' => $listArray,
                'navigationItems' => $this->navigationItems,
                'notice' => $this->getNotice(),
            ]
        );
    }

    protected function getHeaderClass(string $headerName, bool $hasResults = true): string 
    {
        if (!$hasResults) {
            return '';
        }
        
        $initialSortClass = $this->initialSortDirection === 'ASC' ? 'sorttable_sorted' : 'sorttable_sorted_reverse';
        switch ($headerName) {
            case $this->initialSortColumn:
                return $initialSortClass;
            default:
                return '';
        }
    }
}
