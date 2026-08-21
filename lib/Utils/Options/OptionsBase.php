<?php

namespace Uplab\Api\Utils\Options;

use Bitrix\Main\Web\Uri;
use CAdminTabControl;
use CControllerClient;
use Exception;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Application;
use Bitrix\Main\Localization\Loc;
use Uplab\Api\Main;

class OptionsBase
{
	public string $moduleId = "";
	public $modulePrefix;
	public $tabsSettings = [];

	public function __construct($filePath = false, $tabsSettings = false, $moduleId = null)
	{
		$this->moduleId = empty($moduleId) ? Main::MODULE_ID : $moduleId;

		if (empty($filePath)) {
			$filePath =
					Application::getDocumentRoot() .
					getLocalPath('modules/' . $this->moduleId . '/options.php');
		}

		Loc::loadMessages($_SERVER["DOCUMENT_ROOT"] . BX_ROOT . "/modules/main/options.php");
		Loc::loadMessages($filePath);

		$this->modulePrefix = str_replace(".", "_", $this->moduleId);

		if ($tabsSettings !== false) {
			$this->tabsSettings = $tabsSettings;
		} else {
			$this->prepareTabs();
		}

		$this->postData();
	}

	public function updateModuleFiles()
	{
		$className = '\\' . $this->modulePrefix;
		/** @noinspection PhpIncludeInspection */
		if (!class_exists($className)) {
			/** @noinspection PhpIncludeInspection */
			include $_SERVER["DOCUMENT_ROOT"] .
					getLocalPath("modules/{$this->moduleId}") .
					"/install/index.php";
		}
		try {
			$module = new $className();
			if (is_callable([$module, "installFiles"])) {
				/** @noinspection PhpUndefinedMethodInspection */
				$module->installFiles();
			}
		} catch (Exception $e) {
		}
	}

	public function onPostEvents()
	{
		// сбрасываем кеш, помеченный тегом модуля (например, это кеш настроек)
		$cache = Application::getInstance()->getTaggedCache();
		$cache->clearByTag($this->moduleId);
	}

	public function getTabsArray()
	{
		return [];
	}

	private function drawSerializedRow($option, &$arControllerOption)
	{
		$type = $option[3];
		$disabled = isset($option[4]) && $option[4] == "Y" ? " disabled " : "";

		$fieldId = $this->moduleId . "_" . randString(6);

		$val = Option::get($this->moduleId, $option[0]);
		if ($val === "") {
			$val = $option[2];
		}
        $val = json_encode(
            unserialize($val, ['allowed_classes' => false]),
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );
		?>
		<tr>
			<td class="adm-detail-valign-top" width="50%">
				<label for="<?= $fieldId ?>"><?= $option[1] ?></label>
			</td>
			<td>
				<textarea rows="<?= $type[1] ?>"
						  cols="<?= $type[2] ?>"
						  name="<?= htmlspecialcharsbx($option[0]) ?>"
						  id="<?= $fieldId ?>"
					<?php
					if (isset($arControllerOption[$option[0]])) {
						echo " disabled title=\"" . Loc::getMessage("MAIN_ADMIN_SET_CONTROLLER_ALT") . "\"";
					}
					echo " {$disabled} ";
					?>><?= htmlspecialcharsbx($val) ?></textarea>
			</td>
		</tr>
		<?php
	}

	public function drawTabOptions($optionsArray): void
	{
		$arControllerOption = CControllerClient::GetInstalledOptions($this->moduleId);
		foreach ($optionsArray as $option) {
			if (!$option) {
				continue;
			}
			if (isset($option["ext_data"]) && $option["ext_data"]["is_serialized"] === true) {
				$this->drawSerializedRow($option, $arControllerOption);
			} elseif (!empty($option["print_row"]) && is_callable($option["print_row"])) {
				if (!$option["print_row_no_tr"]) {
					echo "<tr><td colspan='2'>";
				}
				$option["print_row"]();
				if (!$option["print_row_no_tr"]) {
					echo "</td></tr>";
				}
			} else {
				__AdmSettingsDrawRow($this->moduleId, $option);
			}
		}
	}

	public function drawOptionsForm()
	{
		global $APPLICATION;

		if (empty($this->tabsSettings)) {
			return;
		}

		$tabControl = new CAdminTabControl("tabControl", $this->tabsSettings);
		$tabControl->Begin();

		$formAction = (new Uri($APPLICATION->GetCurPage()))
				->addParams(["mid" => $this->moduleId, "lang" => LANGUAGE_ID])
				->getUri();
		?>

		<form action="<?= $formAction ?>" name="<?= "{$this->modulePrefix}_form" ?>" class="up-core-settings"
			  method="post">
			<?= bitrix_sessid_post() ?>
			<?php
			foreach ($this->tabsSettings as $arTab) {
				if (empty($arTab)) {
					continue;
				}
				$tabControl->BeginNextTab();
				$this->drawTabOptions($arTab["OPTIONS"]);
			}

			$tabControl->BeginNextTab();
			$tabControl->Buttons();
			?>
			<input type="submit" name="update" class="adm-btn-green" value="<?= Loc::getMessage("MAIN_SAVE") ?>">
			<input type="reset" value="<?= Loc::getMessage("MAIN_RESET") ?>">
		</form>
		<?php
		$tabControl->End();
	}

	protected function prepareTabs()
	{
		$this->tabsSettings = $this->getTabsArray();
	}

	/**
	 * Записать необходимые настройки модуля
	 */
	protected function postData()
	{
		$request = Application::getInstance()->getContext()->getRequest();

		if (
				!$request->isPost() ||
				!$request->get("update") ||
				!check_bitrix_sessid()
		) {
			return;
		}

		foreach ($this->tabsSettings as $arTab) {
			foreach ($arTab["OPTIONS"] as $arOption) {
				$this->postAnOption($arOption);
			}
		}

		$this->onPostEvents();

		LocalRedirect($_SERVER["REQUEST_URI"], true);
	}

	protected function postAnOption($arOption)
	{
		$request = Application::getInstance()->getContext()->getRequest();

		if (!is_array($arOption)) {
			return;
		}
		// Строка настроек может быть не полем, а рендерером (ключ print_row) — сохранять нечего.
		$key = $arOption[0] ?? '';
		if (empty($key)) {
			return;
		}
		$value = $request->get($key);

		if (($arOption[3][0] ?? '') === 'password') {
			if ($value === "") {
				$value = Option::get($this->moduleId, $key, "");
			}
			if ($request->get("{$key}_delete") === 'Y') {
				$value = "";
			}
		} elseif (!empty($arOption["ext_data"]["is_serialized"])) {
			$oldValue = $value;
			try {
				$value = json_decode((string)$value, true, 512, JSON_THROW_ON_ERROR);
			} catch (\JsonException $e) {
				// Некорректный JSON — не затираем сохранённое значение и не роняем страницу настроек.
				return;
			}

			if (!empty($oldValue) && empty($value)) {
				return;
			}
			$value = serialize($value);
		} elseif (empty($arOption["ext_data"]["skip_encode"])) {
			$value = htmlspecialchars((string)$value);
		}

		Option::set($this->moduleId, $key, $value);
	}
}