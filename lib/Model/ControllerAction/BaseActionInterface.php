<?php

namespace Uplab\Api\Model\ControllerAction;

use Bitrix\Main\Engine\Response\Json;

interface BaseActionInterface
{
    function setErrorResponse(Json $result, string $status, string $message): Json;
}