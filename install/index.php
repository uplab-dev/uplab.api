<?php

use Bitrix\Main\ModuleManager;

class uplab_api extends CModule
{
    public function __construct()
    {
        $this->MODULE_ID = 'uplab.api';
        $this->MODULE_GROUP_RIGHTS = 'Y';

        $arModuleVersion = [];
        include __DIR__ . '/version.php';
        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];

        $this->MODULE_NAME = '[Uplab] ' . $this->MODULE_ID;
        $this->MODULE_DESCRIPTION = $this->MODULE_ID . '. Библиотеки для работы по API.';

        $this->PARTNER_NAME = 'Uplab. Digital & Design';
        $this->PARTNER_URI = 'https://uplab.ru';
    }

    public function DoInstall()
    {
        ModuleManager::registerModule($this->MODULE_ID);
        $this->InstallFiles();
    }

    public function DoUninstall()
    {
        ModuleManager::unRegisterModule($this->MODULE_ID);
        $this->UnInstallFiles();
    }

    function InstallFiles()
    {
        CopyDirFiles(
            $this->getPath() . '/install/bitrix/',
            $_SERVER["DOCUMENT_ROOT"] . '/bitrix/',
            true,
            true
        );
        return true;
    }

    function UnInstallFiles()
    {
        DeleteDirFilesEx(
            '/bitrix/admin/' . $this->MODULE_ID
        );

        DeleteDirFiles(
            $this->getPath() . '/install/bitrix/',
            $_SERVER["DOCUMENT_ROOT"] . '/bitrix/'
        );
    }

    protected function getPath()
    {
        return $_SERVER["DOCUMENT_ROOT"] . getLocalPath("modules/$this->MODULE_ID");
    }
}