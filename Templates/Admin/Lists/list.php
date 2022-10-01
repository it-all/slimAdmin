<?php
declare(strict_types=1);

use Templates\Admin\AdminContent;
use Templates\Admin\Lists\AdminList;

$list = new AdminList($title, $listArray, $headers);

$content = new AdminContent($list->getMainHtml(), $list->getHeadCss(), $list->getBodyJs(), $notice, $isLive, $title, $domainName, $businessName, $businessDba, $authentication, $navigationItems, $routeParser);

require TEMPLATES_PATH . '/base.php';
