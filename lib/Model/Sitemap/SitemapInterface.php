<?php

namespace Uplab\Api\Model\Sitemap;

use Bitrix\Main\Engine\Response\Json;
use Uplab\Api\Utils\Config\BaseConfig;

/**
 * Контракт модели для генерации данных sitemap.
 *
 * Реализуйте этот интерфейс, чтобы подставить собственную логику сбора URL
 * через {@see SitemapController::getModelClass()} без наследования от {@see Sitemap}.
 */
interface SitemapInterface
{
    /**
     * Возвращает JSON-ответ с данными для sitemap.xml.
     *
     * @param mixed|BaseConfig|null $config Конфиг запроса. Передаётся контроллером.
     * @return Json
     */
    public function getData(mixed $config = null): Json;
}
