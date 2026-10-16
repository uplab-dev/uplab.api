<?php

namespace Uplab\Api\Model\WebForm;

use Bitrix\Main\Application;
use Bitrix\Main\Data\Cache;
use Bitrix\Main\Entity\Result;
use Bitrix\Main\Error;
use Bitrix\Main\EventResult;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use CForm;
use CFormResult;
use Uplab\Api\Enums\HttpStatus;
use Uplab\Api\EventResult\BeforeCheckForm;
use Uplab\Api\Utils\Config\BaseConfig;

class WebFormService
{
    /**
     * Типы вопросов, значения которых ядро (CForm::Check, CFormResult::Add) читает по SID вопроса:
     * form_{тип}_{SID}, а ID ответа передаётся значением. Остальные типы читаются по ID ответа.
     */
    protected const SID_NAMED_FIELD_TYPES = ['checkbox', 'multiselect', 'radio', 'dropdown'];

    /**
     * Метод end-point для получения данных web-формы в формате для FRONT.
     * @param string $webFormCode
     * @param BaseConfig $iblockConfig
     * @return \Bitrix\Main\Engine\Response\Json
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ArgumentTypeException
     * @throws \Bitrix\Main\LoaderException
     * @throws \Bitrix\Main\SystemException
     */
    public function getFormData(string $webFormCode, BaseConfig $iblockConfig): \Bitrix\Main\Engine\Response\Json
    {
        $result = new \Bitrix\Main\Engine\Response\Json();

        if (!Loader::includeModule('form')) {
            $result->setStatus(HttpStatus::FAILED_DEPENDENCY->value);
            $result->setData([
                'message' => Loc::getMessage('UPLAB_API.ERROR_LOAD_MODULE', ['#NAME#' => 'form'])
            ]);
        } else {
            $formData = $this->getWebFormFieldsByCode($webFormCode, $iblockConfig->getSiteId());

            if (!$formData) {
                $result->setStatus(404);
                $result->setData([
                    'message' => Loc::getMessage('UPLAB_API.WEB_FORM_NOT_FOUND')
                ]);
            } else {
                $data = [
                    'id'          => $formData['arForm']['ID'],
                    'attr'        => 'enctype="multipart/form-data"',
                    'title'       => $formData['arForm']['NAME'] ?? '',
                    'method'      => 'POST',
                    'description' => $formData['arForm']['DESCRIPTION'] ?? '',
                    'items'       => $formData['frontFields'] ?? []
                ];

                if (($formData['arForm']['F_RIGHT'] ?? 0) >= 10 && $textBtn = ($formData['arForm']['BUTTON'] ?? '')) {
                    $data['submit'] = $textBtn;
                }

                $result->setData($data);
            }
        }

        return $result;
    }

    /**
     * Метод end-point для получения данных о полях web-формы в формате для FRONT.
     * @param string $webFormCode
     * @param BaseConfig $iblockConfig
     * @return \Bitrix\Main\Engine\Response\Json
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ArgumentTypeException
     * @throws \Bitrix\Main\LoaderException
     * @throws \Bitrix\Main\SystemException
     */
    public function getFormFieldsData(string $webFormCode, BaseConfig $iblockConfig): \Bitrix\Main\Engine\Response\Json
    {
        $result = new \Bitrix\Main\Engine\Response\Json();

        if (!Loader::includeModule('form')) {
            $result->setStatus(HttpStatus::FAILED_DEPENDENCY->value);
            $result->setData([
                'message' => Loc::getMessage('UPLAB_API.ERROR_LOAD_MODULE', ['#NAME#' => 'form'])
            ]);
        } else {
            $formData = $this->getWebFormMixedFieldsByCode($webFormCode, $iblockConfig->getSiteId());

            if (!$formData) {
                $result->setStatus(404);
                $result->setData([
                    'message' => Loc::getMessage('UPLAB_API.WEB_FORM_NOT_FOUND')
                ]);
            } else {
                $data = [
                    'id'          => $formData['arForm']['ID'],
                    'attr'        => 'enctype="multipart/form-data"',
                    'title'       => $formData['arForm']['NAME'] ?? '',
                    'method'      => 'POST',
                    'description' => $formData['arForm']['DESCRIPTION'] ?? '',
                    'fields'      => $formData['frontFields'] ?? []
                ];

                if (($formData['arForm']['F_RIGHT'] ?? 0) >= 10 && $textBtn = ($formData['arForm']['BUTTON'] ?? '')) {
                    $data['submit'] = $textBtn;
                }

                $result->setData($data);
            }
        }

        return $result;
    }

    /**
     * Метод end-point для добавления нового результат web-формы в формате для FRONT.
     * @param string $webFormCode
     * @param BaseConfig $iblockConfig
     * @param array $request
     * @return \Bitrix\Main\Engine\Response\Json
     * @throws \Bitrix\Main\ArgumentTypeException
     * @throws \Bitrix\Main\LoaderException
     */
    public function addNewResult(string $webFormCode, BaseConfig $iblockConfig, array $request): \Bitrix\Main\Engine\Response\Json
    {
        $result = new \Bitrix\Main\Engine\Response\Json();

        if (!Loader::includeModule('form')) {
            $result->setStatus(HttpStatus::FAILED_DEPENDENCY->value);
            $result->setData([
                'message' => Loc::getMessage('UPLAB_API.ERROR_LOAD_MODULE', ['#NAME#' => 'form'])
            ]);
        } else {
            $formData = $this->getWebFormFieldsByCode($webFormCode, $iblockConfig->getSiteId());

            if (!$formData) {
                $result->setStatus(HttpStatus::NOT_FOUND->value);
                $result->setData([
                    'message' => Loc::getMessage('UPLAB_API.WEB_FORM_NOT_FOUND')
                ]);
            } else {
                $webFormId = (int)($formData['arForm']['ID'] ?? 0);

                $resultAdd = new Result();

                $event = new BeforeCheckForm(
                    webFormId: $webFormId,
                    request: $request,
                    formData: $formData
                );
                $event->send();
                foreach ($event->getResults() as $eventResult)
                {
                    if ($eventResult->getType() === EventResult::ERROR)
                    {
                        $resultAdd->addError(
                            new Error(
                                (string)($eventResult->getParameters()['message'] ?? 'Unknown'),
                            )
                        );
                    }
                    elseif ($eventResult->getType() === EventResult::UNDEFINED)
                    {
                        throw new \Exception('Wrong event result');
                    }
                }

                if ($resultAdd->isSuccess()) {
                    $error = CForm::Check($webFormId, $request, false, 'Y', 'Y');

                    if (count($error) !== 0) {
                        $error = (array)$error;
                        foreach ($error as $fieldCode => $errorText) {
                            if ($errorText) {
                                $resultAdd->addError(new Error($errorText, 2, ['FIELD_CODE' => $fieldCode]));
                            }
                        }
                    } else {
                        $resId = CFormResult::Add($webFormId, $request);

                        if (!$resId) {
                            global $APPLICATION;
                            $errorText = $APPLICATION->GetException()
                                ? $APPLICATION->GetException()->GetString()
                                : ($GLOBALS["strError"] ?? '');
                            //fix bug Bitrix core
                            if ($errorText && substr($errorText, -6) === '<br />') {
                                $errorText = substr($errorText, 0, -6);
                            } elseif ($errorText && substr($errorText, -4) === '<br>') {
                                $errorText = substr($errorText, 0, -4);
                            }
                            $resultAdd->addError(new Error((string)($errorText ?: 'Unknown error')));
                        } else {
                            \CFormCRM::onResultAdded($webFormId, $resId);
                            CFormResult::SetEvent($resId);

                            if (!CFormResult::Mail($resId)) {
                                global $strError;
                                \CAdminNotify::Add([
                                    'NOTIFY_TYPE'  => \CAdminNotify::TYPE_ERROR,
                                    'MESSAGE'      => $strError,
                                    'TAG'          => 'errorsenformemail',
                                    'MODULE_ID'    => 'uplab.api',
                                    'ENABLE_CLOSE' => 'Y'
                                ]);
                            }
                        }
                    }
                }

                if (!$resultAdd->isSuccess()) {
                    $datum = [];

                    $errors = $resultAdd->getErrorCollection();
                    /** @var Error $error */
                    foreach ($errors as $error) {
                        if ($error->getCode() === 2) {
                            $datum['fieldsError'][$error->getCustomData()['FIELD_CODE'] ?? ''] = $error->getMessage();
                        } else {
                            $datum['errors'][] = $error->getMessage();
                        }
                    }

                    $data = [
                        'data'   => $datum,
                        'status' => 'error',
                    ];
                } else {
                    $data = [
                        'data'   => [],
                        'status' => 'success'
                    ];
                }

                $result->setData($data);
            }
        }

        return $result;
    }

    /**
     * Получить все данные о полях web-формы
     * @param string $webFormCode
     * @param string $siteId
     * @return array
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\LoaderException
     * @throws \Bitrix\Main\SystemException
     */
    public function getWebFormFieldsByCode(string $webFormCode, ?string $siteId = ''): array
    {
        if (!$siteId) {
            $siteId = SITE_ID;
        }

        $cache = Cache::createInstance();

        $cacheTime = 3600;
        $cacheDir = "/$siteId/formFields";

        $data = [];
        if ($cache->initCache($cacheTime, $webFormCode, $cacheDir)) {
            $data = $cache->getVars() ?? [];
        } elseif ($cache->startDataCache() && Loader::includeModule('form')) {
            if ($form = (CForm::GetBySID($webFormCode)->fetch())) {
                $webFormId = CForm::GetDataByID(
                    $form['ID'],
                    $data['arForm'],
                    $data['arQuestions'],
                    $data['arAnswers'],
                    $data['arDropDown'],
                    $data['arMultiSelect'],
                    'ALL'
                );

                global $APPLICATION;
                if (($data['arForm']['USE_CAPTCHA'] ?? 'N') === 'Y') {
                    $data['CAPTCHACode'] = $APPLICATION->CaptchaGetCode();
                }
                if ($imageId = $data['arForm']['IMAGE_ID'] ?? 0) {
                    $data['FORM_IMAGE']['ID'] = (int)$imageId;
                    // assign form image url
                    $arImage = \CFile::GetFileArray($imageId);
                    $data['FORM_IMAGE']['URL'] = $arImage['SRC'];

                    // check image file existance and assign image data
                    if (mb_substr($arImage['SRC'], 0, 1) === '/') {
                        $arSize = \CFile::GetImageSize($_SERVER['DOCUMENT_ROOT'] . $arImage['SRC']);
                        if (is_array($arSize)) {
                            [
                                $data['FORM_IMAGE']['WIDTH'],
                                $data['FORM_IMAGE']['HEIGHT'],
                                $data['FORM_IMAGE']['TYPE'],
                                $data['FORM_IMAGE']['ATTR']
                            ] = $arSize;
                        }
                    } else {
                        $data['FORM_IMAGE']['WIDTH'] = $arImage['WIDTH'];
                        $data['FORM_IMAGE']['HEIGHT'] = $arImage['HEIGHT'];
                        $data['FORM_IMAGE']['TYPE'] = false;
                        $data['FORM_IMAGE']['ATTR'] = false;
                    }
                }

                $data['frontFields'] = $this->getFieldsForFront($data);

                $cacheManager = Application::getInstance()->getTaggedCache();
                $cacheManager->startTagCache($cacheDir);
                $cacheManager->registerTag('forms');
                $cacheManager->registerTag('form_' . $webFormId);
                $cacheManager->endTagCache();
            } else {
                $cache->abortDataCache();
            }
            $cache->endDataCache($data);
        }

        if ($data) {
            Loader::includeModule('form');
            $data['arForm']['F_RIGHT'] = (int)\CForm::GetPermission($data['arForm']["ID"]);
        }

        return $data;
    }

    /**
     * Получить все данные о полях web-формы, включая смешанные
     * @param string $webFormCode
     * @param string $siteId
     * @return array
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\LoaderException
     * @throws \Bitrix\Main\SystemException
     */
    public function getWebFormMixedFieldsByCode(string $webFormCode, ?string $siteId = ''): array
    {
        if (!$siteId) {
            $siteId = SITE_ID;
        }

        $cache = Cache::createInstance();

        $cacheTime = 3600;
        $cacheDir = "/$siteId/formFields";
        // Отдельный ключ кеша: payload отличается от getWebFormFieldsByCode (иначе методы затирают друг друга).
        $cacheId = $webFormCode . '_mixed';

        $data = [];
        if ($cache->initCache($cacheTime, $cacheId, $cacheDir)) {
            $data = $cache->getVars() ?? [];
        } elseif ($cache->startDataCache() && Loader::includeModule('form')) {
            if ($form = (CForm::GetBySID($webFormCode)->fetch())) {
                $webFormId = CForm::GetDataByID(
                    $form['ID'],
                    $data['arForm'],
                    $data['arQuestions'],
                    $data['arAnswers'],
                    $data['arDropDown'],
                    $data['arMultiSelect'],
                    'ALL'
                );

                global $APPLICATION;
                if (($data['arForm']['USE_CAPTCHA'] ?? 'N') === 'Y') {
                    $data['CAPTCHACode'] = $APPLICATION->CaptchaGetCode();
                }
                if ($imageId = $data['arForm']['IMAGE_ID'] ?? 0) {
                    $data['FORM_IMAGE']['ID'] = (int)$imageId;
                    // assign form image url
                    $arImage = \CFile::GetFileArray($imageId);
                    $data['FORM_IMAGE']['URL'] = $arImage['SRC'];

                    // check image file existance and assign image data
                    if (mb_substr($arImage['SRC'], 0, 1) === '/') {
                        $arSize = \CFile::GetImageSize($_SERVER['DOCUMENT_ROOT'] . $arImage['SRC']);
                        if (is_array($arSize)) {
                            [
                                $data['FORM_IMAGE']['WIDTH'],
                                $data['FORM_IMAGE']['HEIGHT'],
                                $data['FORM_IMAGE']['TYPE'],
                                $data['FORM_IMAGE']['ATTR']
                            ] = $arSize;
                        }
                    } else {
                        $data['FORM_IMAGE']['WIDTH'] = $arImage['WIDTH'];
                        $data['FORM_IMAGE']['HEIGHT'] = $arImage['HEIGHT'];
                        $data['FORM_IMAGE']['TYPE'] = false;
                        $data['FORM_IMAGE']['ATTR'] = false;
                    }
                }

                $data['frontFields'] = $this->getFieldsDataForFront($data);

                $cacheManager = Application::getInstance()->getTaggedCache();
                $cacheManager->startTagCache($cacheDir);
                $cacheManager->registerTag('forms');
                $cacheManager->registerTag('form_' . $webFormId);
                $cacheManager->endTagCache();
            } else {
                $cache->abortDataCache();
            }
            $cache->endDataCache($data);
        }

        if ($data) {
            Loader::includeModule('form');
            $data['arForm']['F_RIGHT'] = (int)\CForm::GetPermission($data['arForm']["ID"]);
        }

        return $data;
    }

    /**
     * Преобразовываем вопросы и ответы web-формы в поля для FRONT (для типовых ответов-вопросов).
     * @param array $formData
     * @return array
     */
    protected function getFieldsForFront(array $formData): array
    {
        $result = [];

        if (!empty($formData['arQuestions'])) {

            reset($formData["arQuestions"]);
            //get Validator
            foreach ($formData['arQuestions'] as &$question) {
                $resValid = \CFormValidator::GetList($question['ID']);
                while ($item = $resValid->Fetch()) {
                    $question['VALIDATORS'][] = $item;
                }
            }
            unset($question);

            reset($formData["arQuestions"]);
            foreach ($formData["arQuestions"] as $arQuestion) {
                $FIELD_SID = $arQuestion["SID"];
                if (empty($formData["arAnswers"][$FIELD_SID])) {
                    continue;
                }
                $field = [
                    'id'       => $formData["arQuestions"][$FIELD_SID]["ID"] ?? '',
                    'code'     => $FIELD_SID,
                    //'comments' => $formData["arQuestions"][$FIELD_SID]["COMMENTS"] ?? '',
                    'label'    => // field caption
                        $formData["arQuestions"][$FIELD_SID]["TITLE_TYPE"] === "html" ?
                            $formData["arQuestions"][$FIELD_SID]["TITLE"] :
                            nl2br(htmlspecialcharsbx($formData["arQuestions"][$FIELD_SID]["TITLE"])),
                    'required' => $formData["arQuestions"][$FIELD_SID]["REQUIRED"] === "Y"
                ];

                $answers = $formData["arAnswers"][$FIELD_SID];

                if (is_array($answers)) {
                    $field['type'] = $formData["arAnswers"][$FIELD_SID][0]['FIELD_TYPE'];

                    $field['name'] = "form_{$field['type']}_{$arQuestion['SID']}";

                    if (!empty($arQuestion['VALIDATORS'])) {
                        foreach ($arQuestion['VALIDATORS'] as $validator) {
                            switch ($validator['NAME']) {
                                case 'text_len':
                                    if ($val = $validator['PARAMS']['LENGTH_FROM'] ?? 0) {
                                        $field['minlength'] = $val;
                                    }
                                    if ($val = $validator['PARAMS']['LENGTH_TO'] ?? 0) {
                                        $field['maxlength'] = $val - 1;//Какой-то баг битрикс. Он считает не включая это число, поэтому делаем N-1
                                    }
                                    break;

                                case 'file_type':
                                    if (isset($validator['PARAMS']) && (isset($validator['PARAMS']['EXT']) || isset($validator['PARAMS']['EXT_CUSTOM']))) {
                                        $arrAccept = [];
                                        if (isset($validator['PARAMS']['EXT_CUSTOM']) && $validator['PARAMS']['EXT_CUSTOM']
                                            && $ar = explode(',', $validator['PARAMS']['EXT_CUSTOM'])) {
                                            $arrAccept = array_merge($arrAccept, array_map('trim', $ar));
                                        }
                                        if (isset($validator['PARAMS']['EXT']) && $validator['PARAMS']['EXT']
                                            && $ar = explode(',', $validator['PARAMS']['EXT'])) {
                                            $arrAccept = array_merge($arrAccept, array_map('trim', $ar));
                                        }
                                        //for front
                                        if ($arrAccept) {
                                            foreach ($arrAccept as &$accept) {
                                                $accept = '.' . $accept;
                                            }
                                            unset($accept);
                                        }
                                        $field['accept'] = $arrAccept;
                                    }
                                    break;

                                case 'file_size':
                                    if (isset($validator['PARAMS']['SIZE_FROM']) && $val = $validator['PARAMS']['SIZE_FROM']) {
                                        $field['minSize'] = $val;
                                    }
                                    if (isset($validator['PARAMS']['SIZE_TO']) && $val = $validator['PARAMS']['SIZE_TO']) {
                                        $field['maxSize'] = $val;
                                    }
                                    break;
                            }
                        }
                    }

                    if (count($answers) === 1) {
                        $answer = $answers[0] ?? [];

                        if (!in_array($field['type'], static::SID_NAMED_FIELD_TYPES, true)) {
                            $field['name'] = "form_{$field['type']}_{$answer['ID']}";
                        }

                        $message = trim($answer['MESSAGE']);
                        if (strlen($message) > 0) {
                            $field['placeholder'] = $message;
                        }
                        $value = $answer['VALUE'];
                        if (strlen($value) > 0) {
                            $field['value'] = $value;
                        }
                        $val = $answer['FIELD_PARAM'];
                        if (strlen($val) > 0) {
                            $field['param'] = $val;
                        }
                    }

                    switch ($field['type']) {
                        case 'multiselect':
                            $field['value'] = $answer['ID'];
                            $field['name'] .= '[]';
                            break;
                        case 'checkbox':
                            $field['name'] .= '[]';
                        case 'dropdown':
                        case 'radio':
                            $field['type'] = $field['type'] === 'dropdown' ? 'select' : $field['type'];
                            foreach ($answers as $answer) {
                                if ($answer['ACTIVE'] === 'Y') {
                                    $option = [
                                        'selected' => ($answer['FIELD_PARAM'] ?? '') === 'selected',
                                        'value'    => $answer['ID'],
                                        'opValue'  => $answer['VALUE'],
                                        'text'     => $answer['MESSAGE']
                                    ];
                                    $val = $answer['FIELD_PARAM'];
                                    if (strlen($val) > 0) {
                                        $option['param'] = $val;
                                    }
                                    $field['options'][] = $option;
                                }
                            }

                            break;
                        case 'file':
                            $field['multiple'] = count($answers) > 1;
                            if ($field['multiple']) {
                                foreach ($answers as $answer) {
                                    if ($answer['ACTIVE'] === 'Y') {
                                        $field['fileds'][] = [
                                            'name'       => 'form_' . $answer['FIELD_TYPE'] . '_' . $answer['ID'],
                                            'value'      => $answer['VALUE'],
                                            'message'    => $answer['MESSAGE'],
                                            'fieldParam' => $answer['FIELD_PARAM'],
                                        ];
                                    }
                                }
                            }
                            break;
                    }
                }

                if ($arQuestion['COMMENTS']) {
                    $field['placeholder'] = $arQuestion['COMMENTS'];
                }

                if ($FIELD_SID === 'PHONE') {
                    $field['type'] = 'tel';
                }

                $result[] = $field;
            }
        }
        return $result;
    }

    /**
     * Преобразовываем вопросы и ответы web-формы в поля для FRONT (включая разные ответы к одному вопросу).
     * @param array $formData
     * @return array
     */
    protected function getFieldsDataForFront(array $formData): array
    {
        $result = [];

        if (!empty($formData['arQuestions'])) {
            $webFormId = $formData['ID'] ?? $formData['arForm']['ID'] ?? 0;

            reset($formData["arQuestions"]);
            //get Validator
            foreach ($formData['arQuestions'] as &$question) {
                $resValid = \CFormValidator::GetList($question['ID']);
                while ($item = $resValid->Fetch()) {
                    $question['VALIDATORS'][] = $item;
                }
            }
            unset($question);

            reset($formData["arQuestions"]);
            foreach ($formData["arQuestions"] as $arQuestion) {
                $FIELD_SID = $arQuestion["SID"];
                if (empty($formData["arAnswers"][$FIELD_SID])) {
                    continue;
                }
                $field = [
                    'id'       => $formData["arQuestions"][$FIELD_SID]["ID"] ?? '',
                    'code'     => $FIELD_SID,
                    //'comments' => $formData["arQuestions"][$FIELD_SID]["COMMENTS"] ?? '',
                    'title'    => // field caption
                        $formData["arQuestions"][$FIELD_SID]["TITLE_TYPE"] === "html" ?
                            $formData["arQuestions"][$FIELD_SID]["TITLE"] :
                            nl2br(htmlspecialcharsbx($formData["arQuestions"][$FIELD_SID]["TITLE"])),
                    'required' => $formData["arQuestions"][$FIELD_SID]["REQUIRED"] === "Y"
                ];

                $answers = $formData["arAnswers"][$FIELD_SID];

                if (is_array($answers)) {
                    if (count($answers) === 1) {
                        $field['type'] = $formData["arAnswers"][$FIELD_SID][0]['FIELD_TYPE'];

                        $answer = $answers[0];

                        $field['name'] = in_array($field['type'], static::SID_NAMED_FIELD_TYPES, true)
                            ? "form_{$field['type']}_{$arQuestion['SID']}"
                            : "form_{$field['type']}_{$answer['ID']}";
                        if (in_array($field['type'], ['multiselect', 'checkbox'], true)) {
                            $field['name'] .= '[]';
                        }

                        $message = trim($answer['MESSAGE']);
                        if (strlen($message) > 0) {
                            $field['placeholder'] = $message;
                        }
                        $value = $answer['VALUE'];
                        if (strlen($value) > 0) {
                            $field['value'] = $value;
                        }
                        $val = $answer['FIELD_PARAM'];
                        if (strlen($val) > 0) {
                            $field['class'] = $val;
                        }
                    }
                    if (!empty($arQuestion['VALIDATORS'])) {
                        foreach ($arQuestion['VALIDATORS'] as $validator) {
                            switch ($validator['NAME']) {
                                case 'text_len':
                                    if ($val = $validator['PARAMS']['LENGTH_FROM'] ?? 0) {
                                        $field['minlength'] = $val;
                                    }
                                    if ($val = $validator['PARAMS']['LENGTH_TO'] ?? 0) {
                                        $field['maxlength'] = $val - 1;//Какой-то баг битрикс. Он считает не включая это число, поэтому делаем N-1
                                    }
                                    break;

                                case 'file_type':
                                    if (isset($validator['PARAMS']) && (isset($validator['PARAMS']['EXT']) || isset($validator['PARAMS']['EXT_CUSTOM']))) {
                                        $arrAccept = [];
                                        if (isset($validator['PARAMS']['EXT_CUSTOM']) && $validator['PARAMS']['EXT_CUSTOM']
                                            && $ar = explode(',', $validator['PARAMS']['EXT_CUSTOM'])) {
                                            $arrAccept = array_merge($arrAccept, array_map('trim', $ar));
                                        }
                                        if (isset($validator['PARAMS']['EXT']) && $validator['PARAMS']['EXT']
                                            && $ar = explode(',', $validator['PARAMS']['EXT'])) {
                                            $arrAccept = array_merge($arrAccept, array_map('trim', $ar));
                                        }
                                        //for front
                                        if ($arrAccept) {
                                            foreach ($arrAccept as &$accept) {
                                                $accept = '.' . $accept;
                                            }
                                            unset($accept);
                                        }
                                        $field['accept'] = $arrAccept;
                                    }
                                    break;

                                case 'file_size':
                                    if (isset($validator['PARAMS']['SIZE_FROM']) && $val = $validator['PARAMS']['SIZE_FROM']) {
                                        $field['minSize'] = $val;
                                    }
                                    if (isset($validator['PARAMS']['SIZE_TO']) && $val = $validator['PARAMS']['SIZE_TO']) {
                                        $field['maxSize'] = $val;
                                    }
                                    break;
                            }
                        }
                    }

                    foreach ($answers as $answer) {
                        $singleField = [
                            'type'  => $answer['FIELD_TYPE'],
                            'value' => $answer['VALUE'],
                            'id'    => $answer['ID'],
                            'text'  => $answer['MESSAGE'],
                            'name'  => "form_{$answer['FIELD_TYPE']}_"
                        ];
                        if ($param = $answer['FIELD_PARAM']) {
                            $singleField['attr'] = $param;
                        }
                        if (in_array($answer['FIELD_TYPE'], static::SID_NAMED_FIELD_TYPES, true)) {
                            $singleField['name'] .= $arQuestion['SID'];
                        } else {
                            $singleField['name'] .= $answer['ID'];
                        }
                        if (in_array($answer['FIELD_TYPE'], ['multiselect', 'checkbox'])) {
                            $singleField['name'] .= '[]';
                        }
                        $field['fields'][] = $singleField;
                    }
                }

                if ($arQuestion['COMMENTS']) {
                    $field['placeholder'] = $arQuestion['COMMENTS'];
                }

                if ($FIELD_SID === 'PHONE') {
                    $field['type'] = 'tel';
                }

                $result[] = $field;
            }
        }
        return $result;
    }
}