<?php 
declare(strict_types=1);

// ensure the content object is set
if (!isset($content)) {
    throw new \Exception("Template content object not set");
}
?>
<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8"/>
        <title><?= $content->getTitle() ?></title>
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <?= $content->getMetaKeywords() ?>
        <?= $content->getMetaDescription() ?>
        <?= $content->getMetaRobots() ?>
        <?= $content->getHeadMeta() ?>
        <?= $content->getHeadLink() ?>
        <?= $content->getHeadCss() ?>
        <?= $content->getHeadJs() ?>
    </head>
    <body>
        <?= $content->getBody() ?>
        <?= $content->getBodyJs() ?>
    </body>
</html>