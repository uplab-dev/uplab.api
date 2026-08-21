<?php

$path = '/modules/uplab.api/admin/uplab_api_docs.php';
$local = $_SERVER['DOCUMENT_ROOT'] . '/local' . $path;

include_once(file_exists($local) ? $local : $_SERVER['DOCUMENT_ROOT'] . '/bitrix' . $path);
