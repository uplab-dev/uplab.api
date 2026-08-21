<?php

use Bitrix\Main\Loader;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}
/**
 * @global CMain $APPLICATION
 */

$module_id = 'uplab.api';
if (Loader::includeModule($module_id)) {
    $options = new \Uplab\Api\Utils\Options\Options(__FILE__, [
        [
            'DIV'     => 'project',
            'TAB'     => 'Общие настройки',
            'OPTIONS' => [
                'Редактирование данных',
                [
                    'SHOW_PAGE_CONTENT_TAB',
                    'Выводить контентные данные на странице элемента инфоблока',
                    'N',
                    ['checkbox']
                ]
            ]
        ],
        [
            'DIV'     => 'api_access',
            'TAB'     => 'Доступ к API',
            'OPTIONS' => [
                [
                    // Проверка включена, но ни один список не заполнен — ограничений нет.
                    // Состояние выглядит как «защита работает», поэтому показываем предупреждение.
                    'print_row'       => static function (): void {
                        $enabled = \Bitrix\Main\Config\Option::get('uplab.api', 'API_ACCESS_ENABLED', 'N') === 'Y';
                        $origins = trim(\Bitrix\Main\Config\Option::get('uplab.api', 'API_ALLOWED_ORIGINS', ''));
                        $ips     = trim(\Bitrix\Main\Config\Option::get('uplab.api', 'API_ALLOWED_IPS', ''));

                        if (!$enabled || $origins !== '' || $ips !== '') {
                            return;
                        }

                        echo '<div style="padding:8px 12px;margin:0 0 8px;border-left:3px solid #f5a623;'
                            . 'background:#fdf6e3;color:#5a4a12;">'
                            . 'Проверка доступа включена, но оба списка пусты — ограничения не действуют, '
                            . 'запросы к API проходят как при выключенной проверке. Заполните список Origin '
                            . '(для браузерных запросов) и/или список IP (для серверных запросов без Origin).'
                            . '</div>';
                    },
                    'print_row_no_tr' => false,
                ],
                [
                    'API_ACCESS_ENABLED',
                    'Включить ограничение доступа к API (без заполненных списков ниже ограничений нет)',
                    'N',
                    ['checkbox'],
                ],
                [
                    'API_ALLOWED_ORIGINS',
                    'Разрешённые Origin — по одному на строку, для браузерных запросов. Пример: https://site.ru',
                    '',
                    ['textarea', 6, 60],
                ],
                [
                    'API_ALLOWED_IPS',
                    'Разрешённые REMOTE_ADDR — по одному на строку, для серверных запросов без Origin (SSR). Пример: 127.0.0.1',
                    '',
                    ['textarea', 6, 60],
                ],
            ]
        ],
    ]);

    $options->drawOptionsForm();
}


