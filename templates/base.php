<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <title><?= $title ?></title>
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
        // output meta keywords and meta description on public pages if they are set
        if (isset($pageType) && $pageType == 'public') {
            if (isset($metaKeywords)) {
                echo '<meta name="keywords" content="' . $metaKeywords . '">';
            }
            if (isset($metaDescription)) {
                echo '<meta name="description" content="' . $metaDescription . '">';
            }
        } else {
            echo '<meta name="robots" content="noindex, nofollow">';
        }

        // output other meta tags if they are set
        if (isset($htmlHeadMeta)) { echo $htmlHeadMeta; }

        // output head css if set
        if (isset($htmlHeadCss)) { echo $htmlHeadCss; }

        // output head js if set
        if (isset($htmlHeadJs)) { echo $htmlHeadJs; }

    ?>
</head>
<body><?php

    // output body html if set
    if (isset($htmlBodyContent)) { echo $htmlBodyContent; }

    // output body js if set
    if (isset($htmlBodyJs)) { echo $htmlBodyJs; }

?></body>
</html>