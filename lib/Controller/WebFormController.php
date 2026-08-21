<?php

namespace Uplab\Api\Controller;

use Bitrix\Main\Engine\ActionFilter\HttpMethod;
use Uplab\Api\Model\WebForm\WebFormService;
use Uplab\Api\Utils\Config\BaseConfig;

class WebFormController extends BaseController
{
    public function configureActions(): array
    {
        return [
            'addNewResult' => [
                '+prefilters' => [new HttpMethod([HttpMethod::METHOD_POST])],
            ],
        ];
    }

    /**
     * End-point для получения объекта с информацией, описывающей web-форму
     * @param string $code
     * @return \Bitrix\Main\Engine\Response\Json
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ArgumentTypeException
     * @throws \Bitrix\Main\LoaderException
     * @throws \Bitrix\Main\SystemException
     */
    public function getWebFormAction(string $code)
    {
        return $this->getModelClass()->getFormData($code, BaseConfig::create($this->getJsonRequestBody()));
    }

    /**
     * End-point для получения полей web-формы
     * @param string $code
     * @return \Bitrix\Main\Engine\Response\Json
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ArgumentTypeException
     * @throws \Bitrix\Main\LoaderException
     * @throws \Bitrix\Main\SystemException
     */
    public function getWebFormFieldsAction(string $code)
    {
        return $this->getModelClass()->getFormFieldsData($code, BaseConfig::create($this->getJsonRequestBody()));
    }

    /**
     * End-point для добавления нового результат web-формы
     * @param string $code
     * @return \Bitrix\Main\Engine\Response\Json
     * @throws \Bitrix\Main\ArgumentTypeException
     * @throws \Bitrix\Main\LoaderException
     */
    public function addNewResultAction(string $code)
    {
        return $this->getModelClass()->addNewResult($code, BaseConfig::create($this->getJsonRequestBody()), $_REQUEST);
    }

    /**
     * Модель для получения данных
     * @return WebFormService
     */
    protected function getModelClass()
    {
        return new WebFormService();
    }
}
