<?php
if ($MinifyX->isCss()) {
    $style = '<style type="text/css">' . $MinifyX->getContent() . '</style>';
    $modx->regClientCSS($style);
    // Switch off file registration
    $MinifyX->setFilename('');
}