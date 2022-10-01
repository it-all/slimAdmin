<?php
declare(strict_types=1);
use Templates\Frontend\HomeContent;
$noticeContent = $notice ?? null;
$content = new HomeContent($businessName, $noticeContent);
require TEMPLATES_PATH . 'base.php';
