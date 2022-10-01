<?php
declare(strict_types=1);

use Templates\Admin\AdminLoginContent;

$debugString = $debug ?? null;
$navigationItems = null;

$content = new AdminLoginContent($formHtml, $focusFieldId, $notice, $isLive, $title, $domainName, $businessName, $businessDba, $authentication, $navigationItems, $routeParser, $debugString);

require TEMPLATES_PATH . '/base.php';
