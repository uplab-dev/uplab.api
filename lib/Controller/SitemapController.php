<?php

namespace Uplab\Api\Controller;

use Uplab\Api\Model\Sitemap\Sitemap;
use Uplab\Api\Model\Sitemap\SitemapInterface;
use Uplab\Api\Utils\Config\BaseConfig;

/**
 * Контроллер эндпоинта `ANY /api/sitemap`.
 *
 * Делегирует генерацию данных в {@see SitemapInterface::getData()}.
 * Для подмены модели достаточно наследоваться и переопределить {@see getModelClass()},
 * вернув любой класс, реализующий {@see SitemapInterface} — наследование от {@see Sitemap} необязательно.
 *
 * @example
 * ```php
 * class ProjectSitemapController extends SitemapController
 * {
 *     protected function getModelClass()
 *     {
 *         return new ProjectSitemap(); // реализует SitemapInterface
 *     }
 * }
 * ```
 */
class SitemapController extends BaseController
{
    /**
     * Возвращает данные для sitemap в формате JSON.
     *
     * Принимает необязательный параметр `siteId` в теле запроса ({@see BaseConfig}).
     * Если `siteId` не передан, используется сайт по умолчанию.
     *
     * @return \Bitrix\Main\Engine\Response\Json
     * @throws \Bitrix\Main\ArgumentTypeException
     * @throws \Bitrix\Main\LoaderException
     */
    public function getAction()
    {
        return $this->getModelClass()->getData(BaseConfig::create($this->getJsonRequestBody()));
    }

    /**
     * Возвращает экземпляр модели для генерации sitemap.
     *
     * Переопределите в наследнике, чтобы подставить любую реализацию {@see SitemapInterface}.
     *
     * @return SitemapInterface
     */
    protected function getModelClass()
    {
        return new Sitemap();
    }
}