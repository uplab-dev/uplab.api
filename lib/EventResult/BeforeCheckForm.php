<?php

namespace Uplab\Api\EventResult;

use Bitrix\Main\Event;
use Uplab\Api\Main;

final class BeforeCheckForm extends Event
{
    public function __construct(
        public readonly int $webFormId,
        public readonly ?array $request,
        public readonly ?array $formData,
    ) {
        parent::__construct(
            Main::MODULE_ID,
            'OnBeforeCheckForm',
        );
    }
}