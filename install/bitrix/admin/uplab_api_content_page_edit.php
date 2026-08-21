<?php
$file = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/uplab.api/admin/uplab_api_content_page_edit.php';
if (file_exists($file)) {
    include_once $file;
} else {
    include_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/uplab.api/admin/uplab_api_content_page_edit.php';
}