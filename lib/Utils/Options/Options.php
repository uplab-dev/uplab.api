<?php

namespace Uplab\Api\Utils\Options;

class Options extends OptionsBase
{
	public function __construct($filePath = false, $tabsSettings = false, $moduleId = null)
	{
		//$tabsSettings[] = $this->getSettingsConstantsTab();
		parent::__construct($filePath, $tabsSettings, $moduleId);
	}

	public function onPostEvents()
	{
		parent::onPostEvents();
		$this->updateModuleFiles();
	}
}