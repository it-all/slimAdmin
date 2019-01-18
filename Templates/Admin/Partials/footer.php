<?php
declare(strict_types=1);

$footer = '<footer id="adminPageFooter">'.$businessName;
if (!$isLive) {
    $footer .= '<span style="color: grey;">{LOCAL}</span>';
}
$footer .= '</footer>';
