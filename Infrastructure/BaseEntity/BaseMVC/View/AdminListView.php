<?php
declare(strict_types=1);

namespace Infrastructure\BaseEntity\BaseMVC\View;

use Infrastructure\SlimAdmin;
use Infrastructure\Database\Queries\QueryBuilder;
use Infrastructure\Database\DataMappers\ListViewMappers;
use Infrastructure\BaseEntity\BaseMVC\View\Forms\FormHelper;
use Psr\Container\ContainerInterface as Container;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

abstract class AdminListView extends AdminView
{
    protected $mapper; /** list view mapper */
    protected $template;
    protected $indexPath;
    protected $routePrefix;
    protected $insertsAuthorized; /** bool */
    protected $updatesAuthorized; /** bool */
    protected $deletesAuthorized; /** bool */
    protected $addBodyJs; /** string like <script type="text/javascript">fncall();</script> */
    
    const DELETE_COLUMN_TEXT = 'X'; 
    const SESSION_FILTER_COLUMNS_KEY = 'columns';
    const SESSION_FILTER_VALUE_KEY = 'value';

    abstract protected function getListArray(): array;
    abstract protected function getResource(string $which): string;
    abstract protected function getHeaderClass(string $headerName, bool $hasResults = true): string;

    public function __construct(Container $container, ListViewMappers $mapper, string $indexPath, string $routePrefix, string $template = 'Admin/Lists/list.php', ?string $addBodyJs = null)
    {
        parent::__construct($container);
        $this->mapper = $mapper;
        $this->template = $template;
        $this->indexPath = $indexPath;
        $this->routePrefix = $routePrefix;
        $this->addBodyJs = $addBodyJs;

        $this->setInsertsPermitted();
        $this->setUpdatesPermitted();
        $this->setDeletesPermitted();
    }

    protected function setInsertsPermitted()
    {
        $this->insertsAuthorized = $this->authorization->isAuthorized($this->getResource('insert'));
    }

    protected function setUpdatesPermitted()
    {
        $this->updatesAuthorized = $this->authorization->isAuthorized($this->getResource('update'));
    }

    protected function setDeletesPermitted()
    {
        $this->deletesAuthorized = $this->authorization->isAuthorized($this->getResource('delete'));
    }

    public function routeIndex(Request $request, Response $response, $args)
    {
        return $this->indexView($response);
    }

    /** children can overwrite */
    protected function getErrorMessage(): ?string 
    {
        return null;
    }

    public function setAddBodyJs(?string $addBodyJs = null) 
    {
        $this->addBodyJs = $addBodyJs;
    }

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
                'errorMessage' => $this->getErrorMessage(),
                'navigationItems' => $this->navigationItems,
                'notice' => $this->getNotice(),
                'addBodyJs' => $this->addBodyJs,
                'footers' => $this->getFooters(),
            ]
        );
    }

    /**
     * adds the delete header column if required
     * note that the getColumnNames fn is only defined for table mappers
     * currently, entity mappers are overriding this fn
     * that should be changed so that getColumnNames is required for the ListViewMappers interface,
     * and implemented in entity mappers, even if they override
     */
    protected function getHeaders(bool $hasResults = true): array 
    {
        $headers = [];
        $headerNames = $this->deletesAuthorized ? array_merge($this->mapper->getColumnNames(), [self::DELETE_COLUMN_TEXT]) : $this->mapper->getColumnNames();
        foreach ($headerNames as $name) {
            $headers[] = [
                'class' => $this->getHeaderClass($name, $hasResults),
                'text' => $name,
            ];
        }

        return $headers;
    }

    protected function getFooters(): ?array
    {
        return null;
    }

    protected function getInsertLinkInfo()
    {
        return $this->insertsAuthorized ? [
            'text' => $this->mapper->getInsertTitle(), 
            'href' => $this->container->get('routeParser')->urlFor(SlimAdmin::getRouteName(true, $this->routePrefix, 'insert')),
        ] : null;
    }
}
