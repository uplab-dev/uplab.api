<?php

namespace Uplab\Api\Model\ControllerAction;

use Bitrix\Main\ArgumentTypeException;
use Bitrix\Main\Engine\Response\Json;

abstract class BaseAction implements BaseActionInterface
{
    /**
     * Sets error response.
     *
     * @param Json $result
     * @param string $status
     * @param string $message
     *
     * @return Json
     * @throws ArgumentTypeException
     */
    public function setErrorResponse(Json $result, string $status, string $message): Json
    {
        $result->setStatus($status);
        // Тело ошибки — валидный JSON (Content-Type ответа — application/json).
        $result->setData(['message' => $message]);

        return $result;
    }
}