<?php

namespace Uplab\Api\Controller;

use Bitrix\Main\Error;
use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Loader;
use Bitrix\Highloadblock as HL;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Engine\ActionFilter\Authentication;
use Bitrix\Main\Engine\ActionFilter\Csrf;
use Bitrix\Main\Engine\ActionFilter\HttpMethod;
use Uplab\Api\Enums\HttpStatus;
use Uplab\Api\Exception\ApiException;
use Uplab\Api\Utils\Config\BaseConfig;

class ContentController extends BaseController
{
    public function configureActions(): array
    {
        return [
            'getList' => [
                '+prefilters' => [
                    new Authentication(),
                    new HttpMethod([HttpMethod::METHOD_POST]),
                ],
            ],
            'bulk' => [
                '+prefilters' => [
                    new Authentication(),
                    new HttpMethod([HttpMethod::METHOD_POST]),
                    new Csrf(),
                ],
            ],
            'deleteElement' => [
                '+prefilters' => [
                    new Authentication(),
                    new HttpMethod([HttpMethod::METHOD_DELETE]),
                    new Csrf(),
                ],
            ],
        ];
    }

    /**
     * Максимальный размер страницы в списке записей контента.
     *
     * Точка расширения: административные экшены контента конфига запроса не используют, поэтому
     * лимиты меняются переопределением этих методов в наследнике контроллера.
     *
     * @return int
     */
    protected function getMaxPageSize(): int
    {
        return BaseConfig::MAX_LIMIT;
    }

    /**
     * Максимальное смещение в списке записей контента.
     * @return int
     */
    protected function getMaxOffset(): int
    {
        return BaseConfig::MAX_OFFSET;
    }

    /**
     * Максимальное число ID в групповом действии.
     * @return int
     */
    protected function getMaxBulkIds(): int
    {
        return BaseConfig::MAX_LIMIT;
    }

    protected function getSourceEntity()
    {
        return \Uplab\Api\Model\Content\ContentTable::class;
    }

    public function getListAction(string $pageCode, $page = 1, $pageSize = 20)
    {
        BaseConfig::assertCacheSegment($pageCode, 'page code');
        $page = (int)$page;
        $pageSize = (int)$pageSize;
        if ($page < 1 || $pageSize < 1 || $pageSize > $this->getMaxPageSize()) {
            throw new ApiException(
                (string)Loc::getMessage('UPLAB_API.ERROR_PAGINATION_INVALID'),
                HttpStatus::BAD_REQUEST
            );
        }
        if (($page - 1) * $pageSize > $this->getMaxOffset()) {
            throw new ApiException(
                (string)Loc::getMessage(
                    'UPLAB_API.ERROR_PAGINATION_OFFSET_TOO_LARGE',
                    ['#MAX#' => $this->getMaxOffset()]
                ),
                HttpStatus::BAD_REQUEST
            );
        }
        $canRead = false;
        $entityId = 0;
        if ($this->getCurrentUser() && Loader::includeModule('highloadblock')) {
            /** @var \Uplab\Api\Model\Content\ContentTable $entity */
            $entity = $this->getSourceEntity();
            $HLBlockName = $entity::getHLBlockName();
            $hlblock = \Uplab\Api\Model\Hlblock\HlblockService::getHlblockByName($HLBlockName);
            $entityId = $hlblock['ID'] ?? null;
            if (($entityId !== null) && !empty($hlblock)) {
                //check rights
                if ($this->getCurrentUser()->isAdmin()) {
                    $canRead = true;
                } else {
                    $operations = HL\HighloadBlockRightsTable::getOperationsName($entityId);
                    if (!empty($operations)) {
                        $canRead = in_array('hl_element_read', $operations);
                    }
                }
            }
        }

        if (!$canRead || !$entityId) {
            $this->addError(new Error(Loc::getMessage('UPLAB_API.ACCESS_DENIED')));
            return [];
        }

        $data = [
            'headers' => [],
            'items'   => []
        ];

        $hlBlockId = (int)$entityId;
        $fields = $GLOBALS['USER_FIELD_MANAGER']->GetUserFields('HLBLOCK_' . $hlBlockId, 0, LANGUAGE_ID);
        $headers = [
            ["id" => "ID", "content" => "ID", "sort" => "ID", 'userTypeId' => 'int']
        ];

        foreach ($fields as $field) {
            $headers[] = [
                '~'          => $field,
                "id"         => $field["FIELD_NAME"],
                "userTypeId" => $field["USER_TYPE_ID"],
                "content"    => ($field["LIST_COLUMN_LABEL"] ?: $field['EDIT_FORM_LABEL']) ?: $field["FIELD_NAME"],
                "sort"       => $field["FIELD_NAME"]
            ];
        }
        $data['headers'] = $headers;


        $nav = new \Bitrix\Main\UI\PageNavigation('elements');
        $nav->setPageSize($pageSize)->setCurrentPage($page);

        $obEntity = HL\HighloadBlockTable::compileEntity($hlblock);
        $entityDataClass = $obEntity->getDataClass();

        $list = $entityDataClass::getList([
            'filter'      => ['UF_PAGE' => $pageCode],
            'order'       => ['ID' => 'DESC'],
            'count_total' => true,
            'offset'      => $nav->getOffset(),
            'limit'       => $nav->getLimit(),
        ]);
        $nav->setRecordCount($list->getCount());

        $data['total'] = $list->getCount();

        while ($item = $list->fetch()) {
            $data['items'][] = $item;
        }
        return $data;
    }

    public function bulkAction(string $action, array $ids): array
    {
        if (!check_bitrix_sessid()) {
            $this->addError(new Error(Loc::getMessage('UPLAB_API.ACCESS_DENIED')));
            return [];
        }

        if (empty($ids)) {
            $this->addError(new Error((string)Loc::getMessage('UPLAB_API.ERROR_EMPTY_IDS')));
            return [];
        }
        if (count($ids) > $this->getMaxBulkIds()) {
            throw new ApiException(
                (string)Loc::getMessage('UPLAB_API.ERROR_TOO_MANY_IDS', ['#MAX#' => $this->getMaxBulkIds()]),
                HttpStatus::BAD_REQUEST
            );
        }

        $canWrite  = false;
        $canDelete = false;
        $entityId  = 0;

        if ($this->getCurrentUser() && Loader::includeModule('highloadblock')) {
            /** @var \Uplab\Api\Model\Content\ContentTable $entity */
            $entity      = $this->getSourceEntity();
            $HLBlockName = $entity::getHLBlockName();
            $hlblock     = \Uplab\Api\Model\Hlblock\HlblockService::getHlblockByName($HLBlockName);
            $entityId    = $hlblock['ID'] ?? null;

            if ($entityId && !empty($hlblock)) {
                if ($this->getCurrentUser()->isAdmin()) {
                    $canWrite = $canDelete = true;
                } else {
                    $operations = HL\HighloadBlockRightsTable::getOperationsName($entityId);
                    if (!empty($operations)) {
                        $canWrite  = in_array('hl_element_write', $operations);
                        $canDelete = in_array('hl_element_delete', $operations);
                    }
                }
            }
        }

        if (!$canWrite && !$canDelete) {
            $this->addError(new Error(Loc::getMessage('UPLAB_API.ACCESS_DENIED')));
            return [];
        }

        $compiled        = HL\HighloadBlockTable::compileEntity($entityId);
        $entityDataClass = $compiled->getDataClass();
        $errors          = [];

        foreach ($ids as $id) {
            $id = (int)$id;
            if ($id <= 0) {
                continue;
            }

            if ($action === 'activate' || $action === 'deactivate') {
                if (!$canWrite) {
                    $this->addError(new Error(Loc::getMessage('UPLAB_API.ACCESS_DENIED')));
                    return [];
                }
                $result = $entityDataClass::update($id, ['UF_ACTIVE' => $action === 'activate' ? '1' : '0']);
            } elseif ($action === 'delete') {
                if (!$canDelete) {
                    $this->addError(new Error(Loc::getMessage('UPLAB_API.ACCESS_DENIED')));
                    return [];
                }
                $result = $entityDataClass::delete($id);
            } else {
                $this->addError(new Error('Unknown action: ' . $action));
                return [];
            }

            if (!$result->isSuccess()) {
                foreach ($result->getErrors() as $error) {
                    $errors[] = $error->getMessage();
                }
            }
        }

        if (!empty($errors)) {
            foreach ($errors as $msg) {
                $this->addError(new Error($msg));
            }
        }

        return [];
    }

    public function deleteElementAction(int $elementId): array
    {
        if (!check_bitrix_sessid()) {
            $this->addError(new Error(Loc::getMessage('UPLAB_API.ACCESS_DENIED')));
            return [];
        }

        if (!$elementId) {
            $this->addError(new Error("Empty ID"));
            return [];
        }

        $canDelete = false;
        $entityId = 0;
        if ($this->getCurrentUser() && Loader::includeModule('highloadblock')) {
            /** @var \Uplab\Api\Model\Content\ContentTable $entity */
            $entity = $this->getSourceEntity();
            $HLBlockName = $entity::getHLBlockName();
            $hlblock = \Uplab\Api\Model\Hlblock\HlblockService::getHlblockByName($HLBlockName);
            $entityId = $hlblock['ID'] ?? null;
            if (($entityId !== null) && !empty($hlblock)) {
                //check rights
                if ($this->getCurrentUser()->isAdmin()) {
                    $canDelete = true;
                } else {
                    $operations = HL\HighloadBlockRightsTable::getOperationsName($entityId);
                    if (!empty($operations)) {
                        $canDelete = in_array('hl_element_delete', $operations);
                    }
                }
            }
        }

        if (!$canDelete) {
            $this->addError(new Error(Loc::getMessage("UPLAB_API.ACCESS_DENIED")));
        } else {
            // get entity
            $entity = HL\HighloadBlockTable::compileEntity($entityId);
            /** @var HL\DataManager $entity_data_class */
            $entity_data_class = $entity->getDataClass();
            $result = $entity_data_class::delete($elementId);
            if (!$result->isSuccess()) {
                foreach ($result->getErrors() as $error) {
                    $this->addError(new Error($error->getMessage()));
                }
            }
        }

        return [];
    }
}
