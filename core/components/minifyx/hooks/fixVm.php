<?php
if ($MinifyX->isCss()) {
    $data = preg_replace('#vm (ax|in)#', 'vm$1', $MinifyX->getContent());
    $MinifyX->setContent($data);
}