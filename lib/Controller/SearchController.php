<?php

namespace Uplab\Api\Controller;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\ArgumentTypeException;
use Bitrix\Main\Engine\Response\Json;
use Bitrix\Main\LoaderException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use Uplab\Api\Model\Search;

class SearchController extends BaseController
{
    /**
     * Метод end-point для получения информации о меню в формате для FRONT.
     * @return Json
     * @throws ArgumentTypeException
     * @throws LoaderException
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public function getAction(): Json
    {
        return $this->getModelClass()->getAction($this->getJsonRequestBody() ?? []);
    }

    /**
     * Модель для получения данных
     * @return Search
     */
    protected function getModelClass()
    {
        return new Search();
    }
}