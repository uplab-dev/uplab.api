<?php

/** @global CMain $APPLICATION */

/** @global CDatabase $DB */

/** @global CUser $USER */

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Highloadblock as HL;

define("ADMIN_MODULE_NAME", "uplab.api");

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

IncludeModuleLangFile(__FILE__);

if (!Loader::includeModule(ADMIN_MODULE_NAME)) {
	ShowError(Loc::getMessage("UPLAB_API_MODULE_NOT_INSTALLED"));
}

// Проверка прав доступа
$POST_RIGHT = $APPLICATION->GetGroupRight(ADMIN_MODULE_NAME);

if ($POST_RIGHT == "D") {
	$APPLICATION->AuthForm(GetMessage("ACCESS_DENIED"));
}
\Bitrix\Main\Loader::includeModule("highloadblock");


$request = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();

$pageCode = (string)$request->get('PAGE_CODE');

$canEdit = false;
$canDelete = false;

// unknown artefact
$isList = true;

$HLBlockName = \Uplab\Api\Model\Content\ContentTable::getHLBlockName();
$hlblock = \Uplab\Api\Model\Hlblock\HlblockService::getHlblockByName($HLBlockName);

if (empty($hlblock)) {
	ShowError(GetMessage('CONTENT_PAGE_EDIT_HLBLOCK_NOT_FOUND'));
	CMain::FinalActions();
}

$entityId = $hlblock['ID'] ?? null;

// get entity info
if (($entityId !== null) && !empty($hlblock)) {
	//localization
	$lang = HL\HighloadBlockLangTable::getRow([
			'filter' => [
					'=ID'  => $hlblock['ID'],
					'=LID' => LANGUAGE_ID,
			]
	]);
	$hlblock['NAME_LANG'] = $lang['NAME'] ?? $hlblock['NAME'];
	//check rights
	if ($USER->isAdmin()) {
		$canEdit = $canDelete = true;
	} else {
		$operations = HL\HighloadBlockRightsTable::getOperationsName($entityId);
		if (empty($operations)) {
			$APPLICATION->AuthForm(GetMessage("ACCESS_DENIED"));
		} else {
			$canEdit = in_array('hl_element_write', $operations);
			$canDelete = in_array('hl_element_delete', $operations);
		}
	}
}
// get entity
$entity = HL\HighloadBlockTable::compileEntity($entityId);
/** @var HL\DataManager $entity_data_class */
$entity_data_class = $entity->getDataClass();

$is_create_form = false;
$action = 'add';

$isEditMode = $canEdit;

// get row
$pageTitle = GetMessage('CONTENT_PAGE_EDIT_TITLE_CREATE');
$ID = (int)$request->get('ID');
$row = [];

$ufPage = $request->get('PAGE') ?? '';

$is_update_form = false;

if ($ID > 0) {
	$pageTitle = GetMessage('CONTENT_PAGE_EDIT_TITLE_UPDATE', ['#ID#' => $ID]);
	$row = $entity_data_class::getById($ID)->fetch();

	if (empty($row)) {
		$ID = 0;
		$row = null;
	}

	if (!empty($row)) {
		if ($action !== 'copy') {
			if ($action !== 'delete') {
				$action = 'update';
			}
			$is_update_form = true;
			$is_create_form = false;
		}
	}
}


$APPLICATION->SetTitle($pageTitle);

global $USER_FIELD_MANAGER;
$ufields = $USER_FIELD_MANAGER->GetUserFields('HLBLOCK_' . $hlblock['ID'],0, LANGUAGE_ID);


// save action
$save = (string)$request->getPost('save');
$apply = (string)$request->getPost('apply');
$savedSuccessfully = false;

if (($save !== '' || $apply !== '') && $request->isPost() && $canEdit && check_bitrix_sessid()) {
	$data = [];

	$USER_FIELD_MANAGER->EditFormAddFields('HLBLOCK_' . $hlblock['ID'], $data);

	$message = '';
	if ($is_update_form) {
		$result = $entity_data_class::update($ID, $data);
		$message = GetMessage('CONTENT_PAGE_EDIT_MSG_UPDATED');
	} else {
		$result = $entity_data_class::add($data);
		$ID = (int)$result->getId();
		$message = GetMessage('CONTENT_PAGE_EDIT_MSG_ADDED');
		if ($ID > 0) {
			$pageTitle = GetMessage('CONTENT_PAGE_EDIT_TITLE_UPDATE', ['#ID#' => $ID]);
		}
	}

	if ($result->isSuccess()) {
		$savedSuccessfully = true;
		ShowMessage(['MESSAGE' => $message, 'TYPE' => 'OK']);
		$row = $entity_data_class::getById($ID)->fetch();
	} else {
		$errors = $result->getErrorMessages();
		foreach ($data as $k => $v) {
			$row[$k] = $v;
		}
	}
}
echo "<h1>{$pageTitle}</h1>";

//view
//require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
\Bitrix\Main\UI\Extension::load([
		'ui.forms',
		'ui.buttons',
		'ui.alerts',
]);
\CJSCore::init(["sidepanel", "ui.forms"]);
?>
	<!doctype html>
	<html lang="<?= LANGUAGE_ID ?>">
	<head>
		<meta charset="UTF-8">
		<meta name="viewport"
			  content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
		<meta http-equiv="X-UA-Compatible" content="ie=edge">
		<title><?php
			$APPLICATION->ShowTitle(); ?></title>
		<?php
		echo $USER_FIELD_MANAGER->ShowScript();
		// Подключаем UI библиотеки для красивой формы
		$APPLICATION->ShowHead();
		?>
	</head>
	<body>
	<?php
	if (!empty($errors)) {
		$bVarsFromForm = true;
		ShowError(implode("\n", $errors));
	} else {
		$bVarsFromForm = false;
	}
	if ($savedSuccessfully) { ?>
		<script>
			if (window.parent && window.parent !== window) { window.parent.uplabContentWasSaved = true; }
			<?php if ($save !== '') { ?>
			BX.SidePanel.Instance.close();
			<?php } ?>
		</script>
	<?php } ?>

	<script>
	function uplabAddMultiRow(btn, fieldName) {
		var tbody = btn.closest('table').querySelector('tbody');
		var rows = tbody.querySelectorAll('tr');
		var lastDataRow = null;
		for (var i = 0; i < rows.length - 1; i++) {
			if (rows[i].querySelector('select,input[type=text],input[type=hidden]')) {
				lastDataRow = rows[i];
			}
		}
		if (!lastDataRow) return;
		var newRow = lastDataRow.cloneNode(true);
		var idx = tbody.querySelectorAll('tr').length - 1;
		newRow.querySelectorAll('select,input').forEach(function(el) {
			if (el.name) el.name = fieldName + '[' + idx + ']';
			if (el.id)   el.id   = fieldName + '[' + idx + ']';
			if (el.tagName === 'SELECT') {
				el.selectedIndex = 0;
			} else {
				el.value = '';
			}
		});
		tbody.insertBefore(newRow, tbody.lastElementChild);
	}
	</script>
	<style>
		body {
			font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
			font-size: 13px;
			color: #333;
		}

		.ui-form {
			max-width: 600px;
			margin: 20px auto;
		}

		.ui-form-row {
			margin-bottom: 16px;
		}

		.ui-form-label {
			display: block;
			margin-bottom: 6px;
			font-weight: 500;
		}

		.ui-form-field {
			width: 100%;
		}

		.ui-btn-container {
			margin-top: 20px;
			display: flex;
			justify-content: space-between;
			align-items: center;
		}

		.ui-btn-container__left {
			display: flex;
			gap: 8px;
		}
	</style>

	<form method="post" class="ui-form">
		<?= bitrix_sessid_post() ?>
		<input type="hidden" name="ID" value="<?= $ID ?>">
		<?php
		foreach ($ufields as $fieldName => $fieldInfo):
			?>
			<div class="ui-form-row">
				<label class="ui-form-label"><?= htmlspecialcharsbx(
							($fieldInfo["LIST_COLUMN_LABEL"] ?: $fieldInfo['EDIT_FORM_LABEL']) ?: $fieldInfo["FIELD_NAME"]) ?></label>
				<div class="ui-form-field">
					<?php
					$value = $row[$fieldName] ?? '';
					if (!$is_update_form && $value === '') {
						$value = $fieldInfo['SETTINGS']['DEFAULT_VALUE'] ?? '';
					}
					$fieldInfo['VALUE'] = $value;
					if ($fieldName === 'UF_PAGE' && $ufPage) {
						$fieldInfo['VALUE'] = $ufPage;
					}

					if ($fieldInfo['MULTIPLE'] === 'Y') {
						$vals = $fieldInfo['VALUE'];
						if (!is_array($vals)) {
							$vals = ($vals !== '' && $vals !== false) ? [$vals] : [''];
						}
						$vals = array_values($vals);
						$s = '<table id="table_' . $fieldName . '"><tbody>';
						foreach ($vals as $i => $val) {
							$rowField = $fieldInfo;
							$rowField['MULTIPLE'] = 'N';
							$rowField['VALUE'] = $val;
							$rowField['FIELD_NAME'] = $fieldName . '[' . $i . ']';
							$rowHtml = $USER_FIELD_MANAGER->GetPublicEdit($rowField);
							$rowHtml = str_replace("class='typeselect'", 'class="ui-ctl-element"', $rowHtml);
							if (str_contains($rowHtml, '<select')) {
								$rowHtml = '<div class="ui-ctl ui-ctl-after-icon ui-ctl-dropdown"><div class="ui-ctl-after ui-ctl-icon-angle"></div>' . $rowHtml . '</div>';
							}
							$s .= '<tr><td>' . $rowHtml . '</td></tr>';
						}
						$s .= '<tr><td style="padding-top:6px;">'
							. '<input type="button" value="' . GetMessage('CONTENT_PAGE_EDIT_BTN_ADD_ROW') . '" class="ui-btn ui-btn-xs ui-btn-light-border" onclick="uplabAddMultiRow(this, \'' . htmlspecialcharsbx($fieldName) . '\')">'
							. '</td></tr>'
							. '</tbody></table>';
					} else {
						$s = $USER_FIELD_MANAGER->GetPublicEdit($fieldInfo);
						if ($fieldName === 'UF_PAGE') {
							$s = str_replace('<input', '<input readonly', $s);
						}
						$s = str_replace('class="', 'class="ui-ctl-element ', $s);
						$s = str_replace("<select class='typeselect'", '<select class="ui-ctl-element"', $s);
						if (str_contains($s, '<textarea')) {
							$s = preg_replace('/<textarea\b/', '<textarea class="ui-ctl-element"', $s);
							$s = '<div class="ui-ctl ui-ctl-textarea ui-ctl-resize-y">' . $s . '</div>';
						} elseif (str_contains($s, '<select')) {
							$s = '<div class="ui-ctl ui-ctl-after-icon ui-ctl-dropdown"><div class="ui-ctl-after ui-ctl-icon-angle"></div>' . $s . '</div>';
						}
					}

					echo $s;
					?>
				</div>
			</div>
		<?php
		endforeach; ?>

		<div class="ui-btn-container">
			<div class="ui-btn-container__left">
				<?php if ($canEdit) { ?>
					<button type="submit" name="save" value="Y" class="ui-btn ui-btn-success"><?= GetMessage('CONTENT_PAGE_EDIT_BTN_SAVE') ?></button>
					<button type="submit" name="apply" value="Y" class="ui-btn ui-btn-light-border"><?= GetMessage('CONTENT_PAGE_EDIT_BTN_APPLY') ?></button>
				<?php } ?>
			</div>
			<button type="button" class="ui-btn ui-btn-light-border" onclick="BX.SidePanel.Instance.close()"><?= GetMessage('CONTENT_PAGE_EDIT_BTN_CANCEL') ?></button>
		</div>
	</form>
	</body>
	</html>
<?php
CMain::FinalActions();
?>