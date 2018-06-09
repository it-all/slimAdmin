<?php
declare(strict_types=1);

$htmlHeadCss = '<link href="/css/adminFlexSimpleNotLoggedIn.css" rel="stylesheet" type="text/css">';
require 'partials/header.php';
require 'partials/footer.php';
$htmlBodyContent = $header;
$formHtml = $form->generate();
$htmlBodyContent .= <<< EOT
    <main>
        <div id="simpleContainer">
            <h1>Login</h1>
            <h5>Authorized for use by $businessName employees and associates only.</h5>
            $formHtml
        </div>
    </main>
EOT;
$htmlBodyContent .= $footer;

$htmlBodyJs = '<script type="text/javascript" src="/js/uiHelper.js"></script>';
$focusFieldId = $form->getFocusFieldId();
if (mb_strlen($focusFieldId) > 0) {
    $htmlBodyJs = <<< EOT
<script type="text/javascript">
    window.onload = document.getElementById('$focusFieldId').focus();
</script>    
EOT;
}
require APPLICATION_ROOT_DIRECTORY . '/templates/base.php';
