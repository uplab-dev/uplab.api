<?php

namespace Uplab\Api\Admin;

/**
 * Встроенная документация REST API модуля uplab.api для админ-панели.
 *
 * Отрисовывает Swagger-подобный интерфейс: сворачиваемые операции с бейджами
 * методов, чипами (контроллер/модель/конфиг, кеш, авторизация), таблицами
 * path/query/body-параметров, примерами запроса, автогенерацией curl и
 * примерами ответа. Данные описания перенесены из README.md — при его правках
 * синхронизируйте данные тут.
 */
class Docs
{
    /** Запасной базовый URL для примеров curl, если не удалось определить домен. */
    protected const BASE_URL_FALLBACK = 'https://your-site.ru';

    /**
     * Человекочитаемые описания path-параметров (по имени плейсхолдера).
     *
     * @var array<string, string>
     */
    protected const PATH_PARAM_DESC = [
        'iblockCode'  => 'Символьный код инфоблока',
        'hlblockCode' => 'Символьный код (имя) highload-блока',
        'elementCode' => 'Символьный код элемента',
        'elementId'   => 'ID элемента',
        'sectionCode' => 'Символьный код раздела',
        'sectionId'   => 'ID раздела',
        'menuCode'    => 'Символьный код меню (инфоблок)',
        'pageCode'    => 'Символьный код страницы',
        'code'        => 'Символьный код',
    ];

    /**
     * Карта страниц документации: ключ => [файл в /bitrix/admin/, пункт меню].
     * Порядок определяет порядок пунктов меню и верхней навигации.
     *
     * @return array<string, array{file: string, menu: string, title: string, icon: string}>
     */
    public static function menuItems(): array
    {
        return [
            'overview' => ['file' => 'uplab_api_docs.php',         'menu' => 'Обзор',            'title' => 'Обзор API',              'icon' => '🏠'],
            'iblock'   => ['file' => 'uplab_api_docs_iblock.php',  'menu' => 'Инфоблоки',        'title' => 'Инфоблоки',              'icon' => '🧱'],
            'hlblock'  => ['file' => 'uplab_api_docs_hlblock.php', 'menu' => 'Highload-блоки',   'title' => 'Highload-блоки',         'icon' => '🗄️'],
            'pages'    => ['file' => 'uplab_api_docs_pages.php',   'menu' => 'Статические страницы', 'title' => 'Статические страницы', 'icon' => '📄'],
            'forms'    => ['file' => 'uplab_api_docs_forms.php',   'menu' => 'Веб-формы',        'title' => 'Веб-формы',              'icon' => '📝'],
            'content'  => ['file' => 'uplab_api_docs_content.php', 'menu' => 'Статический контент', 'title' => 'Статический контент',  'icon' => '🧩'],
            'menus'    => ['file' => 'uplab_api_docs_menus.php',   'menu' => 'Меню',             'title' => 'Меню',                   'icon' => '🧭'],
            'search'   => ['file' => 'uplab_api_docs_search.php',  'menu' => 'Поиск',            'title' => 'Поиск',                  'icon' => '🔍'],
            'sitemap'  => ['file' => 'uplab_api_docs_sitemap.php', 'menu' => 'Карта сайта',      'title' => 'Карта сайта',            'icon' => '🗺️'],
            'events'   => ['file' => 'uplab_api_docs_events.php',  'menu' => 'События',          'title' => 'Система событий',        'icon' => '🔔'],
        ];
    }

    /**
     * Отрисовать страницу документации по её ключу.
     * Вызывается между prolog_admin_after.php и epilog_admin.php.
     */
    public static function render(string $key): void
    {
        $pages = static::pages();
        $page = $pages[$key] ?? $pages['overview'];

        // Панель инструментов (поиск + развернуть/свернуть) — только там, где есть операции.
        $hasOps = false;
        foreach ($page['sections'] as $section) {
            if (in_array($section['type'], ['endpoint', 'events'], true)) {
                $hasOps = true;
                break;
            }
        }

        echo static::styles();

        echo '<div class="uplab-docs">';

        // Горизонтальная навигация по разделам (белый блок).
        echo static::topnav($key);

        // Вступительный блок с заголовком и описанием (белый блок).
        echo '<div class="uplab-block uplab-intro">';
        echo '<h2 class="uplab-docs__title">' . htmlspecialcharsbx($page['title']) . '</h2>';
        if (!empty($page['lead'])) {
            echo '<p class="uplab-docs__lead">' . $page['lead'] . '</p>';
        }
        echo '</div>';

        // Панель инструментов (белый блок).
        if ($hasOps) {
            echo static::toolbar();
        }

        echo '<div class="uplab-docs__body">';
        foreach ($page['sections'] as $section) {
            echo static::renderSection($section);
        }
        echo '</div>';

        echo '</div>'; // docs
        echo static::script();
    }

    protected static function toolbar(): string
    {
        return '<div class="uplab-block uplab-toolbar">'
            . '<input type="text" class="uplab-toolbar__search" placeholder="Фильтр по пути или описанию…" '
            . 'oninput="uplabDocsFilter(this.value)">'
            . '<div class="uplab-toolbar__btns">'
            . '<button type="button" class="uplab-toolbar__btn" onclick="uplabDocsToggleAll(true)">Развернуть все</button>'
            . '<button type="button" class="uplab-toolbar__btn" onclick="uplabDocsToggleAll(false)">Свернуть все</button>'
            . '</div></div>';
    }

    protected static function topnav(string $current): string
    {
        $lang = defined('LANGUAGE_ID') ? LANGUAGE_ID : 'ru';

        $html = '<nav class="uplab-block uplab-topnav">';
        foreach (static::menuItems() as $key => $item) {
            $active = $key === $current ? ' is-active' : '';
            $url = htmlspecialcharsbx($item['file'] . '?lang=' . $lang);
            $html .= '<a class="uplab-topnav__item' . $active . '" href="' . $url . '" title="' . htmlspecialcharsbx($item['title']) . '">'
                . '<span class="uplab-topnav__ico">' . $item['icon'] . '</span>'
                . '<span class="uplab-topnav__label">' . htmlspecialcharsbx($item['menu']) . '</span>'
                . '</a>';
        }
        $html .= '</nav>';

        return $html;
    }

    protected static function renderSection(array $section): string
    {
        return match ($section['type']) {
            'endpoint' => static::renderEndpoint($section),
            'events'   => static::renderEvents($section),
            'note'     => '<div class="uplab-docs-note">' . $section['text'] . '</div>',
            'html'     => $section['html'],
            'heading'  => '<h3 class="uplab-docs-h3">' . htmlspecialcharsbx($section['text']) . '</h3>',
            'table'    => static::renderParamsTable($section['rows'], $section['head'] ?? null),
            default    => '',
        };
    }

    protected static function renderEndpoint(array $s): string
    {
        $method = $s['method'] ?? 'ANY';
        $badge = strtolower($method);
        $summary = $s['summary'] ?? static::deriveSummary($s['desc'] ?? '');
        $filterKey = htmlspecialcharsbx(mb_strtolower(($s['path'] ?? '') . ' ' . strip_tags($summary)));
        $id = static::anchorId($method, $s['path'] ?? '');

        $html = '<div class="uplab-op uplab-op--' . htmlspecialcharsbx($badge) . ' is-collapsed" id="' . htmlspecialcharsbx($id) . '" data-filter="' . $filterKey . '">';

        // Заголовок-переключатель.
        $html .= '<div class="uplab-op__summary" role="button" tabindex="0" onclick="uplabDocsToggle(this)">'
            . '<span class="uplab-badge uplab-badge--' . htmlspecialcharsbx($badge) . '">' . htmlspecialcharsbx($method) . '</span>'
            . '<code class="uplab-op__path">' . static::highlightPath($s['path'] ?? '') . '</code>'
            . '<span class="uplab-op__summary-text">' . htmlspecialcharsbx($summary) . '</span>'
            . '<a class="uplab-op__anchor" href="#' . htmlspecialcharsbx($id) . '" title="Скопировать ссылку на раздел" onclick="uplabDocsAnchor(event, this)">#</a>'
            . '<span class="uplab-op__chevron" aria-hidden="true"></span>'
            . '</div>';

        // Тело операции.
        $html .= '<div class="uplab-op__body">';

        // Полное описание (если отличается от summary).
        if (!empty($s['desc'])) {
            $html .= '<div class="uplab-op__desc">' . $s['desc'] . '</div>';
        }

        // Чипы: контроллер/модель/конфиг, кеш, авторизация.
        $html .= static::renderChips($s);

        // Значения по умолчанию (структурированный блок).
        if (!empty($s['defaults'])) {
            $html .= static::renderDefaults($s['defaults']);
        }

        // Path-параметры (извлекаются из пути автоматически).
        $pathParams = static::extractPathParams($s['path'] ?? '', $s['pathParams'] ?? []);
        if ($pathParams) {
            $html .= '<div class="uplab-op__section-label">Path-параметры</div>';
            $html .= static::renderParamsTable($pathParams, ['Параметр', 'Тип', 'Обяз.', 'Описание']);
        }

        // Query-параметры.
        if (!empty($s['queryParams'])) {
            $html .= '<div class="uplab-op__section-label">Query-параметры</div>';
            $html .= static::renderParamsTable($s['queryParams'], $s['queryParamsHead'] ?? null);
        }

        // Параметры тела запроса.
        if (!empty($s['params'])) {
            $html .= '<div class="uplab-op__section-label">Параметры тела запроса (JSON)</div>';
            $html .= static::renderParamsTable($s['params'], $s['paramsHead'] ?? null);
        }

        // Примечания.
        foreach (($s['notes'] ?? []) as $note) {
            $html .= '<div class="uplab-docs-note">' . $note . '</div>';
        }

        // Пример тела запроса.
        if (!empty($s['example'])) {
            $html .= static::renderCode($s['example'], 'Тело запроса', 'json');
        }

        // curl.
        $html .= static::renderCode(static::buildCurl($method, $s['path'] ?? '', $s['example'] ?? null), 'Пример запроса (curl)', 'bash');

        // Ответы.
        $html .= static::renderResponses($s);

        $html .= '</div>'; // body
        $html .= '</div>'; // op

        return $html;
    }

    protected static function renderChips(array $s): string
    {
        $chips = [];
        foreach (($s['meta'] ?? []) as $label => $value) {
            $chips[] = '<span class="uplab-chip"><span class="uplab-chip__label">' . htmlspecialcharsbx((string)$label) . '</span>'
                . '<code>' . htmlspecialcharsbx((string)$value) . '</code></span>';
        }
        if (!empty($s['cache'])) {
            $chips[] = '<span class="uplab-chip uplab-chip--cache" title="Кеширование ответа">⏱ ' . htmlspecialcharsbx($s['cache']) . '</span>';
        }
        if (!empty($s['auth'])) {
            $chips[] = '<span class="uplab-chip uplab-chip--auth" title="Требуется авторизация">🔒 ' . htmlspecialcharsbx($s['auth']) . '</span>';
        }

        return $chips ? '<div class="uplab-op__chips">' . implode('', $chips) . '</div>' : '';
    }

    /**
     * @param array<int, array<int, string>> $rows
     * @param array<int, string>|null $head
     */
    protected static function renderParamsTable(array $rows, ?array $head = null): string
    {
        $cols = 0;
        foreach ($rows as $row) {
            $cols = max($cols, count($row));
        }
        if ($head === null) {
            $head = $cols >= 4
                ? ['Параметр', 'Тип', 'По умолчанию', 'Описание']
                : ['Параметр', 'Тип', 'Описание'];
        }

        $html = '<div class="uplab-table-wrap"><table class="uplab-table"><thead><tr>';
        foreach ($head as $th) {
            $html .= '<th>' . htmlspecialcharsbx($th) . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $html .= '<tr>';
            for ($i = 0; $i < $cols; $i++) {
                $cell = $row[$i] ?? '';
                if ($i === 0) {
                    $html .= '<td><code>' . htmlspecialcharsbx($cell) . '</code></td>';
                } elseif ($i === 1) {
                    $html .= '<td><span class="uplab-type">' . htmlspecialcharsbx($cell) . '</span></td>';
                } elseif ($cell === 'да' || $cell === 'нет') {
                    $cls = $cell === 'да' ? 'uplab-req uplab-req--yes' : 'uplab-req';
                    $html .= '<td><span class="' . $cls . '">' . htmlspecialcharsbx($cell) . '</span></td>';
                } else {
                    $html .= '<td>' . $cell . '</td>';
                }
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table></div>';

        return $html;
    }

    protected static function renderCode(string $code, string $title = '', string $lang = ''): string
    {
        $html = '<div class="uplab-code">';
        $html .= '<div class="uplab-code__bar">';
        if ($title !== '') {
            $html .= '<span class="uplab-code__title">' . htmlspecialcharsbx($title) . '</span>';
        }
        if ($lang !== '') {
            $html .= '<span class="uplab-code__lang">' . htmlspecialcharsbx($lang) . '</span>';
        }
        $html .= '<button type="button" class="uplab-code__copy" onclick="uplabDocsCopy(this)">Копировать</button>';
        $html .= '</div>';
        $html .= '<pre><code class="uplab-hl">' . static::highlight($code, $lang) . '</code></pre>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Лёгкая серверная подсветка синтаксиса (без внешних библиотек).
     * Возвращает безопасный HTML: сначала экранирует, затем расставляет span-ы.
     */
    protected static function highlight(string $code, string $lang): string
    {
        $esc = htmlspecialcharsbx($code);

        $out = match ($lang) {
            'json'  => static::hlJson($esc),
            'bash'  => static::hlBash($esc),
            'php'   => static::hlPhp($esc),
            default => $esc,
        };

        return static::hlPlaceholders($out);
    }

    /**
     * Подсветка плейсхолдеров: {параметр} и макросы вида #YEAR#.
     * Применяется поверх уже расставленных span-ов (совпадения не попадают в разметку).
     */
    protected static function hlPlaceholders(string $html): string
    {
        $html = preg_replace('/\{[A-Za-z][\w]*\}/', '<span class="hl-ph">$0</span>', $html);

        return preg_replace('/#[A-Z][A-Z0-9_]*#/', '<span class="hl-ph">$0</span>', $html);
    }

    protected static function hlJson(string $esc): string
    {
        return preg_replace_callback(
            '/&quot;(?:[^&]|&(?!quot;))*&quot;(\s*:)?|\b(?:true|false|null)\b|-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?/',
            static function (array $m): string {
                $t = $m[0];
                if (str_starts_with($t, '&quot;')) {
                    if (!empty($m[1])) {
                        $q = substr($t, 0, -strlen($m[1]));

                        return '<span class="hl-key">' . $q . '</span>' . $m[1];
                    }

                    return '<span class="hl-str">' . $t . '</span>';
                }
                if ($t === 'true' || $t === 'false' || $t === 'null') {
                    return '<span class="hl-lit">' . $t . '</span>';
                }

                return '<span class="hl-num">' . $t . '</span>';
            },
            $esc
        );
    }

    protected static function hlBash(string $esc): string
    {
        return preg_replace_callback(
            '/(&#0?39;(?:[^&]|&(?!#0?39;))*&#0?39;)|(#[^\n]*)|(?<![\w-])(-{1,2}[A-Za-z][\w-]*)|\b(curl)\b/m',
            static function (array $m): string {
                if (($m[1] ?? '') !== '') {
                    return '<span class="hl-str">' . $m[1] . '</span>';
                }
                if (($m[2] ?? '') !== '') {
                    return '<span class="hl-comment">' . $m[2] . '</span>';
                }
                if (($m[3] ?? '') !== '') {
                    return '<span class="hl-flag">' . $m[3] . '</span>';
                }
                if (($m[4] ?? '') !== '') {
                    return '<span class="hl-cmd">' . $m[4] . '</span>';
                }

                return $m[0];
            },
            $esc
        );
    }

    protected static function hlPhp(string $esc): string
    {
        $keywords = 'public|protected|private|static|function|return|new|if|else|elseif|foreach|as|class|extends|implements|void|array|string|int|bool|float|use|namespace|try|catch|throw|true|false|null';

        return preg_replace_callback(
            '~(/\*.*?\*/|//[^\n]*)|(&quot;(?:[^&]|&(?!quot;))*&quot;|&#0?39;(?:[^&]|&(?!#0?39;))*&#0?39;)|(\$[A-Za-z_]\w*)|\b(' . $keywords . ')\b~s',
            static function (array $m): string {
                if (($m[1] ?? '') !== '') {
                    return '<span class="hl-comment">' . $m[1] . '</span>';
                }
                if (($m[2] ?? '') !== '') {
                    return '<span class="hl-str">' . $m[2] . '</span>';
                }
                if (($m[3] ?? '') !== '') {
                    return '<span class="hl-var">' . $m[3] . '</span>';
                }
                if (($m[4] ?? '') !== '') {
                    return '<span class="hl-kw">' . $m[4] . '</span>';
                }

                return $m[0];
            },
            $esc
        );
    }

    protected static function renderResponses(array $s): string
    {
        $html = '<div class="uplab-op__section-label">Ответы</div>';
        $html .= '<div class="uplab-resp">';

        // 200.
        $html .= '<div class="uplab-resp__row">'
            . '<span class="uplab-status uplab-status--ok">200</span>'
            . '<span class="uplab-resp__desc">Успех. Данные — в теле ответа.</span>'
            . '</div>';

        if (!empty($s['responseExample'])) {
            $html .= static::renderCode($s['responseExample'], 'Пример ответа', 'json');
        }

        // Дополнительные коды.
        foreach (($s['responses'] ?? []) as $resp) {
            $cls = ((int)$resp['code'] >= 400) ? 'uplab-status--err' : 'uplab-status--ok';
            $html .= '<div class="uplab-resp__row">'
                . '<span class="uplab-status ' . $cls . '">' . htmlspecialcharsbx((string)$resp['code']) . '</span>'
                . '<span class="uplab-resp__desc">' . $resp['desc'] . '</span>'
                . '</div>';
        }

        $html .= '<div class="uplab-resp__note">Общие коды ошибок (400 / 403 / 404 / 500) и формат ответа — в разделе '
            . '<b>«Обзор» → «Формат ответа»</b>.</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * @param array<int, array{label: string, value: string, tone?: string}> $rows
     */
    protected static function renderDefaults(array $rows): string
    {
        $html = '<div class="uplab-defaults">';
        $html .= '<div class="uplab-defaults__title">Значения по умолчанию</div>';
        $html .= '<dl class="uplab-defaults__list">';
        foreach ($rows as $row) {
            $tone = !empty($row['tone']) ? ' uplab-defaults__row--' . htmlspecialcharsbx($row['tone']) : '';
            $html .= '<div class="uplab-defaults__row' . $tone . '">'
                . '<dt>' . htmlspecialcharsbx($row['label']) . '</dt>'
                . '<dd>' . $row['value'] . '</dd>'
                . '</div>';
        }
        $html .= '</dl></div>';

        return $html;
    }

    protected static function renderEvents(array $s): string
    {
        $html = '';
        foreach ($s['events'] as $event) {
            $filterKey = htmlspecialcharsbx(mb_strtolower($event['name'] . ' ' . strip_tags($event['desc'] ?? '')));
            $id = static::anchorId('event', $event['name']);
            $html .= '<div class="uplab-op uplab-op--event is-collapsed" id="' . htmlspecialcharsbx($id) . '" data-filter="' . $filterKey . '">';
            $html .= '<div class="uplab-op__summary" role="button" tabindex="0" onclick="uplabDocsToggle(this)">'
                . '<span class="uplab-badge uplab-badge--event">EVENT</span>'
                . '<code class="uplab-op__path">' . htmlspecialcharsbx($event['name']) . '</code>'
                . '<span class="uplab-op__summary-text">' . htmlspecialcharsbx(static::deriveSummary($event['desc'] ?? '')) . '</span>'
                . '<a class="uplab-op__anchor" href="#' . htmlspecialcharsbx($id) . '" title="Скопировать ссылку на раздел" onclick="uplabDocsAnchor(event, this)">#</a>'
                . '<span class="uplab-op__chevron" aria-hidden="true"></span>'
                . '</div>';
            $html .= '<div class="uplab-op__body">';
            if (!empty($event['params'])) {
                $html .= '<div class="uplab-op__chips"><span class="uplab-chip"><span class="uplab-chip__label">Параметры</span>'
                    . '<code>' . htmlspecialcharsbx($event['params']) . '</code></span></div>';
            }
            if (!empty($event['desc'])) {
                $html .= '<div class="uplab-op__desc">' . $event['desc'] . '</div>';
            }
            if (!empty($event['example'])) {
                $html .= static::renderCode($event['example'], 'Пример обработчика', 'php');
            }
            $html .= '</div></div>';
        }

        return $html;
    }

    // --- Вспомогательные методы ------------------------------------------------

    protected static function deriveSummary(string $descHtml): string
    {
        $text = trim(strip_tags($descHtml));
        // Первое предложение.
        if (preg_match('/^(.+?[.!?])(\s|$)/u', $text, $m)) {
            $text = $m[1];
        }
        if (mb_strlen($text) > 90) {
            $text = mb_substr($text, 0, 87) . '…';
        }

        return $text;
    }

    /**
     * Стабильный якорный id для операции/события (для ссылок с #hash).
     */
    protected static function anchorId(string $prefix, string $value): string
    {
        $slug = mb_strtolower($prefix . '-' . $value);
        $slug = str_replace(['{', '}'], '', $slug);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim((string)$slug, '-');

        return $slug !== '' ? $slug : $prefix;
    }

    protected static function highlightPath(string $path): string
    {
        $escaped = htmlspecialcharsbx($path);

        return preg_replace('/(\{[^}]+\})/u', '<span class="uplab-op__param">$1</span>', $escaped);
    }

    /**
     * Извлекает path-параметры из шаблона пути и дополняет описаниями.
     *
     * @param array<string, string> $overrides имя => описание
     * @return array<int, array<int, string>>
     */
    protected static function extractPathParams(string $path, array $overrides = []): array
    {
        if (!preg_match_all('/\{([^}]+)\}/u', $path, $m)) {
            return [];
        }
        $rows = [];
        foreach ($m[1] as $name) {
            $desc = $overrides[$name]
                ?? static::PATH_PARAM_DESC[$name]
                ?? 'Параметр пути';
            $type = (str_ends_with($name, 'Id')) ? 'int' : 'string';
            $rows[] = [$name, $type, 'да', $desc];
        }

        return $rows;
    }

    /**
     * Базовый URL для примеров: домен текущего запроса.
     */
    protected static function baseUrl(): string
    {
        try {
            $request = \Bitrix\Main\Context::getCurrent()?->getRequest();
            $host = $request?->getHttpHost();
            if ($host) {
                $scheme = $request->isHttps() ? 'https' : 'http';

                return $scheme . '://' . $host;
            }
        } catch (\Throwable) {
            // ниже — запасной вариант
        }

        return static::BASE_URL_FALLBACK;
    }

    protected static function buildCurl(string $method, string $path, ?string $body): string
    {
        $httpMethod = $method === 'ANY' ? 'POST' : $method;
        $url = static::baseUrl() . '/' . ltrim($path, '/');

        $curl = "curl -X {$httpMethod} '{$url}' \\\n  -H 'Content-Type: application/json'";
        if ($body !== null && $body !== '') {
            $curl .= " \\\n  -d '" . $body . "'";
        }

        return $curl;
    }

    // --- Данные страниц --------------------------------------------------------

    /**
     * @return array<string, array{title: string, lead?: string, sections: array}>
     */
    /**
     * Структурированный блок «по умолчанию» для эндпоинтов инфоблоков.
     * Сверено с IblockService::getArFilterForList() / getArOrderForList() и конфигами.
     *
     * @return array<int, array{label: string, value: string, tone?: string}>
     */
    protected static function iblockDefaults(bool $withList = true): array
    {
        $rows = [
            [
                'label' => 'Проверка прав',
                'tone'  => 'lock',
                'value' => '<code>CHECK_PERMISSIONS=Y</code>, <code>MIN_PERMISSION=PUBLIC_READ</code> — '
                    . '<b>всегда включена, отключить через <code>filter</code> нельзя.</b> Кеш разделён по '
                    . 'отсортированным группам текущего пользователя (<code>CACHE_GROUPS=Y</code>), а для '
                    . 'инфоблоков в расширенном режиме прав (<code>RIGHTS_MODE=E</code>) — ещё и по самому '
                    . 'пользователю: там права выдаются персонально (<code>U&lt;ID&gt;</code>), по отделу и на '
                    . '«создателя» (<code>CR</code>).',
            ],
            [
                'label' => 'Базовый фильтр',
                'value' => '<code>ACTIVE=Y</code>, <code>IBLOCK_ACTIVE=Y</code>, <code>IBLOCK_ID</code>, '
                    . '<code>IBLOCK_LID=siteId</code>. Переданный <code>filter</code> только <b>добавляет</b> условия.',
            ],
            [
                'label' => 'Управляющие ключи',
                'tone'  => 'lock',
                'value' => 'С верхнего уровня переданного <code>filter</code> вырезаются ключи, которые меняют способ '
                    . 'сборки запроса, а не добавляют условие: <code>LOGIC</code>, <code>CHECK_PERMISSIONS</code>, '
                    . '<code>MIN_PERMISSION</code>, <code>PERMISSIONS_BY</code>, <code>SHOW_HISTORY</code>, '
                    . '<code>SHOW_NEW</code>, <code>SHOW_BP_NEW</code>, <code>CHECK_BP_PERMISSIONS</code> '
                    . '(префикс-оператор не помогает: <code>!CHECK_PERMISSIONS</code> тоже вырезается). '
                    . 'Во вложенных группах <code>LOGIC</code> работает — группа присоединяется через <code>AND</code> '
                    . 'и может только сузить выборку.',
            ],
        ];
        if ($withList) {
            $rows[] = [
                'label' => 'Сортировка',
                'value' => '<code>{"SORT":"ASC","NAME":"ASC"}</code> — если <code>order</code> не передан '
                    . '(переданный заменяет её целиком).',
            ];
            $rows[] = [
                'label' => 'Пагинация',
                'value' => '<code>limit=10</code>, <code>offset=0</code> — если не переданы; максимум '
                    . '<code>limit=500</code>, <code>offset=100000</code>.',
            ];
        }

        return $rows;
    }

    /**
     * Описание ключа BREADCRUMBS в детальных ответах инфоблока.
     * Сверено с IblockService::getIblockElementData() / getIblockSectionData().
     *
     * @param bool $forSection true — для детальной раздела, false — для детальной элемента
     * @return array<int, string>
     */
    protected static function breadcrumbsNotes(bool $forSection): array
    {
        $common = '<b>Хлебные крошки (<code>BREADCRUMBS</code>).</b> Детальный ответ содержит готовую цепочку '
            . 'навигации — массив звеньев <code>{ "TEXT": …, "HREF": … }</code> в порядке от корня к текущей странице. '
            . 'Параметрами не управляется, формируется всегда и кешируется вместе с ответом. В списках '
            . '(<code>/elements</code>, <code>/sections</code>) и в <code>/api/pages/{code}</code> цепочка не строится.';

        if ($forSection) {
            return [
                $common,
                '<b>Состав цепочки.</b> Первое звено — инфоблок (<code>TEXT</code> = его название, <code>HREF</code> = '
                . '<code>LIST_PAGE_URL</code>), далее — цепочка навигации <code>CIBlockSection::GetNavChain()</code>, '
                . 'включая сам раздел: <code>TEXT</code> = SEO-заголовок <code>IPROPERTY_VALUES.SECTION_PAGE_TITLE</code>, '
                . 'при пустом — <code>NAME</code>; <code>HREF</code> = <code>SECTION_PAGE_URL</code>, а у звена самого '
                . 'текущего раздела <code>HREF</code> — <b>пустая строка</b> (ключ присутствует).',
            ];
        }

        return [
            $common,
            '<b>Состав цепочки.</b> Первое звено — инфоблок (<code>TEXT</code> = его название, <code>HREF</code> = '
            . '<code>LIST_PAGE_URL</code>; добавляется, только если инфоблок определён), далее — разделы от корня '
            . 'до раздела элемента (<code>CIBlockSection::GetNavChain()</code> по <code>IBLOCK_SECTION_ID</code>): '
            . '<code>TEXT</code> = <code>IPROPERTY_VALUES.SECTION_PAGE_TITLE</code>, при пустом — <code>NAME</code>, '
            . '<code>HREF</code> = <code>SECTION_PAGE_URL</code>. Последнее звено — сам элемент: <code>TEXT</code> = '
            . '<code>IPROPERTY_VALUES.ELEMENT_PAGE_TITLE</code>, при пустом — <code>NAME</code>; <b>ключа '
            . '<code>HREF</code> у него нет</b> (не пустая строка, а отсутствующий ключ).',
            '<b><code>SECTION_URL</code>.</b> Детальная элемента дополнительно отдаёт URL последнего раздела цепочки '
            . '(пустая строка, если элемент вне разделов).',
        ];
    }

    public static function pages(): array
    {
        return [
            'overview' => static::pageOverview(),
            'iblock'   => static::pageIblock(),
            'hlblock'  => static::pageHlblock(),
            'pages'    => static::pagePages(),
            'forms'    => static::pageForms(),
            'content'  => static::pageContent(),
            'menus'    => static::pageMenus(),
            'search'   => static::pageSearch(),
            'sitemap'  => static::pageSitemap(),
            'events'   => static::pageEvents(),
        ];
    }

    protected static function pageOverview(): array
    {
        $successExample = "{\n  \"items\": [ /* ... */ ],\n  \"meta\": { \"total\": 128, \"limit\": 10, \"offset\": 0 }\n}";
        $errorExample = "{\n  \"message\": \"Инфоблок не найден.\"\n}";
        $apiErrorExample = "{\n  \"type\": \"about:blank\",\n  \"title\": \"Not Found\",\n  \"status\": 404,\n  \"detail\": \"Форма не найдена.\"\n}";
        $internalErrorExample = "{\n  \"type\": \"about:blank\",\n  \"title\": \"Internal Server Error\",\n  \"status\": 500,\n  \"detail\": \"Внутренняя ошибка сервера.\",\n  \"code\": \"UPLAB_API_INTERNAL_ERROR\"\n}";
        $envelopeExample = "{\n  \"status\": \"success\",\n  \"data\": { /* полезная нагрузка */ },\n  \"errors\": []\n}";

        return [
            'title' => 'uplab.api — REST API',
            'lead'  => 'Модуль <code>uplab.api</code> предоставляет REST API для получения данных из Bitrix: '
                . 'инфоблоки, highload-блоки, веб-формы, меню, поиск, карта сайта, статический контент. '
                . 'Неймспейс <code>Uplab\\Api\\</code>, PHP ≥ 8.1, язык по умолчанию <code>ru</code>. '
                . 'Базовый URL всех endpoint-ов: <code>/api/…</code>',
            'sections' => [
                ['type' => 'heading', 'text' => 'Общие принципы'],
                [
                    'type' => 'note',
                    'text' => '<b>ANY</b> означает, что маршрут принимает любой HTTP-метод (GET, POST и т.д.). '
                        . '<b>Все параметры</b> передаются через JSON body (<code>Content-Type: application/json</code>), '
                        . 'в том числе для ANY/GET запросов — они парсятся через <code>file_get_contents(\'php://input\')</code>. '
                        . 'Path-параметры (в фигурных скобках) передаются в самом URL.',
                ],
                [
                    'type' => 'note',
                    'text' => 'Общий необязательный параметр для всех endpoint-ов: <code>siteId</code> (string) — ID сайта Bitrix. '
                        . 'По умолчанию <code>SITE_ID</code> текущего сайта; допустимы 1–32 символа: латиница, цифры, <code>_</code> и <code>-</code>; пустая строка — фильтр по сайту не применяется.',
                ],
                [
                    'type' => 'note',
                    'text' => 'Тело с <code>Content-Type: application/x-www-form-urlencoded</code> или '
                        . '<code>multipart/form-data</code> как JSON не разбирается — такие поля читает сам '
                        . 'endpoint из запроса (отправка веб-формы). Любое другое тело разбирается как JSON '
                        . 'независимо от заголовка: валидный JSON-объект принимается и без <code>Content-Type</code>. '
                        . 'Заголовок влияет только на реакцию на ошибку разбора: при объявленном JSON '
                        . '(<code>application/json</code>, <code>*+json</code>) некорректное или не-объектное тело '
                        . 'даёт <code>400</code>, иначе тело игнорируется и параметры берутся по умолчанию.',
                ],
                ['type' => 'heading', 'text' => 'Ограничения запросов'],
                [
                    'type' => 'note',
                    'text' => 'JSON body — до 64 КиБ и глубины 32 (превышение размера → <code>413</code>); '
                        . '<code>limit=1…500</code>, <code>offset=0…100000</code>; массивы условий — до 1000 элементов '
                        . 'суммарно (с учётом вложенных) и глубины 5; <code>select</code>/<code>propertyCode</code> — до 100 полей; '
                        . '<code>order</code> — до 10 полей, направления <code>ASC</code>, <code>DESC</code>, '
                        . '<code>ASC,NULLS</code>, <code>DESC,NULLS</code>, <code>NULLS,ASC</code>, <code>NULLS,DESC</code>; '
                        . 'имя поля — до 128 символов, строковое значение условия — до 4096. '
                        . 'Прочие нарушения → <code>400</code>.',
                ],
                ['type' => 'heading', 'text' => 'Переопределение лимитов запросов'],
                [
                    'type' => 'note',
                    'text' => 'Лимиты заданы константами <code>MAX_*</code> в <code>BaseConfig</code>, а проверки '
                        . 'обращаются к ним через <code>static::</code>. Чтобы изменить лимит на проекте, унаследуйте '
                        . 'нужный конфиг, переопределите константу и подключите наследника фабрикой класса конфига '
                        . 'в контроллере (<code>get*ConfigClass()</code>) — тела проверок и экшенов копировать не нужно. '
                        . 'Лимит можно как поднять, так и ужесточить; остальные endpoint-ы продолжают работать со '
                        . 'штатными значениями.',
                ],
                ['type' => 'html', 'html' => static::renderCode(
                    "class ProjectListConfig extends \\Uplab\\Api\\Utils\\Config\\Iblock\\IblockListElementsConfig\n{\n    public const MAX_LIMIT = 2000;\n}\n\nclass ProjectIblockController extends \\Uplab\\Api\\Controller\\IblockController\n{\n    protected function getElementListConfigClass(): string\n    {\n        return ProjectListConfig::class;\n    }\n}",
                    'Свой лимит limit для списка элементов',
                    'php'
                )],
                [
                    'type' => 'table',
                    'head' => ['Фабрика', 'Контроллер', 'Endpoint'],
                    'rows' => [
                        ['getIblockConfigClass()', 'IblockController', '/api/iblock/{code}'],
                        ['getElementListConfigClass()', 'IblockController', '/api/iblock/{code}/elements'],
                        ['getElementDetailConfigClass()', 'IblockController', '/api/iblock/{code}/element/code|id/{...}'],
                        ['getSectionListConfigClass()', 'IblockController', '/api/iblock/{code}/sections'],
                        ['getSectionDetailConfigClass()', 'IblockController', '/api/iblock/{code}/section/code|id/{...}'],
                        ['getElementFilterConfigClass()', 'IblockController', '/api/iblock/{code}/filter'],
                        ['getIblockPropertiesConfigClass()', 'IblockController', '/api/iblock/{code}/properties'],
                        ['getElementListConfigClass()', 'HlblockController', '/api/hlblock/{code}/elements'],
                        ['getElementFilterConfigClass()', 'HlblockController', '/api/hlblock/{code}/filter'],
                        ['getFieldsConfigClass()', 'HlblockController', '/api/hlblock/{code}/fields'],
                        ['getPageConfigClass()', 'PageController', '/api/pages/{code}'],
                        ['getMenuConfigClass()', 'MenuController', '/api/menus/{code}'],
                    ],
                ],
                [
                    'type' => 'note',
                    'text' => 'У поиска и административных endpoint-ов контента объекта конфига нет, поэтому там лимиты '
                        . 'меняются переопределением методов модели/контроллера: <code>Search::getMaxLimit()</code>, '
                        . '<code>Search::getMaxOffset()</code>, <code>ContentController::getMaxPageSize()</code>, '
                        . '<code>ContentController::getMaxOffset()</code>, <code>ContentController::getMaxBulkIds()</code>.',
                ],
                [
                    'type' => 'note',
                    'text' => '<b>Тексты ошибок.</b> Сообщения берутся из языковых сообщений модуля '
                        . '(<code>UPLAB_API.ERROR_*</code> в <code>lang/{ru,en}/include.php</code>), лимит подставляется '
                        . 'плейсхолдером <code>#MAX#</code> — в том числе лимит наследника, если проект его поднял. '
                        . 'Формулировку можно изменить на проекте штатным переопределением языковых сообщений Битрикс. '
                        . 'HTTP-статусы задаются кейсами <code>Uplab\\Api\\Enums\\HttpStatus</code>, а не числами.',
                ],
                ['type' => 'heading', 'text' => 'Формат ответа'],
                [
                    'type' => 'note',
                    'text' => 'Сервисные endpoint-ы (инфоблоки, highload-блоки, страницы, формы, меню, поиск, карта сайта) '
                        . 'возвращают в теле ответа <b>сырые данные</b> endpoint-а — <b>без</b> обёртки. При ошибке '
                        . 'возвращается соответствующий HTTP-статус (404/424 и т.п.) и тело <code>{"message": "..."}</code>.',
                ],
                ['type' => 'html', 'html' => static::renderCode($successExample, 'Успешный ответ (пример)', 'json')],
                ['type' => 'html', 'html' => static::renderCode($errorExample, 'Ожидаемая ошибка (напр. 404)', 'json')],
                [
                    'type' => 'note',
                    'text' => '<b>Намеренные клиентские ошибки.</b> Код может бросить '
                        . '<code>Uplab\\Api\\Exception\\ApiException($message, $httpStatus)</code> — ответ будет с указанным '
                        . '<code>4xx</code>-статусом и телом RFC 9457 (<code>application/problem+json</code>).',
                ],
                ['type' => 'html', 'html' => static::renderCode($apiErrorExample, 'ApiException (RFC 9457)', 'json')],
                [
                    'type' => 'note',
                    'text' => '<b>Внутренние ошибки.</b> Любое необработанное исключение/ошибка → <code>HTTP 500</code> и тело '
                        . 'RFC 9457 — <b>без</b> раскрытия классов, сигнатур и трейса. Полные детали пишутся в Журнал событий '
                        . '(тип <code>UPLAB_API_INTERNAL_ERROR</code>), администратору показывается уведомление со ссылкой на '
                        . 'журнал (перехват в <code>BaseController::getActionResponse()</code>; страховка '
                        . '<code>runProcessingException</code>/<code>runProcessingError</code> отдаёт тот же ответ для '
                        . 'исключений, не дошедших до него, в том числе на ядре без этого хука). В журнал попадают только '
                        . 'внутренние ошибки — <code>ApiException</code> (<code>4xx</code>) не логируется. '
                        . '<b>nginx:</b> при <code>fastcgi_intercept_errors on</code> тело <code>5xx</code> подменяется '
                        . 'HTML-страницей nginx — чтобы <code>problem+json</code> доходил на 500, отключите перехват для '
                        . '<code>/api/</code> (см. README). Ответы <code>4xx</code> проходят всегда.',
                ],
                ['type' => 'html', 'html' => static::renderCode($internalErrorExample, 'Внутренняя ошибка (HTTP 500, RFC 9457)', 'json')],
                [
                    'type' => 'note',
                    'text' => 'Исключение — административные endpoint-ы контента (<code>POST /api/content/admin/{pageCode}</code>, '
                        . '<code>POST /api/content/bulk</code>, <code>DELETE /api/content/admin/{elementId}</code>): они построены '
                        . 'на стандартном контроллере Bitrix и используют конверт <code>{status, data, errors}</code>.',
                ],
                ['type' => 'html', 'html' => static::renderCode($envelopeExample, 'Конверт (только content-эндпоинты)', 'json')],
                ['type' => 'heading', 'text' => 'HTTP-коды'],
                [
                    'type' => 'table',
                    'head' => ['Код', 'Значение', 'Когда возникает'],
                    'rows' => [
                        ['200', 'OK', 'Успешный запрос.'],
                        ['201', 'Created', 'Ресурс создан (например, результат веб-формы).'],
                        ['204', 'No Content', 'Успех без тела ответа.'],
                        ['400', 'Bad Request', 'Некорректные параметры запроса.'],
                        ['401', 'Unauthorized', 'Требуется авторизация (админ-endpoint контента).'],
                        ['403', 'Forbidden', 'Доступ запрещён фильтром <code>ApiAccessFilter</code> (Origin/IP) либо нет прав.'],
                        ['404', 'Not Found', 'Инфоблок/элемент/раздел/форма не найдены.'],
                        ['413', 'Content Too Large', 'JSON body превышает 64 КиБ.'],
                        ['422', 'Unprocessable Entity', 'Данные не прошли валидацию.'],
                        ['429', 'Too Many Requests', 'Превышен лимит запросов.'],
                        ['500', 'Internal Server Error', 'Необработанное исключение/ошибка. Тело — RFC 9457 (обобщённое), детали — в Журнале событий (тип <code>UPLAB_API_INTERNAL_ERROR</code>). На bitrix-env тело 5xx может подменяться nginx (см. «Формат ответа»).'],
                    ],
                ],
                ['type' => 'heading', 'text' => 'Архитектура'],
                [
                    'type' => 'note',
                    'text' => 'Три слоя: <b>Контроллеры</b> (<code>lib/Controller/</code>) принимают HTTP-запрос, создают '
                        . 'объект конфига из JSON body и делегируют в сервис; <b>Сервисы</b> (<code>lib/Model/</code>) — '
                        . 'бизнес-логика, запросы к Bitrix API, кеширование; <b>Конфиги</b> (<code>lib/Utils/Config/</code>) — '
                        . 'Fluent-объекты параметров запроса, создаются через <code>ClassName::create(array|null $body)</code>. '
                        . 'Точка входа: <code>include.php</code> → <code>EventManager::bindEvents()</code>. '
                        . 'Все маршруты — <code>lib/Routing/Configurator.php</code>.',
                ],
                ['type' => 'heading', 'text' => 'Доступ к API (настройки модуля)'],
                [
                    'type' => 'note',
                    'text' => 'Вкладка <b>«Доступ к API»</b> в настройках модуля управляет ограничением доступа к публичным '
                        . 'endpoint-ам. По умолчанию всё отключено — ограничений нет. Логика в '
                        . '<code>lib/ActionFilter/ApiAccessFilter.php</code>.',
                ],
                [
                    'type' => 'table',
                    'head' => ['Параметр', 'По умолчанию', 'Описание'],
                    'rows' => [
                        ['API_ACCESS_ENABLED', 'N', 'Включить проверку доступа. При выключенном чекбоксе (по умолчанию) запросы пропускаются; при включённом с двумя пустыми списками — тоже.'],
                        ['API_ALLOWED_ORIGINS', '(пусто)', 'Разрешённые значения <code>Origin</code> — для браузерных запросов. Схема необязательна: <code>site.ru</code> = <code>https://site.ru</code>.'],
                        ['API_ALLOWED_IPS', '(пусто)', 'Разрешённые значения <code>REMOTE_ADDR</code> — для серверных запросов без <code>Origin</code> (Nuxt SSR и т.п.).'],
                    ],
                ],
                [
                    'type' => 'note',
                    'text' => 'Логика проверки: 1) <code>API_ACCESS_ENABLED = N</code> → пропуск (по умолчанию API открыт); '
                        . '2) оба списка пусты → пропуск (правил нет); '
                        . '3) <code>REMOTE_ADDR</code> есть в IP-списке → пропуск; 4) иначе <code>Origin</code> есть в Origin-списке → пропуск; '
                        . '5) не прошёл ни по одному заданному списку → <code>403</code>. Списки работают по «ИЛИ»: браузер проходит по '
                        . '<code>Origin</code>, серверный запрос без заголовка — по IP, поэтому для схемы «браузер + SSR» заполняются оба списка. '
                        . 'Обхода «снять заголовок» нет: без <code>Origin</code> запрос обязан пройти по IP. '
                        . '<code>Origin</code> не является аутентификацией; за reverse proxy ограничивайте доступ на прокси или проверяйте его адрес.',
                ],
            ],
        ];
    }

    protected static function pageIblock(): array
    {
        return [
            'title' => 'Инфоблоки — /api/iblock',
            'lead'  => 'Получение информации об инфоблоках, элементах, разделах, свойствах и данных для фильтров.',
            'sections' => [
                [
                    'type' => 'endpoint', 'method' => 'ANY',
                    'path' => '/api/iblock/{iblockCode}',
                    'summary' => 'Информация об инфоблоке.',
                    'desc' => 'Возвращает мета-данные инфоблока по его символьному коду.',
                    'meta' => ['Контроллер' => 'IblockController::getIblockAction', 'Модель' => 'IblockService', 'Конфиг' => 'IblockDetailConfig'],
                    'cache' => '≈24 часа (тег инфоблока)',
                    'params' => [
                        ['siteId', 'string', 'ID сайта'],
                        ['filter', 'array', 'Дополнительный фильтр'],
                        ['select', 'string[]', 'Список полей для выборки'],
                    ],
                    'defaults' => [
                        [
                            'label' => 'Проверка прав',
                            'tone'  => 'lock',
                            'value' => '<code>CHECK_PERMISSIONS=Y</code>, <code>MIN_PERMISSION=PUBLIC_READ</code>.',
                        ],
                        [
                            'label' => 'Базовый фильтр',
                            'value' => '<code>ACTIVE=Y</code>; при переданном <code>siteId</code> добавляется <code>SITE_ID</code>.',
                        ],
                    ],
                    'example' => "{\n  \"siteId\": \"ru\",\n  \"select\": [\"ID\", \"NAME\", \"CODE\", \"DESCRIPTION\"]\n}",
                ],
                [
                    'type' => 'endpoint', 'method' => 'ANY',
                    'path' => '/api/iblock/{iblockCode}/elements',
                    'summary' => 'Список элементов инфоблока с пагинацией.',
                    'desc' => 'Возвращает список элементов инфоблока с поддержкой фильтрации, сортировки, пагинации и выборки свойств.',
                    'meta' => ['Контроллер' => 'IblockController::getElementListAction', 'Модель' => 'IblockService', 'Конфиг' => 'IblockListElementsConfig'],
                    'cache' => '≈24 часа (тег инфоблока); RAND — 30 секунд',
                    'params' => [
                        ['siteId', 'string', 'SITE_ID', 'ID сайта'],
                        ['filter', 'array', '[]', 'Фильтр в формате Bitrix (<code>{"ACTIVE": "Y"}</code>)'],
                        ['select', 'string[]', '[]', 'Список полей'],
                        ['order', 'array', '{"SORT":"ASC","NAME":"ASC"}', 'Сортировка. Если не передать — применяется значение слева'],
                        ['limit', 'int', '10', 'Кол-во элементов на странице (применяется, если не передать)'],
                        ['offset', 'int', '0', 'Смещение (применяется, если не передать)'],
                        ['propertyCode', 'string[]', '(см. описание)', 'Символьные коды свойств для выборки. <b>Необязательный.</b> Если не передать — выводятся свойства с включённым флагом «Показывать на странице списка элементов»'],
                        ['SHOW_TOTAL', 'Y|N', 'Y', 'Включить <code>total</code> в ответ'],
                        ['SHOW_IPROPERTY_VALUES', 'Y|N', 'N', 'Выводить SEO-поля (iPropertyValues)'],
                        ['HIDE_DISPLAY_VALUES', 'Y|N', 'N', 'Скрыть <code>DISPLAY_VALUES</code> у свойств (уменьшает объём ответа)'],
                        ['SHOW_PREFIX_FIELD_DATA', 'Y|N', 'N', 'Показывать поля с префиксом <code>~</code>'],
                        ['GET_PROPERTIES_PROPERTY', 'object', '{}', 'Свойства связанных элементов: <code>{"VENDORS": ["LOGO"]}</code>'],
                        ['activeDateFormat', 'string', 'null', 'Формат даты <code>ACTIVE_FROM</code> (напр. <code>d.m.Y</code>)'],
                        ['activeToDateFormat', 'string', 'null', 'Формат даты <code>ACTIVE_TO</code>'],
                        ['parentSectionId', 'int', 'null', 'Фильтр по ID родительского раздела'],
                        ['parentSectionCode', 'string', 'null', 'Фильтр по символьному коду родительского раздела'],
                    ],
                    'defaults' => static::iblockDefaults(true),
                    'notes' => [
                        '<b>Случайная сортировка (RAND).</b> При передаче <code>"order": {"RAND": "ASC"}</code> ответ кешируется на '
                        . '<b>30 секунд</b> вместо стандартных ~24 часов. Это позволяет возвращать разные случайные наборы, '
                        . 'не нагружая БД: максимум 1 запрос в 30 секунд на каждую уникальную комбинацию параметров.',
                    ],
                    'example' => "{\n  \"siteId\": \"ru\",\n  \"filter\": { \"ACTIVE\": \"Y\" },\n  \"select\": [\"ID\", \"NAME\", \"CODE\"],\n  \"order\": { \"SORT\": \"ASC\" },\n  \"limit\": 10,\n  \"offset\": 0,\n  \"propertyCode\": [\"PRICE\", \"COLOR\"]\n}",
                    'responseExample' => "{\n  \"items\": [\n    { \"ID\": 42, \"NAME\": \"Товар\", \"CODE\": \"tovar\", \"PROPERTIES\": { } }\n  ],\n  \"meta\": { \"total\": 128, \"limit\": 10, \"offset\": 0 }\n}",
                ],
                [
                    'type' => 'endpoint', 'method' => 'ANY',
                    'path' => '/api/iblock/{iblockCode}/element/code/{elementCode}',
                    'summary' => 'Детальная карточка элемента по символьному коду.',
                    'desc' => 'Возвращает детальную карточку элемента инфоблока по его символьному коду.',
                    'meta' => ['Контроллер' => 'IblockController::getElementDetailByCodeAction', 'Модель' => 'IblockService', 'Конфиг' => 'IblockDetailElementConfig'],
                    'cache' => '≈24 часа (тег инфоблока)',
                    'params' => [
                        ['siteId', 'string', 'ID сайта'],
                        ['filter', 'array', 'Дополнительный фильтр'],
                        ['select', 'string[]', 'Список полей'],
                        ['propertyCode', 'string[]', 'Символьные коды свойств. <b>Необязательный.</b> Если не передать — выводятся свойства с включённым флагом «Показывать на детальной странице элемента»'],
                        ['activeDateFormat', 'string', 'Формат даты <code>ACTIVE_FROM</code>'],
                        ['activeToDateFormat', 'string', 'Формат даты <code>ACTIVE_TO</code>'],
                        ['HIDE_DISPLAY_VALUES', 'Y|N', 'Скрыть <code>DISPLAY_VALUES</code> у свойств'],
                        ['SHOW_PREFIX_FIELD_DATA', 'Y|N', 'Показывать поля с <code>~</code>'],
                        ['GET_PROPERTIES_PROPERTY', 'object', 'Свойства связанных элементов'],
                        ['parentSectionId', 'int', 'ID родительского раздела'],
                        ['parentSectionCode', 'string', 'Символьный код родительского раздела'],
                    ],
                    'defaults' => static::iblockDefaults(false),
                    'notes' => static::breadcrumbsNotes(false),
                    'example' => "{\n  \"siteId\": \"ru\",\n  \"select\": [\"ID\", \"NAME\", \"DETAIL_TEXT\"],\n  \"propertyCode\": [\"PRICE\", \"COLOR\"]\n}",
                    'responseExample' => "{\n  \"ID\": 42,\n  \"NAME\": \"ThinkPad X1\",\n  \"PROPERTIES\": { },\n  \"SECTION_URL\": \"/catalog/noutbuki/\",\n  \"BREADCRUMBS\": [\n    { \"TEXT\": \"Каталог\", \"HREF\": \"/catalog/\" },\n    { \"TEXT\": \"Ноутбуки\", \"HREF\": \"/catalog/noutbuki/\" },\n    { \"TEXT\": \"ThinkPad X1\" }\n  ]\n}",
                    'responses' => [
                        ['code' => 404, 'desc' => 'Элемент не найден.'],
                    ],
                ],
                [
                    'type' => 'endpoint', 'method' => 'ANY',
                    'path' => '/api/iblock/{iblockCode}/element/id/{elementId}',
                    'summary' => 'Детальная карточка элемента по ID.',
                    'desc' => 'Возвращает детальную карточку элемента инфоблока по ID. Параметры тела идентичны варианту с символьным кодом.',
                    'meta' => ['Контроллер' => 'IblockController::getElementDetailByIdAction', 'Конфиг' => 'IblockDetailElementConfig'],
                    'cache' => '≈24 часа (тег инфоблока)',
                    'defaults' => static::iblockDefaults(false),
                    'notes' => static::breadcrumbsNotes(false),
                    'example' => "{\n  \"siteId\": \"ru\"\n}",
                    'responses' => [
                        ['code' => 404, 'desc' => 'Элемент не найден.'],
                    ],
                ],
                [
                    'type' => 'endpoint', 'method' => 'ANY',
                    'path' => '/api/iblock/{iblockCode}/sections',
                    'summary' => 'Список разделов инфоблока с пагинацией.',
                    'desc' => 'Возвращает список разделов инфоблока с фильтрацией, сортировкой и пагинацией.',
                    'meta' => ['Контроллер' => 'IblockController::getSectionListAction', 'Модель' => 'IblockService', 'Конфиг' => 'IblockListSectionsConfig'],
                    'cache' => '≈24 часа (тег инфоблока)',
                    'params' => [
                        ['siteId', 'string', 'SITE_ID', 'ID сайта'],
                        ['filter', 'array', '[]', 'Фильтр'],
                        ['select', 'string[]', '[]', 'Список полей'],
                        ['order', 'array', '{"SORT":"ASC","NAME":"ASC"}', 'Сортировка. Если не передать — применяется значение слева'],
                        ['limit', 'int', '10', 'Кол-во на странице (применяется, если не передать)'],
                        ['offset', 'int', '0', 'Смещение (применяется, если не передать)'],
                        ['bIncCnt', 'bool|array', 'null', 'Параметр <code>bIncCnt</code> для <code>CIBlockSection::GetList()</code>'],
                        ['SHOW_TOTAL', 'Y|N', 'Y', 'Включить <code>total</code> в ответ'],
                        ['parentSectionId', 'int', 'null', 'ID родительского раздела'],
                        ['parentSectionCode', 'string', 'null', 'Символьный код родительского раздела'],
                    ],
                    'defaults' => static::iblockDefaults(true),
                    'example' => "{\n  \"siteId\": \"ru\",\n  \"select\": [\"ID\", \"NAME\", \"CODE\"],\n  \"order\": { \"SORT\": \"ASC\" },\n  \"limit\": 20,\n  \"offset\": 0\n}",
                ],
                [
                    'type' => 'endpoint', 'method' => 'ANY',
                    'path' => '/api/iblock/{iblockCode}/section/code/{sectionCode}',
                    'summary' => 'Детальная информация о разделе по символьному коду.',
                    'desc' => 'Возвращает детальную информацию о разделе инфоблока по символьному коду.',
                    'meta' => ['Контроллер' => 'IblockController::getSectionDetailByCodeAction', 'Модель' => 'IblockService', 'Конфиг' => 'IblockDetailSectionConfig'],
                    'cache' => '≈24 часа (тег инфоблока)',
                    'params' => [
                        ['siteId', 'string', 'ID сайта'],
                        ['filter', 'array', 'Дополнительный фильтр'],
                        ['select', 'string[]', 'Список полей'],
                        ['parentSectionId', 'int', 'ID родительского раздела'],
                        ['parentSectionCode', 'string', 'Символьный код родительского раздела'],
                    ],
                    'defaults' => static::iblockDefaults(false),
                    'notes' => static::breadcrumbsNotes(true),
                    'example' => "{\n  \"siteId\": \"ru\"\n}",
                    'responseExample' => "{\n  \"ID\": 17,\n  \"NAME\": \"Ноутбуки\",\n  \"BREADCRUMBS\": [\n    { \"TEXT\": \"Каталог\", \"HREF\": \"/catalog/\" },\n    { \"TEXT\": \"Ноутбуки\", \"HREF\": \"\" }\n  ]\n}",
                    'responses' => [
                        ['code' => 404, 'desc' => 'Раздел не найден.'],
                    ],
                ],
                [
                    'type' => 'endpoint', 'method' => 'ANY',
                    'path' => '/api/iblock/{iblockCode}/section/id/{sectionId}',
                    'summary' => 'Детальная информация о разделе по ID.',
                    'desc' => 'Возвращает детальную информацию о разделе инфоблока по ID. Параметры тела идентичны варианту с символьным кодом.',
                    'meta' => ['Контроллер' => 'IblockController::getSectionDetailByIdAction', 'Конфиг' => 'IblockDetailSectionConfig'],
                    'cache' => '≈24 часа (тег инфоблока)',
                    'defaults' => static::iblockDefaults(false),
                    'notes' => static::breadcrumbsNotes(true),
                    'example' => "{\n  \"siteId\": \"ru\"\n}",
                    'responses' => [
                        ['code' => 404, 'desc' => 'Раздел не найден.'],
                    ],
                ],
                [
                    'type' => 'endpoint', 'method' => 'ANY',
                    'path' => '/api/iblock/{iblockCode}/filter',
                    'summary' => 'Данные для построения фильтра по элементам.',
                    'desc' => 'Возвращает значения свойств инфоблока, участвующих в умном фильтре '
                        . '(свойство должно быть включено в настройке инфоблока «Показывать в умном фильтре»). '
                        . 'Выводятся только значения, реально использованные в доступных элементах.',
                    'meta' => ['Контроллер' => 'IblockController::getElementFilterAction', 'Модель' => 'IblockProperty', 'Конфиг' => 'IblockFilterConfig'],
                    'cache' => '≈24 часа (тег инфоблока <code>iblock_id_{ID}</code>; для свойств-справочников '
                        . 'дополнительно <code>hlblock_id_{ID}</code>); ключ кеша учитывает переданный <code>filter</code>',
                    'params' => [
                        ['siteId', 'string', 'ID сайта'],
                        ['propertyCode', 'string[]', 'Символьные коды свойств для фильтра'],
                        ['filter', 'array', 'Дополнительный фильтр. Сужает набор значений: значения собираются только по элементам, попадающим под фильтр'],
                        ['select', 'string[]', 'Список полей'],
                        ['parentSectionId', 'int', 'ID раздела'],
                        ['parentSectionCode', 'string', 'Символьный код раздела'],
                    ],
                    'defaults' => static::iblockDefaults(false),
                    'notes' => [
                        '<b>Сужение значений переданным <code>filter</code>.</b> Значения каждого свойства собираются '
                        . 'по элементам, отобранным с учётом <code>filter</code>. При этом условие по самому свойству '
                        . '(<code>PROPERTY_&lt;КОД&gt;</code>) из фильтра исключается — иначе свойство сужало бы собственный '
                        . 'список значений и в фильтре оставался бы только уже выбранный вариант. Исключение работает '
                        . 'по <b>точному совпадению ключа</b>: ключ с префиксом-оператором (<code>&gt;=PROPERTY_PRICE</code>, '
                        . '<code>!PROPERTY_COLOR</code>) не исключается и сузит список значений своего же свойства — '
                        . 'для фасетов передавайте условия по свойству без операторов.',
                        '<b>Проверка прав.</b> Значения собираются с <code>CHECK_PERMISSIONS=Y</code> и '
                        . '<code>MIN_PERMISSION=PUBLIC_READ</code> для всех типов свойств — значения из недоступных '
                        . 'элементов в фильтр не попадают. Это же относится к вторичным выборкам: названия связанных '
                        . 'элементов и разделов (свойства <code>E</code> и <code>G</code>) берутся с проверкой прав на '
                        . 'инфоблок-справочник, поэтому из закрытого инфоблока названия не утекают.',
                        '<b>Поддерживаемые типы свойств:</b> <code>L</code> (список), <code>E</code> (привязка к элементу), '
                        . '<code>G</code> (привязка к разделу), <code>S</code> (строка, в том числе <code>directory</code> — '
                        . 'справочник HL-блока). Свойство без значений в выборке в ответ не попадает. Числовые свойства '
                        . '(<code>N</code>) и пользовательские типы сторонних модулей из коробки не обрабатываются — '
                        . 'они добавляются на проекте (см. «Расширение умного фильтра» ниже).',
                        '<b>Формат <code>items</code>.</b> Для списка (<code>L</code>) <code>value</code> — ID значения '
                        . 'перечисления (<code>ENUM_ID</code>), <code>text</code> — его название; для привязок '
                        . '(<code>E</code>, <code>G</code>) — ID элемента/раздела и его <code>NAME</code>; для справочника '
                        . '(<code>directory</code>) — <code>UF_XML_ID</code> и название значения справочника.',
                    ],
                    'example' => "{\n  \"siteId\": \"ru\",\n  \"propertyCode\": [\"COLOR\", \"BRAND\"],\n  \"filter\": { \"PROPERTY_BRAND\": 42 }\n}",
                    'responseExample' => "[\n  {\n    \"id\": \"105\",\n    \"userType\": null,\n    \"sort\": \"500\",\n    \"propertyType\": \"L\",\n    \"code\": \"COLOR\",\n    \"name\": \"Цвет\",\n    \"hint\": null,\n    \"filterHint\": null,\n    \"multiple\": \"N\",\n    \"searchable\": \"N\",\n    \"displayType\": \"F\",\n    \"items\": [\n      { \"id\": \"12\", \"value\": \"12\", \"text\": \"Красный\" },\n      { \"id\": \"13\", \"value\": \"13\", \"text\": \"Синий\" }\n    ]\n  }\n]",
                ],
                [
                    'type' => 'endpoint', 'method' => 'ANY',
                    'path' => '/api/iblock/{iblockCode}/properties',
                    'summary' => 'Все свойства инфоблока.',
                    'desc' => 'Возвращает список всех свойств инфоблока.',
                    'meta' => ['Контроллер' => 'IblockController::getIblockPropertiesAction', 'Конфиг' => 'IblockListConfig'],
                    'cache' => '≈24 часа (тег инфоблока)',
                    'params' => [
                        ['siteId', 'string', 'ID сайта'],
                        ['filter', 'array', 'Фильтр'],
                        ['select', 'string[]', 'Список полей'],
                        ['order', 'array', 'Сортировка'],
                    ],
                    'defaults' => [
                        [
                            'label' => 'Проверка прав',
                            'tone'  => 'lock',
                            'value' => '<code>CHECK_PERMISSIONS=Y</code>.',
                        ],
                        [
                            'label' => 'Базовый фильтр',
                            'value' => '<code>ACTIVE=Y</code>, привязка к инфоблоку.',
                        ],
                        [
                            'label' => 'Сортировка',
                            'value' => '<code>{"sort":"asc","name":"asc"}</code> — если <code>order</code> не передан.',
                        ],
                    ],
                    'example' => "{\n  \"siteId\": \"ru\"\n}",
                ],
                ['type' => 'heading', 'text' => 'Расширение через наследование (IblockService)'],
                [
                    'type' => 'note',
                    'text' => 'Кешируемые выборки имеют точки расширения: проект наследует <code>IblockService</code> '
                        . 'и переопределяет нужный хук, не копируя тела методов. Наследник подключается через '
                        . '<code>getModelClass()</code> своего контроллера. <b>Внимание:</b> хуки вызываются внутри '
                        . 'кешируемой области — всё добавленное попадает в кеш (по умолчанию ≈24 часа, тег '
                        . '<code>iblock_id_{ID}</code>), не полагайтесь в них на per-request данные (текущий '
                        . 'пользователь, время и т.п.). Порядок параметров единый: данные → сырые поля → '
                        . 'сырые свойства → объект выборки → конфиг.',
                ],
                [
                    'type' => 'html',
                    'html' => static::renderCode(
                        "class ProjectIblockService extends \\Uplab\\Api\\Model\\Iblock\\IblockService\n{\n    protected function appendSectionDetailData(array \$data, array \$rawFields, mixed \$obj, mixed \$iblockConfig): array\n    {\n        \$data['MY_PARAM'] = \$this->calcBusinessLogic((int)\$data['ID'], \$rawFields); // попадёт в кеш\n        return \$data;\n    }\n}\n\nclass ProjectIblockController extends \\Uplab\\Api\\Controller\\IblockController\n{\n    protected function getModelClass()\n    {\n        return new ProjectIblockService();\n    }\n}",
                        'Регистрация наследника',
                        'php'
                    ),
                ],
                ['type' => 'heading', 'text' => 'Хуки payload'],
                [
                    'type' => 'table',
                    'head' => ['Метод', 'Область', 'Эндпоинт'],
                    'rows' => [
                        ['appendElementListData($data, $config)', 'весь payload (items + meta)', '<code>/elements</code>'],
                        ['appendElementListItemData($arItem, $rawFields, $rawProperties, $obj, $config)', 'один элемент списка (в цикле выборки)', '<code>/elements</code>'],
                        ['appendElementDetailData($data, $rawFields, $rawProperties, $obj, $config)', 'детальная карточка', '<code>/element/code|id/{...}</code>'],
                        ['appendSectionListData($data, $config)', 'весь payload (items + meta)', '<code>/sections</code>'],
                        ['appendSectionListItemData($arSection, $rawFields, $config)', 'один раздел списка (в цикле выборки)', '<code>/sections</code>'],
                        ['appendSectionDetailData($data, $rawFields, $obj, $config)', 'детальная раздела', '<code>/section/code|id/{...}</code>'],
                    ],
                ],
                [
                    'type' => 'table',
                    'head' => ['Аргумент', 'Описание'],
                    'rows' => [
                        ['$rawFields', 'Снимок полей сразу после выборки (<code>GetFields()</code>/<code>GetNext()</code>), до преобразований для фронта: с <code>~</code>-полями и системными полями, которые из итогового ответа вырезаются. Для разделов включает UF-поля.'],
                        ['$rawProperties', 'Свойства элемента из <code>GetProperties()</code> до преобразований, без повторного запроса. Заполнены, только если свойства запрошены (<code>propertyCode</code> передан или коды определены через property features); иначе пустой массив — при необходимости вызовите <code>$obj->GetProperties()</code> сами.'],
                        ['$obj', 'Объект строки выборки (<code>_CIBElement</code>), где есть — его методы доступны без повторного запроса к БД.'],
                    ],
                ],
                ['type' => 'heading', 'text' => 'Хуки ключа кеша'],
                [
                    'type' => 'note',
                    'text' => '<code>getElementListCacheKeyParts()</code>, <code>getElementDetailCacheKeyParts()</code>, '
                        . '<code>getSectionListCacheKeyParts()</code>, <code>getSectionDetailCacheKeyParts()</code> — '
                        . 'сигнатура <code>(array $parts, $config): array</code>. Если добавленные хуками данные зависят '
                        . 'от входных параметров (настройка, поле конфига) — добавьте их к <code>$parts</code>, иначе '
                        . 'разные входы склеятся в одну кеш-запись. Базовые части (включая <code>static::class</code> — '
                        . 'он разводит кеш наследника и базового класса) не удаляйте. Если данные для хука берутся из '
                        . 'другого инфоблока — зарегистрируйте его тег, тег-кеш в момент вызова открыт: '
                        . '<code>Application::getInstance()->getTaggedCache()->registerTag(\'iblock_id_\' . $id)</code>.',
                ],
                ['type' => 'heading', 'text' => 'Расширение умного фильтра (IblockProperty)'],
                [
                    'type' => 'note',
                    'text' => 'Значения фильтра собираются по карте «тип свойства → метод-резолвер» '
                        . '(<code>getValueResolvers()</code>). Проект наследует <code>IblockProperty</code>, дописывает '
                        . 'карту и реализует только свой метод — остальные типы работают без копирования кода. Список '
                        . 'типов, попадающих в фильтр, выводится из этой же карты. Сигнатура резолвера: '
                        . '<code>(array $arProperty, array $customFilter): array</code>, где <code>$customFilter</code> — '
                        . 'фильтр запроса без условия по самому свойству; возврат — список '
                        . '<code>[[\'id\', \'value\', \'text\'], …]</code>, пустой массив означает «свойство в фильтр '
                        . 'не попадает». Исключение внутри резолвера не роняет весь фильтр: оно пишется в Журнал '
                        . 'событий (тип <code>UPLAB_API_FILTER_ERROR</code>), свойство отдаёт пустой набор.',
                ],
                [
                    'type' => 'note',
                    'text' => '<b>Подключение наследника</b> — через <code>IblockService::getPropertyModel()</code>: '
                        . 'фабрика без параметров (как <code>getModelClass()</code> в контроллерах) возвращает своего '
                        . 'наследника <code>IblockProperty</code>, сервис подключается через <code>getModelClass()</code> '
                        . 'контроллера. Инфоблок, сайт и раздел модель получает сеттерами '
                        . '(<code>setIblockId()</code>, <code>setSiteId()</code>, <code>setSectionId()</code>), поэтому '
                        . 'сигнатура фабрики не меняется при появлении новых данных. Переопределять '
                        . '<code>getFilterElementsAction()</code> не нужно.',
                ],
                [
                    'type' => 'note',
                    'text' => '<b>Свой <code>USER_TYPE</code>.</b> Пользовательский тип свойства в Bitrix живёт поверх '
                        . 'базового (<code>S</code>, <code>N</code>, …), поэтому переопределяйте резолвер базового типа '
                        . 'и опирайтесь на <code>parent::</code> для штатного поведения. Свой базовый тип регистрируется '
                        . 'добавлением ключа в карту.',
                ],
                [
                    'type' => 'html',
                    'html' => static::renderCode(
                        "class ProjectIblockProperty extends \\Uplab\\Api\\Model\\Iblock\\IblockProperty\n{\n    protected function getValueResolvers(): array\n    {\n        return parent::getValueResolvers() + ['F' => 'getFileValues'];\n    }\n\n    protected function getStringValues(array \$arProperty, array \$customFilter): array\n    {\n        if ((\$arProperty['USER_TYPE'] ?? '') === 'my_type') {\n            return \$this->buildMyTypeItems(\$arProperty, \$customFilter);\n        }\n\n        return parent::getStringValues(\$arProperty, \$customFilter);\n    }\n}",
                        'Свой тип свойства в фильтре',
                        'php'
                    ),
                ],
                [
                    'type' => 'table',
                    'head' => ['Метод', 'Назначение'],
                    'rows' => [
                        ['getValueResolvers()', 'Карта «<code>PROPERTY_TYPE</code> → метод». Задаёт и список поддерживаемых типов'],
                        ['getEnumValues() / getStringValues() / getElementLinkValues() / getSectionLinkValues()', 'Резолверы штатных типов (<code>L</code>, <code>S</code>, <code>E</code>, <code>G</code>)'],
                        ['getDirectoryValues() / getPlainStringValues()', 'Ветки строкового типа: справочник HL-блока и значения свойства как есть'],
                        ['buildPropertyFilter()', 'Фильтр запроса без условия по самому свойству'],
                        ['buildElementsFilter()', 'Базовый (защитный) фильтр выборки: <code>ACTIVE</code>, <code>IBLOCK_ID</code>, <code>CHECK_PERMISSIONS</code>, <code>MIN_PERMISSION</code>, раздел'],
                        ['getEnumPropertyItems() / getGroupPropertyItems()', 'Значения свойства, реально использованные в доступных элементах'],
                        ['logPropertyError()', 'Запись ошибки резолвера в Журнал событий и уведомление администратору'],
                    ],
                ],
                [
                    'type' => 'note',
                    'text' => '<b>Настройка под конкретное свойство конкретного инфоблока.</b> Сортировку, формат значений '
                        . 'и фильтр можно менять точечно, не копируя тело резолвера: все хуки получают <code>$arProperty</code> '
                        . '(<code>CODE</code>, <code>ID</code>, <code>USER_TYPE</code>, <code>IBLOCK_ID</code>), инфоблок — '
                        . 'через <code>$this->getIblockId()</code>. Для <code>buildElementsFilter()</code>, который '
                        . 'мета-данные свойства параметром не получает, текущее свойство доступно через '
                        . '<code>getCurrentProperty()</code>: он заполнен, пока работает резолвер, и сбрасывается после — '
                        . 'в том числе при исключении.',
                ],
                [
                    'type' => 'table',
                    'head' => ['Хук', 'Что задаёт', 'По умолчанию'],
                    'rows' => [
                        ['getEnumOrder($arProperty)', 'Сортировка значений перечисления (<code>L</code>)', '<code>{"SORT":"ASC","VALUE":"ASC"}</code>'],
                        ['getSectionsOrder($arProperty)', 'Сортировка разделов (<code>G</code>)', '<code>{"SORT":"ASC","NAME":"ASC"}</code>'],
                        ['getElementsOrder($arProperty)', 'Сортировка элементов (<code>E</code>)', '<code>{"SORT":"ASC","NAME":"ASC"}</code>'],
                        ['getDirectoryOrder($arProperty)', 'Сортировка записей справочника', 'без <code>ORDER BY</code>'],
                        ['getSectionsSelect($arProperty)', 'Поля выборки разделов (<code>G</code>)', '<code>ID</code>, <code>NAME</code>, <code>IBLOCK_ID</code>, <code>CODE</code>'],
                        ['getElementsSelect($arProperty)', 'Поля выборки элементов (<code>E</code>), можно добавить <code>PROPERTY_&lt;КОД&gt;</code>', '<code>ID</code>, <code>NAME</code>'],
                        ['getLinkedSectionsFilter($iblockId, $ids, $arProperty)', 'Фильтр выборки связанных разделов (<code>G</code>)', '<code>ID</code>, <code>ACTIVE=Y</code>, <code>IBLOCK_ID</code>, <code>CHECK_PERMISSIONS=Y</code>, <code>MIN_PERMISSION=PUBLIC_READ</code>'],
                        ['getLinkedElementsFilter($iblockId, $ids, $arProperty)', 'Фильтр выборки связанных элементов (<code>E</code>)', 'то же'],
                        ['buildEnumItems($rows, $arProperty)', 'Формат значений перечисления', '<code>id</code>/<code>value</code> = <code>ENUM_ID</code>, <code>text</code> = <code>VALUE</code>'],
                        ['buildSectionItems($rows, $arProperty)', 'Формат значений-разделов (<code>G</code> и проектные типы с разделами)', '<code>id</code>/<code>value</code> = <code>ID</code>, <code>text</code> = <code>NAME</code>'],
                        ['buildElementItems($rows, $arProperty)', 'Формат значений-элементов (<code>E</code>)', '<code>id</code>/<code>value</code> = <code>ID</code>, <code>text</code> = <code>NAME</code>'],
                        ['buildDirectoryItems($rows, $nameFields, $arProperty)', 'Формат значений справочника', '<code>value</code> = <code>UF_XML_ID</code>, <code>text</code> = локализованное имя'],
                        ['buildPlainStringItems($arProperty, $rows)', 'Формат значений строкового свойства', '<code>value</code> = <code>text</code> = значение свойства'],
                    ],
                ],
                [
                    'type' => 'html',
                    'html' => static::renderCode(
                        "class ProjectIblockProperty extends \\Uplab\\Api\\Model\\Iblock\\IblockProperty\n{\n    // Сортировка — только для COLOR в инфоблоке 17\n    protected function getEnumOrder(array \$arProperty): array\n    {\n        if (\$this->getIblockId() === 17 && \$arProperty['CODE'] === 'COLOR') {\n            return ['VALUE' => 'DESC'];\n        }\n\n        return parent::getEnumOrder(\$arProperty);\n    }\n\n    // Фильтр — контекст текущего свойства\n    protected function buildElementsFilter(array \$additionalFilter = []): array\n    {\n        \$filter = parent::buildElementsFilter(\$additionalFilter);\n        if ((\$this->getCurrentProperty()['CODE'] ?? '') === 'COLOR') {\n            \$filter['!PROPERTY_HIDE_VALUE'] = false;\n        }\n\n        return \$filter;\n    }\n}",
                        'Правка сортировки и фильтра для одного свойства',
                        'php'
                    ),
                ],
                [
                    'type' => 'note',
                    'text' => 'Обращения к API Bitrix вынесены в отдельные обёртки (<code>fetchPropertyValueRows()</code>, '
                        . '<code>fetchSections()</code>, <code>fetchEnumValues()</code> и т.п.). Переопределять их в проекте '
                        . 'обычно не нужно — они существуют, чтобы логику можно было покрыть юнит-тестами без ядра Bitrix. '
                        . 'Если значения берутся из другого инфоблока — зарегистрируйте его тег кеша: '
                        . '<code>$this->addIblockTags($iblockId)</code>.',
                ],
                ['type' => 'heading', 'text' => 'Пример: свои типы свойств (модуль asd.iblock)'],
                [
                    'type' => 'note',
                    'text' => 'Типы <code>SASDCheckboxNum</code> и <code>SASDSection</code> принадлежат сторонему модулю '
                        . '<code>asd.iblock</code>, поэтому <code>uplab.api</code> их не обрабатывает. Пример ниже '
                        . 'добавляет их на проекте целиком. Переиспользуются штатные методы модуля: '
                        . '<code>getGroupPropertyItems()</code> (отбор реально использованных значений с базовым фильтром, '
                        . 'проверкой прав и учётом переданного <code>filter</code>), <code>buildSectionItems()</code> '
                        . '(формат значений) и <code>getIblockId()</code>. Штатные типы (<code>L</code>, <code>S</code>, '
                        . '<code>E</code>, <code>G</code>) продолжают работать — их код не копируется.',
                ],
                [
                    'type' => 'html',
                    'html' => static::renderCode(
                        "class ProjectIblockProperty extends \\Uplab\\Api\\Model\\Iblock\\IblockProperty\n{\n    protected function getValueResolvers(): array\n    {\n        // Пользовательский тип живёт поверх базового, поэтому регистрируем базовый тип «число»\n        return parent::getValueResolvers() + ['N' => 'getAsdNumberValues'];\n    }\n\n    protected function getAsdNumberValues(array \$arProperty, array \$customFilter): array\n    {\n        return match (\$arProperty['USER_TYPE'] ?? '') {\n            'SASDCheckboxNum' => \$this->buildAsdCheckboxItems(\$arProperty),\n            'SASDSection'     => \$this->getAsdSectionValues(\$arProperty, \$customFilter),\n            default           => [],\n        };\n    }\n\n    protected function buildAsdCheckboxItems(array \$arProperty): array\n    {\n        \$items = [];\n        foreach ((array)(\$arProperty['USER_TYPE_SETTINGS']['VIEW'] ?? []) as \$key => \$label) {\n            \$items[] = ['id' => \$arProperty['CODE'] . \$key, 'value' => \$key, 'text' => \$label];\n        }\n\n        return \$items;\n    }\n\n    protected function getAsdSectionValues(array \$arProperty, array \$customFilter): array\n    {\n        \$groupItems = \$this->getGroupPropertyItems(\$arProperty['CODE'], \$customFilter);\n        if (!\$groupItems) {\n            return [];\n        }\n\n        return \$this->buildSectionItems(\$this->fetchAsdSections(array_keys(\$groupItems)), \$arProperty);\n    }\n\n    protected function fetchAsdSections(array \$ids): array\n    {\n        \$res = \\Bitrix\\Iblock\\SectionTable::getList([\n            'filter' => ['GLOBAL_ACTIVE' => 'Y', 'IBLOCK_ID' => \$this->getIblockId(), 'ID' => \$ids],\n        ]);\n\n        \$rows = [];\n        while (\$row = \$res->fetch()) {\n            \$rows[] = \$row;\n        }\n\n        return \$rows;\n    }\n}",
                        'Типы asd.iblock на стороне проекта',
                        'php'
                    ),
                ],
                ['type' => 'heading', 'text' => 'Пример: строковое свойство со своим USER_TYPE'],
                [
                    'type' => 'note',
                    'text' => 'Свойство типа <code>S</code> с пользовательским типом попадает в штатную ветку простых '
                        . 'строк и отдаёт «сырые» значения. Чтобы подменить только его, переопределите резолвер строкового '
                        . 'типа, а остальные строковые свойства оставьте базовой реализации через <code>parent::</code>. '
                        . '<b>Важно:</b> <code>value</code> должен оставаться тем, что реально хранится в свойстве, иначе '
                        . 'фронт не сможет фильтровать по нему (<code>filter: {"PROPERTY_COLOR_HEX": "#FF0000"}</code>) — '
                        . 'меняйте <code>text</code>, а не <code>value</code>.',
                ],
                [
                    'type' => 'html',
                    'html' => static::renderCode(
                        "protected function getStringValues(array \$arProperty, array \$customFilter): array\n{\n    if ((\$arProperty['USER_TYPE'] ?? '') !== 'ProjectColorPicker') {\n        return parent::getStringValues(\$arProperty, \$customFilter);\n    }\n\n    \$labels = \$this->loadColorLabels();   // ваш справочник: '#FF0000' => 'Красный'\n\n    \$items = [];\n    // getGroupPropertyItems() вернёт «значение свойства => количество элементов»,\n    // уже с проверкой прав, базовым фильтром и учётом переданного filter\n    foreach (array_keys(\$this->getGroupPropertyItems(\$arProperty['CODE'], \$customFilter)) as \$value) {\n        \$items[] = [\n            'id'    => \$arProperty['ID'],\n            'value' => \$value,\n            'text'  => \$labels[\$value] ?? \$value,\n        ];\n    }\n\n    return \$items;\n}",
                        'Свой USER_TYPE строкового свойства',
                        'php'
                    ),
                ],
                ['type' => 'heading', 'text' => 'Пример: привязка к элементу (E) со своим названием'],
                [
                    'type' => 'note',
                    'text' => 'Для <code>E</code> в значения по умолчанию попадает <code>NAME</code> связанного элемента. '
                        . 'Чтобы вывести другое поле (например короткое название из свойства) и отсортировать по нему, '
                        . 'нужны три хука: <code>getElementsSelect()</code>, <code>getElementsOrder()</code> и '
                        . '<code>buildElementItems()</code> — выборка и отбор значений остаются штатными. Модуль сам '
                        . 'отбирает ID реально использованных связанных элементов, берёт <code>LINK_IBLOCK_ID</code> из '
                        . 'настроек свойства, выбирает только активные элементы и регистрирует тег кеша '
                        . '<code>iblock_id_{LINK_IBLOCK_ID}</code>. Разделы (<code>G</code>) настраиваются симметрично: '
                        . '<code>getSectionsSelect()</code>, <code>getSectionsOrder()</code>, <code>buildSectionItems()</code>.',
                ],
                [
                    'type' => 'html',
                    'html' => static::renderCode(
                        "protected function getElementsSelect(array \$arProperty): array\n{\n    if (\$arProperty['CODE'] === 'VENDOR') {\n        return ['ID', 'NAME', 'PROPERTY_SHORT_TITLE'];\n    }\n\n    return parent::getElementsSelect(\$arProperty);\n}\n\nprotected function getElementsOrder(array \$arProperty): array\n{\n    if (\$arProperty['CODE'] === 'VENDOR') {\n        return ['PROPERTY_SHORT_TITLE' => 'ASC'];\n    }\n\n    return parent::getElementsOrder(\$arProperty);\n}\n\nprotected function buildElementItems(array \$rows, array \$arProperty): array\n{\n    if (\$arProperty['CODE'] !== 'VENDOR') {\n        return parent::buildElementItems(\$rows, \$arProperty);\n    }\n\n    \$items = [];\n    foreach (\$rows as \$row) {\n        \$items[] = [\n            'id'    => \$row['ID'],\n            'value' => \$row['ID'],\n            'text'  => (string)(\$row['PROPERTY_SHORT_TITLE_VALUE'] ?? '') !== ''\n                ? \$row['PROPERTY_SHORT_TITLE_VALUE']\n                : \$row['NAME'],\n        ];\n    }\n\n    return \$items;\n}",
                        'Своё название и сортировка для свойства-привязки',
                        'php'
                    ),
                ],
            ],
        ];
    }

    protected static function pageHlblock(): array
    {
        return [
            'title' => 'Highload-блоки — /api/hlblock',
            'lead'  => 'Получение элементов, данных для фильтров и пользовательских полей highload-блоков.',
            'sections' => [
                ['type' => 'heading', 'text' => 'Доступ к данным HL-блоков'],
                [
                    'type' => 'note',
                    'text' => 'HL-блок отдаётся публично, пока на нём <b>никому не выдано</b> право чтения: пустая вкладка '
                        . '«Права доступа» означает «ограничение чтения не настроено». Чтобы закрыть блок — выдайте штатную '
                        . 'операцию <code>hl_element_read</code> нужным группам (<b>Настройки → Highload-блоки → блок → '
                        . 'Права доступа</b>): после этого <code>/elements</code>, <code>/filter</code> и <code>/fields</code> '
                        . 'читают только обладатели права и администратор, остальные получают <code>403</code>. Права на запись '
                        . 'публичность не отбирают: задача с буквой <code>W</code> операции чтения не содержит. '
                        . 'Учитываются коды групп (<code>G&lt;ID&gt;</code>, у анонима это <code>G2</code>), '
                        . '<code>AU</code> и <code>U&lt;ID&gt;</code>; для авторизованных дополнительно работает '
                        . 'штатный <code>HighloadBlockRightsTable::getOperationsName()</code> (коды отделов и соцгрупп).',
                ],
                [
                    'type' => 'note',
                    'text' => '<b>Как снова открыть блок, если право чтения уже выдано:</b> выдать чтение группе '
                        . '«Все пользователи» (код <code>G2</code>) — блок станет доступен и анонимным запросам, '
                        . 'а остальные права продолжат работать; либо снять с блока все записи с правом чтения — '
                        . 'блок вернётся в состояние «ограничение не настроено».',
                ],
                [
                    'type' => 'endpoint', 'method' => 'ANY',
                    'path' => '/api/hlblock/{hlblockCode}/elements',
                    'summary' => 'Список элементов highload-блока.',
                    'desc' => 'Возвращает элементы highload-блока. Блок публичен, пока на нём никому не выдано право <code>hl_element_read</code>; '
                        . 'после выдачи читают только обладатели права и администратор (иначе <code>403</code>). '
                        . 'Поля-привязки и файлы дополняются читаемыми данными в подмассиве <code>INFO</code>.',
                    'meta' => ['Контроллер' => 'HlblockController::getElementListAction', 'Модель' => 'HlblockService', 'Конфиг' => 'HlblockListConfig'],
                    'cache' => '≈24 часа (тег HL-блока), отдельно для групп текущего пользователя',
                    'params' => [
                        ['siteId', 'string', 'SITE_ID', 'ID сайта'],
                        ['filter', 'array', '[]', 'Фильтр'],
                        ['select', 'string[]', '[]', 'Список полей'],
                        ['order', 'array', '{"ID":"ASC"}', 'Сортировка. Если не передать — применяется значение слева'],
                        ['limit', 'int', '10', 'Кол-во элементов на странице, 1…500'],
                        ['offset', 'int', '0', 'Смещение, 0…100000'],
                        ['SHOW_TOTAL', 'Y|N', 'Y', 'Включить <code>total</code> в ответ (<code>meta.total</code>)'],
                    ],
                    'defaults' => [
                        [
                            'label' => 'Базовый фильтр',
                            'value' => 'нет — применяется только переданный <code>filter</code> (фильтрация по <code>UF_*</code> полностью на вашей стороне).',
                        ],
                        [
                            'label' => 'Сортировка',
                            'value' => '<code>{"ID":"ASC"}</code> — если <code>order</code> не передан.',
                        ],
                        [
                            'label' => 'Пагинация',
                            'value' => '<code>limit=10</code>, <code>offset=0</code>. В ответе — <code>meta.limit</code>/<code>meta.offset</code>, '
                                . '<code>meta.total</code> при <code>SHOW_TOTAL=Y</code>. Максимумы: <code>500</code>/<code>100000</code>.',
                        ],
                    ],
                    'notes' => [
                        '<b>Конвертация значений полей.</b> Поля-привязки (<code>iblock_element</code>, <code>hlblock</code>) и поля '
                        . 'типа <code>file</code> дополняются читаемыми данными в подмассиве <code>INFO</code> (исходное значение поля '
                        . 'сохраняется). Для <code>file</code> в <code>INFO</code> кладётся полный массив из <code>CFile::GetFileArray()</code>. '
                        . 'Процесс можно расширить событиями <code>OnBeforeHlblockConvertFieldValue</code> / '
                        . '<code>OnAfterHlblockConvertFieldValue</code> (см. раздел «События»).',
                    ],
                    'example' => "{\n  \"siteId\": \"ru\",\n  \"filter\": { \"UF_ACTIVE\": 1 },\n  \"select\": [\"ID\", \"UF_NAME\", \"UF_FILE\"],\n  \"order\": { \"UF_SORT\": \"ASC\" },\n  \"limit\": 20,\n  \"offset\": 0\n}",
                    'responseExample' => "{\n  \"items\": [\n    {\n      \"ID\": 7,\n      \"UF_NAME\": \"Пример\",\n      \"UF_FILE\": 251,\n      \"INFO\": { \"UF_FILE\": { \"SRC\": \"/upload/...\", \"FILE_NAME\": \"a.jpg\", \"FILE_SIZE\": 10240 } }\n    }\n  ],\n  \"meta\": { \"total\": 42, \"limit\": 20, \"offset\": 0 }\n}",
                ],
                [
                    'type' => 'endpoint', 'method' => 'ANY',
                    'path' => '/api/hlblock/{hlblockCode}/filter',
                    'summary' => 'Данные для фильтра элементов highload-блока.',
                    'desc' => 'Возвращает данные для построения фильтра. Доступ — как у <code>/elements</code>: публично, пока на блоке никому не выдано право <code>hl_element_read</code>. Параметры идентичны <code>/elements</code>.',
                    'meta' => ['Контроллер' => 'HlblockController::getElementFilterAction', 'Конфиг' => 'HlblockListConfig'],
                    'cache' => '≈24 часа (тег HL-блока), отдельно для групп текущего пользователя',
                    'example' => "{\n  \"siteId\": \"ru\",\n  \"select\": [\"UF_COLOR\"]\n}",
                ],
                [
                    'type' => 'endpoint', 'method' => 'ANY',
                    'path' => '/api/hlblock/{hlblockCode}/fields',
                    'summary' => 'Список пользовательских полей highload-блока.',
                    'desc' => 'Возвращает мета-данные всех пользовательских полей highload-блока. Доступ — как у <code>/elements</code>: публично, пока на блоке никому не выдано право <code>hl_element_read</code>.',
                    'meta' => ['Контроллер' => 'HlblockController::getFieldsAction', 'Конфиг' => 'BaseConfig'],
                    'params' => [
                        ['siteId', 'string', 'ID сайта'],
                    ],
                    'example' => "{\n  \"siteId\": \"ru\"\n}",
                ],
            ],
        ];
    }

    protected static function pagePages(): array
    {
        return [
            'title' => 'Статические страницы — /api/pages',
            'lead'  => 'Детальные страницы из инфоблока <code>Pages</code> (статические страницы сайта).',
            'sections' => [
                [
                    'type' => 'endpoint', 'method' => 'ANY',
                    'path' => '/api/pages/{code}',
                    'summary' => 'Детальная статическая страница.',
                    'desc' => 'Возвращает детальную страницу из инфоблока <code>Pages</code>. Параметры тела идентичны '
                        . '<code>/api/iblock/{iblockCode}/element/code/{elementCode}</code>.',
                    'meta' => ['Контроллер' => 'PageController::getPageAction', 'Модель' => 'PageService', 'Конфиг' => 'IblockDetailElementConfig'],
                    'cache' => '≈24 часа (тег инфоблока); <code>CONTENT</code> — свой кеш ≈24 часа с тегом '
                        . '<code>update_contenttable_{UF_PAGE}</code>',
                    'pathParams' => ['code' => 'Символьный код страницы в инфоблоке Pages'],
                    'defaults' => static::iblockDefaults(false),
                    'notes' => [
                        '<b>Поле <code>CONTENT</code>.</b> К ответу подмешивается статический контент страницы из '
                        . 'HL-блока <code>Content</code> — те же записи, что отдаёт <code>/api/content/page/{pageCode}</code> '
                        . '(оба эндпоинта вызывают <code>ContentTable::getPageData()</code>), поэтому отдельный запрос '
                        . 'за контентом страницы не нужен.',
                        '<b>Отбор и структура <code>CONTENT</code>.</b> Записи с <code>UF_PAGE</code> = <code>CODE</code> '
                        . 'найденного элемента, <code>UF_ACTIVE = true</code> и <code>UF_LANG = siteId</code> (условие по языку '
                        . 'добавляется, только если <code>siteId</code> передан). Структура — '
                        . '<code>CONTENT[UF_BLOCK][UF_CODE] = UF_VALUE</code>, значение <code>#YEAR#</code> заменяется '
                        . 'на текущий год. Если записей нет — пустой объект.',
                        '<b>Хлебные крошки.</b> <code>PageService</code> цепочку навигации не строит — ключа '
                        . '<code>BREADCRUMBS</code> верхнего уровня в ответе нет (в отличие от детальных эндпоинтов '
                        . 'инфоблока). Если <code>BREADCRUMBS</code> встречается в <code>PROPERTIES</code>, это обычное '
                        . 'свойство инфоблока <code>Pages</code>, заполняемое на проекте.',
                    ],
                    'example' => "{\n  \"siteId\": \"ru\"\n}",
                    'responseExample' => "{\n  \"NAME\": \"Вакансии\",\n  \"CODE\": \"vacancies\",\n  \"PROPERTIES\": {\n    \"SEO_H1\": { \"VALUE\": \"Вакансии\" }\n  },\n  \"CONTENT\": {\n    \"filter\": { \"reset\": \"Сбросить фильтр\" },\n    \"list\": {\n      \"empty.title\": \"Ничего не найдено\",\n      \"empty.text\": \"Попробуйте изменить параметры поиска\"\n    }\n  }\n}",
                    'responses' => [
                        ['code' => 404, 'desc' => 'Страница не найдена.'],
                    ],
                ],
            ],
        ];
    }

    protected static function pageForms(): array
    {
        return [
            'title' => 'Веб-формы — /api/forms',
            'lead'  => 'Получение структуры веб-форм и отправка результатов.',
            'sections' => [
                [
                    'type' => 'endpoint', 'method' => 'GET',
                    'path' => '/api/forms/{code}/fields',
                    'summary' => 'Список полей веб-формы.',
                    'desc' => 'Возвращает список полей веб-формы (удобно, когда в вопросе есть и radio, и input[text]).',
                    'meta' => ['Контроллер' => 'WebFormController::getWebFormFieldsAction', 'Конфиг' => 'BaseConfig'],
                    'cache' => 'тегированный кеш формы (<code>form_{ID}</code>)',
                    'params' => [
                        ['siteId', 'string', 'ID сайта'],
                    ],
                    'example' => "{\n  \"siteId\": \"ru\"\n}",
                ],
                [
                    'type' => 'endpoint', 'method' => 'POST',
                    'path' => '/api/forms/{code}/add',
                    'summary' => 'Отправка результата веб-формы.',
                    'desc' => 'Сохраняет результат веб-формы. Поля формы передаются в теле запроса и соответствуют полям '
                        . 'Bitrix-формы. Перед сохранением вызывается событие <code>OnBeforeCheckForm</code> (см. «События»).',
                    'meta' => ['Контроллер' => 'WebFormController::addNewResultAction', 'Конфиг' => 'BaseConfig + $_REQUEST'],
                    'notes' => [
                        'Набор полей зависит от конкретной веб-формы. Получить структуру можно через <code>GET /api/forms/{code}/fields</code> '
                        . 'или <code>ANY /api/forms/{code}</code>. Имена полей соответствуют <code>SID</code>/кодам вопросов формы.',
                    ],
                    'example' => "{\n  \"siteId\": \"ru\",\n  \"form_text_42\": \"Иван\",\n  \"form_email_43\": \"ivan@example.com\"\n}",
                    'responses' => [
                        ['code' => 200, 'desc' => 'Результат сохранён.'],
                        ['code' => 422, 'desc' => 'Форма отклонена обработчиком <code>OnBeforeCheckForm</code> или невалидна.'],
                    ],
                ],
                [
                    'type' => 'endpoint', 'method' => 'ANY',
                    'path' => '/api/forms/{code}',
                    'summary' => 'Структура веб-формы: вопросы и варианты ответов.',
                    'desc' => 'Возвращает структуру веб-формы: все вопросы и варианты ответов (1 вопрос — 1 ответ).',
                    'meta' => ['Контроллер' => 'WebFormController::getWebFormAction', 'Конфиг' => 'BaseConfig'],
                    'cache' => 'тегированный кеш формы (<code>form_{ID}</code>)',
                    'params' => [
                        ['siteId', 'string', 'ID сайта'],
                    ],
                    'example' => "{\n  \"siteId\": \"ru\"\n}",
                    'responses' => [
                        ['code' => 404, 'desc' => 'Веб-форма не найдена.'],
                    ],
                ],
            ],
        ];
    }

    protected static function pageContent(): array
    {
        return [
            'title' => 'Статический контент — /api/content',
            'lead'  => 'Статический контент страниц из HLBlock <code>Content</code> (таблица <code>b_hlbd_content</code>) '
                . 'и административные операции над ним.',
            'sections' => [
                [
                    'type' => 'endpoint', 'method' => 'ANY',
                    'path' => '/api/content/page/{pageCode}',
                    'summary' => 'Статический контент страницы.',
                    'desc' => 'Возвращает контент страницы в виде <code>{ "block": { "code": "value" } }</code>. '
                        . 'Значение <code>#YEAR#</code> автоматически заменяется на текущий год.',
                    'meta' => ['Контроллер' => 'ContentPageController::getAction', 'Конфиг' => 'BaseConfig'],
                    'cache' => 'кешируется в разрезе сайта',
                    'params' => [
                        ['siteId', 'string', 'ID сайта. Влияет на ключ кеша.'],
                    ],
                    'notes' => [
                        'Для страниц инфоблока <code>Pages</code> отдельный запрос не нужен: те же данные приходят '
                        . 'в ключе <code>CONTENT</code> ответа <code>/api/pages/{code}</code>.',
                    ],
                    'example' => "{\n  \"siteId\": \"ru\"\n}",
                    'responseExample' => "{\n  \"footer\": { \"copyright\": \"© 2026 Компания\" }\n}",
                ],
                [
                    'type' => 'endpoint', 'method' => 'POST',
                    'path' => '/api/content/admin/{pageCode}',
                    'summary' => 'Список записей контента для админ-интерфейса.',
                    'desc' => 'Возвращает список записей контента для административного интерфейса.',
                    'meta' => ['Контроллер' => 'ContentController::getListAction'],
                    'auth' => 'право hl_element_read',
                    'queryParams' => [
                        ['page', 'int', '1', 'Номер страницы'],
                        ['pageSize', 'int', '20', 'Размер страницы'],
                    ],
                    'responses' => [
                        ['code' => 401, 'desc' => 'Требуется авторизация.'],
                        ['code' => 403, 'desc' => 'Недостаточно прав (<code>hl_element_read</code>).'],
                    ],
                ],
                [
                    'type' => 'endpoint', 'method' => 'DELETE',
                    'path' => '/api/content/admin/{elementId}',
                    'summary' => 'Удалить запись контента.',
                    'desc' => 'Удаляет запись контента по ID.',
                    'meta' => ['Контроллер' => 'ContentController::deleteElementAction'],
                    'auth' => 'право hl_element_delete + CSRF (sessid)',
                    'queryParams' => [
                        ['sessid', 'string', '—', 'CSRF-токен Bitrix (bitrix_sessid). Обязателен.'],
                    ],
                    'notes' => [
                        'Мутирующее действие: без валидного <code>sessid</code> запрос отклоняется («Доступ запрещён»).',
                    ],
                    'responses' => [
                        ['code' => 403, 'desc' => 'Недостаточно прав (<code>hl_element_delete</code>) или отсутствует/неверный <code>sessid</code>.'],
                        ['code' => 404, 'desc' => 'Запись не найдена.'],
                    ],
                ],
                [
                    'type' => 'endpoint', 'method' => 'POST',
                    'path' => '/api/content/bulk',
                    'summary' => 'Групповые действия над записями контента.',
                    'desc' => 'Выполняет групповое действие над записями контента.',
                    'meta' => ['Контроллер' => 'ContentController::bulkAction'],
                    'auth' => 'hl_element_write / hl_element_delete + CSRF (sessid)',
                    'params' => [
                        ['action', "'activate'|'deactivate'|'delete'", 'Действие'],
                        ['ids', 'int[]', 'Массив ID элементов'],
                        ['sessid', 'string', 'CSRF-токен Bitrix (bitrix_sessid). Обязателен.'],
                    ],
                    'notes' => [
                        '<code>activate</code> / <code>deactivate</code> требуют прав <code>hl_element_write</code>; '
                        . '<code>delete</code> требует <code>hl_element_delete</code>.',
                        'Мутирующее действие: без валидного <code>sessid</code> запрос отклоняется («Доступ запрещён»).',
                    ],
                    'example' => "{\n  \"action\": \"deactivate\",\n  \"ids\": [12, 15, 19],\n  \"sessid\": \"<bitrix_sessid>\"\n}",
                    'responses' => [
                        ['code' => 403, 'desc' => 'Недостаточно прав для выбранного действия или отсутствует/неверный <code>sessid</code>.'],
                    ],
                ],
            ],
        ];
    }

    protected static function pageMenus(): array
    {
        return [
            'title' => 'Меню — /api/menus',
            'lead'  => 'Иерархическое меню из инфоблока в формате для фронтенда.',
            'sections' => [
                [
                    'type' => 'endpoint', 'method' => 'ANY',
                    'path' => '/api/menus/{menuCode}',
                    'summary' => 'Иерархическое меню из инфоблока.',
                    'desc' => 'Возвращает иерархическое меню, построенное из инфоблока, в формате, удобном для фронтенда.',
                    'meta' => ['Контроллер' => 'MenuController::getAction', 'Модель' => 'MenuService', 'Конфиг' => 'MenuConfig'],
                    'cache' => '≈24 часа (тег инфоблока)',
                    'params' => [
                        ['siteId', 'string', 'ID сайта'],
                        ['iblockType', 'string', 'Тип инфоблока Bitrix'],
                        ['filter', 'array', 'Дополнительный фильтр'],
                        ['select', 'string[]', 'Список полей'],
                        ['order', 'array', 'Сортировка'],
                    ],
                    'defaults' => [
                        [
                            'label' => 'Базовый фильтр',
                            'value' => '<code>ACTIVE=Y</code>, <code>GLOBAL_ACTIVE=Y</code>, <code>IBLOCK_ID</code>. '
                                . 'Переданный <code>filter</code> только <b>добавляет</b> условия (эти ключи не переопределяются).',
                        ],
                        [
                            'label' => 'Проверка прав',
                            'value' => 'меню публичное — проверка прав элементов не применяется.',
                        ],
                    ],
                    'example' => "{\n  \"siteId\": \"ru\",\n  \"iblockType\": \"menu\"\n}",
                ],
            ],
        ];
    }

    protected static function pageSearch(): array
    {
        return [
            'title' => 'Поиск — /api/search',
            'lead'  => 'Полнотекстовый поиск через Bitrix <code>CSearch</code>. Поддерживает морфологию и повторный поиск '
                . 'без стемминга при пустом результате.',
            'sections' => [
                [
                    'type' => 'endpoint', 'method' => 'ANY',
                    'path' => '/api/search',
                    'summary' => 'Полнотекстовый поиск.',
                    'desc' => 'Выполняет полнотекстовый поиск. Параметры передаются напрямую как JSON body (без Config-объекта).',
                    'meta' => ['Контроллер' => 'SearchController::getAction', 'Модель' => 'Search'],
                    'params' => [
                        ['search', 'string', "''", 'Поисковый запрос'],
                        ['siteId', 'string', '—', 'ID сайта для фильтрации результатов'],
                        ['sort', "'rank'|'date'", "'rank'", 'Тип сортировки'],
                        ['type', "'full'|'short'", "'full'", '<code>full</code> — страница поиска, <code>short</code> — шапка сайта (приоритет <code>TITLE_RANK</code>)'],
                        ['limit', 'int', '10', 'Кол-во результатов, 1…500'],
                        ['offset', 'int', '0', 'Смещение, 0…100000'],
                    ],
                    'defaults' => [
                        [
                            'label' => 'Значения',
                            'value' => '<code>search=""</code>, <code>sort=rank</code>, <code>type=full</code>, '
                                . '<code>limit=10</code>, <code>offset=0</code> — если не переданы; максимумы 500/100000.',
                        ],
                        [
                            'label' => 'Область поиска',
                            'value' => 'если <code>siteId</code> не передан — поиск идёт по всем сайтам.',
                        ],
                    ],
                    'notes' => [
                        'При пустом результате с морфологией выполняется повторный поиск без стемминга.',
                        'Текст «ничего не найдено» (<code>message.title</code>/<code>message.text</code>) берётся из статического '
                        . 'контента — HLBlock <code>Content</code>: <code>UF_PAGE=\'search\'</code>, <code>UF_BLOCK=\'empty\'</code>, '
                        . '<code>UF_CODE=\'title\'|\'text\'</code>. Если там пусто — фолбэк на языковые сообщения '
                        . '<code>UPLAB_API.SEARCH_EMPTY_TITLE</code> / <code>UPLAB_API.SEARCH_EMPTY_TEXT</code> '
                        . '(<code>lang/*/include.php</code>). Свой текст задаётся либо в контенте, либо переопределением lang.',
                    ],
                    'example' => "{\n  \"siteId\": \"ru\",\n  \"search\": \"смартфон\",\n  \"type\": \"full\",\n  \"sort\": \"rank\",\n  \"limit\": 10,\n  \"offset\": 0\n}",
                ],
            ],
        ];
    }

    protected static function pageSitemap(): array
    {
        return [
            'title' => 'Карта сайта — /api/sitemap',
            'lead'  => 'Данные для генерации sitemap. Кешируется на 24 часа в разрезе сайта.',
            'sections' => [
                [
                    'type' => 'endpoint', 'method' => 'ANY',
                    'path' => '/api/sitemap',
                    'summary' => 'Данные для генерации sitemap.',
                    'desc' => 'Возвращает данные для генерации sitemap (статические страницы, элементы и разделы инфоблоков).',
                    'meta' => ['Контроллер' => 'SitemapController::getAction', 'Модель' => 'Sitemap (SitemapInterface)'],
                    'cache' => '24 часа (в разрезе сайта)',
                    'params' => [
                        ['siteId', 'string', 'ID сайта. Влияет на ключ кеша и фильтрацию (<code>IBLOCK_LID</code>).'],
                    ],
                    'example' => "{\n  \"siteId\": \"ru\"\n}",
                ],
                ['type' => 'heading', 'text' => 'Расширение через наследование'],
                [
                    'type' => 'note',
                    'text' => 'Модель разбита на точечно переопределяемые хуки. Достаточно создать наследника <code>Sitemap</code> '
                        . 'и зарегистрировать его через <code>SitemapController::getModelClass()</code>.',
                ],
                [
                    'type' => 'html',
                    'html' => static::renderCode(
                        "class ProjectSitemapController extends SitemapController\n{\n    protected function getModelClass()\n    {\n        return new ProjectSitemap();\n    }\n}",
                        'Регистрация наследника',
                        'php'
                    ),
                ],
                ['type' => 'heading', 'text' => 'Свойства наследника'],
                [
                    'type' => 'table',
                    'head' => ['Свойство', 'Тип', 'Описание'],
                    'rows' => [
                        ['$domain', 'string', 'Домен перед каждым URL (по умолчанию пустая строка — относительные URL).'],
                        ['$cacheTime', 'int', 'Время жизни кеша в секундах (по умолчанию <code>86400</code>).'],
                        ['$pagesIblockElementsCodes', 'string[]', 'Коды инфоблоков, детальные страницы элементов которых попадают в sitemap.'],
                        ['$pagesIblockSectionsCodes', 'string[]', 'Коды инфоблоков, страницы разделов которых попадают в sitemap.'],
                    ],
                ],
                ['type' => 'heading', 'text' => 'Хуки для точечного переопределения'],
                [
                    'type' => 'table',
                    'head' => ['Метод', 'Описание'],
                    'rows' => [
                        ['getStaticPagesIblockCode()', 'Код инфоблока статических страниц (по умолчанию <code>\'pages\'</code>).'],
                        ['getStaticPagesOrder()', 'Сортировка статических страниц.'],
                        ['getStaticPagesFilter(int $iblockId)', 'Фильтр статических страниц.'],
                        ['getStaticPagesSelect()', 'Поля выборки статических страниц.'],
                        ['buildStaticPageItem(array $el)', 'Трансформация элемента → <code>{loc, lastmod}</code>. <code>null</code> = пропустить.'],
                        ['getElementPagesOrder()', 'Сортировка элементов инфоблоков.'],
                        ['getElementPagesFilter()', 'Фильтр элементов инфоблоков.'],
                        ['buildElementPageItem(array $el)', 'Трансформация элемента → <code>{loc, lastmod}</code>. <code>null</code> = пропустить.'],
                        ['getSectionPagesOrder()', 'Сортировка разделов инфоблоков.'],
                        ['getSectionPagesFilter()', 'Фильтр разделов инфоблоков.'],
                        ['buildSectionPageItem(array $sect)', 'Трансформация раздела → <code>{loc, lastmod}</code>. <code>null</code> = пропустить.'],
                        ['getFormatDate(int $date)', 'Формат даты (по умолчанию ISO 8601).'],
                        ['getPages()', 'Полный сбор страниц из всех источников.'],
                    ],
                ],
            ],
        ];
    }

    protected static function pageEvents(): array
    {
        return [
            'title' => 'Система событий',
            'lead'  => 'Все события регистрируются на модуль <code>uplab.api</code>. Подписка — через '
                . '<code>\\Bitrix\\Main\\EventManager</code>.',
            'sections' => [
                [
                    'type'   => 'events',
                    'events' => [
                        [
                            'name'   => 'OnBeforeCheckForm',
                            'params' => 'BeforeCheckForm $event',
                            'desc'   => 'Перед сохранением результата веб-формы (<code>POST /api/forms/{code}/add</code>). '
                                . 'Возврат <code>EventResult::ERROR</code> отклоняет форму.',
                            'example' => "\$eventManager->addEventHandler('uplab.api', 'OnBeforeCheckForm', [static::class, 'OnBeforeCheckForm']);\n\npublic static function OnBeforeCheckForm(\\Uplab\\Api\\EventResult\\BeforeCheckForm \$event): \\Bitrix\\Main\\EventResult\n{\n    // \$event->request — данные из \$_REQUEST\n    if (empty(\$event->request['token'])) {\n        return new \\Bitrix\\Main\\EventResult(\n            \\Bitrix\\Main\\EventResult::ERROR,\n            parameters: ['message' => 'Token required'],\n            moduleId: 'uplab.api'\n        );\n    }\n    return new \\Bitrix\\Main\\EventResult(\\Bitrix\\Main\\EventResult::SUCCESS);\n}",
                        ],
                        [
                            'name'   => 'OnBeforeHlblockConvertFieldValue',
                            'params' => 'array $fields, array &$dataItem',
                            'desc'   => 'Для каждого элемента HL-блока <b>перед</b> конвертацией значений полей '
                                . '(<code>GET /api/hlblock/{code}/elements</code>). <code>$fields</code> — мета-данные всех '
                                . 'пользовательских полей; <code>&$dataItem</code> — данные одного элемента, по ссылке.',
                        ],
                        [
                            'name'   => 'OnAfterHlblockConvertFieldValue',
                            'params' => 'array $fields, array &$dataItem',
                            'desc'   => 'Для каждого элемента HL-блока <b>после</b> конвертации значений полей. '
                                . '<b>Внимание:</b> результат конвертации кешируется вместе с ответом (по умолчанию 24 часа) — '
                                . 'не полагайтесь в обработчике на per-request данные.',
                            'example' => "\$eventManager->addEventHandler('uplab.api', 'OnAfterHlblockConvertFieldValue', [static::class, 'OnAfterHlblockConvertFieldValue']);\n\npublic static function OnAfterHlblockConvertFieldValue(array \$fields, array &\$dataItem): void\n{\n    // Например, добавить вычисляемое поле к каждому элементу\n    \$dataItem['INFO']['UF_FULL_URL'] = '/catalog/' . (\$dataItem['UF_CODE'] ?? '');\n}",
                        ],
                        [
                            'name'   => 'OnIblockConvertPropertyDisplayValue',
                            'params' => 'array $data, array &$arDisplayValue',
                            'desc'   => 'Для каждого свойства элемента ИБ <b>после</b> сборки его отображаемого значения. '
                                . '<code>$data</code> — мета-данные свойства (<code>PROPERTY_TYPE</code>, <code>USER_TYPE</code>, '
                                . '<code>MULTIPLE</code>, <code>VALUE</code> и т.д.); <code>&$arDisplayValue</code> — по ссылке.',
                            'example' => "\$eventManager->addEventHandler('uplab.api', 'OnIblockConvertPropertyDisplayValue', [static::class, 'OnIblockConvertPropertyDisplayValue']);\n\npublic static function OnIblockConvertPropertyDisplayValue(array \$data, array &\$arDisplayValue): void\n{\n    // Например, для своего пользовательского типа свойства сформировать своё отображение\n    if ((\$data['USER_TYPE'] ?? '') === 'my_custom_type') {\n        \$arDisplayValue = array_map('mb_strtoupper', \$arDisplayValue);\n    }\n}",
                        ],
                        [
                            'name'   => 'OnIblockConvertSectionDisplayValue',
                            'params' => 'array $sectionUfField, array &$arDisplayValue',
                            'desc'   => 'Для каждого UF-поля раздела ИБ <b>после</b> сборки его отображаемого значения '
                                . '(эндпоинты разделов инфоблока). <code>$sectionUfField</code> — мета-данные и значение поля; '
                                . '<code>&$arDisplayValue</code> — по ссылке.',
                        ],
                    ],
                ],
            ],
        ];
    }

    protected static function styles(): string
    {
        return <<<'HTML'
<style>
.uplab-docs { max-width: 1200px; margin: 0 0 48px; color: #1a1a1a; font-size: 14px; line-height: 1.55; }
.uplab-docs code, .uplab-docs pre { font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace; }
.uplab-docs__title { font-size: 22px; font-weight: 700; margin: 0 0 8px; }
.uplab-docs__lead { font-size: 14.5px; color: #475569; margin: 0; }
.uplab-docs-h3 { font-size: 18px; font-weight: 600; margin: 34px 0 14px; padding-top: 10px; border-top: 1px solid #e6e8eb; }
.uplab-docs code { background: #eef1f5; padding: 1px 6px; border-radius: 4px; font-size: 12.5px; color: #2a3f5f; }

/* Белый контентный блок в стиле админки Bitrix */
.uplab-block { background: #fff; border: 1px solid #dfe4e8; border-radius: 10px; }
.uplab-intro { padding: 18px 20px; margin: 0 0 16px; }

/* Горизонтальная навигация по разделам */
.uplab-topnav { display: flex; flex-wrap: wrap; gap: 6px; padding: 8px; margin: 0 0 16px; }
.uplab-topnav__item { display: inline-flex; align-items: center; gap: 8px; padding: 7px 13px; border-radius: 8px; text-decoration: none; color: #475569; font-size: 13px; font-weight: 500; border: 1px solid transparent; transition: background .13s, color .13s, border-color .13s; }
/* text-decoration повторяется в каждом состоянии: базовое правило слабее глобального a:hover админки */
.uplab-topnav__item:hover, .uplab-topnav__item:focus, .uplab-topnav__item:active { text-decoration: none; }
.uplab-topnav__item:hover { background: #f4f6f9; color: #0f172a; }
.uplab-topnav__item:focus-visible { outline: 2px solid #2067b0; outline-offset: 1px; }
.uplab-topnav__item:active { background: #e3e9f1; color: #0f172a; border-color: #cdd6e2; }
.uplab-topnav__ico { font-size: 15px; line-height: 1; }
.uplab-topnav__item.is-active { background: #eaf2fb; color: #1b5794; border-color: #d6e6f7; font-weight: 600; }
.uplab-topnav__item.is-active:active { background: #d8e7f8; border-color: #bcd6ef; }

.uplab-toolbar { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; padding: 12px 14px; margin: 0 0 16px; }
.uplab-toolbar__search { flex: 1 1 260px; min-width: 200px; padding: 9px 14px; border: 1px solid #d6dbe2; border-radius: 8px; font-size: 13px; outline: none; transition: border-color .15s, box-shadow .15s; }
.uplab-toolbar__search:focus { border-color: #2067b0; box-shadow: 0 0 0 3px rgba(32,103,176,.12); }
.uplab-toolbar__btns { display: flex; gap: 8px; }
.uplab-toolbar__btn { padding: 8px 14px; border: 1px solid #d6dbe2; background: #fff; border-radius: 8px; font-size: 12.5px; color: #475569; cursor: pointer; transition: background .15s, border-color .15s; }
.uplab-toolbar__btn:hover { background: #f1f4f8; border-color: #c3cbd6; }
.uplab-toolbar__btn:active { background: #e3e9f1; border-color: #b3bdcb; }
.uplab-toolbar__btn:focus-visible { outline: 2px solid #2067b0; outline-offset: 1px; }

.uplab-op { border: 1px solid #e2e6ea; border-left-width: 4px; border-radius: 10px; margin: 0 0 12px; background: #fff; overflow: hidden; scroll-margin-top: 16px; transition: box-shadow .2s, border-color .2s; }
.uplab-op.is-target { border-color: #2067b0; box-shadow: 0 0 0 3px rgba(32,103,176,.16); }
.uplab-op--get { border-left-color: #1f9d55; }
.uplab-op--post { border-left-color: #2067b0; }
.uplab-op--delete { border-left-color: #c0392b; }
.uplab-op--put, .uplab-op--patch { border-left-color: #d98016; }
.uplab-op--any { border-left-color: #6b7280; }
.uplab-op--event { border-left-color: #7c3aed; }

.uplab-op__summary { display: flex; align-items: center; gap: 12px; width: 100%; min-width: 0; padding: 13px 16px; background: none; border: none; cursor: pointer; text-align: left; font-size: 14px; }
.uplab-op__summary:hover { background: #fafbfc; }
.uplab-op__summary:active { background: #f1f4f8; }
.uplab-op__path { background: none; padding: 0; font-size: 14.5px; font-weight: 600; color: #1a1a1a; flex: none; }
.uplab-op__param { color: #b45309; }
.uplab-op__summary-text { color: #475569; font-size: 13px; font-weight: 400; margin-left: 4px; flex: 1 1 0; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.uplab-op__anchor { flex: none; text-decoration: none; color: #cbd5e1; font-weight: 700; font-size: 15px; padding: 0 6px; opacity: .35; transition: opacity .13s, color .13s; }
.uplab-op__summary:hover .uplab-op__anchor { opacity: 1; }
.uplab-op__anchor:hover, .uplab-op__anchor:focus, .uplab-op__anchor:active { text-decoration: none; }
.uplab-op__anchor:hover { color: #2067b0; }
.uplab-op__anchor:focus-visible { outline: 2px solid #2067b0; outline-offset: 1px; opacity: 1; }
.uplab-op__anchor:active { color: #17457a; }
.uplab-op__anchor.is-copied { color: #1f9d55; opacity: 1; }
.uplab-op__chevron { flex: none; width: 9px; height: 9px; border-right: 2px solid #94a3b8; border-bottom: 2px solid #94a3b8; transform: rotate(45deg); transition: transform .18s; margin-right: 4px; }
.uplab-op.is-collapsed .uplab-op__chevron { transform: rotate(-45deg); }
.uplab-op__body { padding: 4px 18px 20px; border-top: 1px solid #eef1f4; }
.uplab-op.is-collapsed .uplab-op__body { display: none; }
.uplab-op__desc { margin: 14px 0 0; color: #334155; }
.uplab-op__section-label { margin: 24px 0 10px; font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #0f172a; }

.uplab-op__chips { display: flex; flex-wrap: wrap; gap: 8px; margin: 14px 0 0; }
.uplab-chip { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; background: #f4f6f9; border: 1px solid #e6e9ee; border-radius: 20px; font-size: 12px; color: #475569; }
.uplab-chip code { background: none; padding: 0; color: #334155; font-size: 12px; }
.uplab-chip__label { color: #94a3b8; }
.uplab-chip--cache { background: #eef7ff; border-color: #d3e7fb; color: #1b5794; }
.uplab-chip--auth { background: #fff4f4; border-color: #f6d5d5; color: #a13030; }

.uplab-badge { display: inline-block; min-width: 58px; text-align: center; padding: 5px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; letter-spacing: .04em; color: #fff; flex: none; }
.uplab-badge--get { background: #1f9d55; }
.uplab-badge--post { background: #2067b0; }
.uplab-badge--delete { background: #c0392b; }
.uplab-badge--put, .uplab-badge--patch { background: #d98016; }
.uplab-badge--any { background: #6b7280; }
.uplab-badge--event { background: #7c3aed; }

.uplab-table-wrap { overflow-x: auto; border: 1px solid #d7dde4; border-radius: 8px; }
.uplab-table { width: 100%; border-collapse: collapse; margin: 0; font-size: 13px; }
.uplab-table th { text-align: left; background: #eaeef3; padding: 9px 12px; border-bottom: 1px solid #cfd7e0; font-weight: 600; color: #334155; white-space: nowrap; }
.uplab-table td { padding: 9px 12px; border-bottom: 1px solid #e9edf1; vertical-align: top; color: #1f2a37; }
.uplab-table tbody tr:nth-child(even) { background: #f7f9fb; }
.uplab-table tbody tr:hover { background: #eef4fb; }
.uplab-table tr:last-child td { border-bottom: none; }
.uplab-table td:first-child code { white-space: nowrap; background: #eef1f5; }
.uplab-type { color: #486081; font-family: "SFMono-Regular", Consolas, monospace; font-size: 12px; }
.uplab-req { color: #94a3b8; font-size: 12px; }
.uplab-req--yes { color: #c0392b; font-weight: 600; }

.uplab-docs-note { margin: 14px 0 0; padding: 12px 16px; background: #fff8e6; border-left: 3px solid #f0b400; border-radius: 0 6px 6px 0; font-size: 13px; color: #5c4d1a; }
.uplab-docs-note code { background: #fdf0cc; color: #6b5410; }

.uplab-defaults { margin: 14px 0 0; border: 1px solid #d3e3f5; background: #f5f9ff; border-radius: 8px; padding: 12px 14px; overflow: hidden; }
.uplab-defaults__title { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #1b5794; margin: 0 0 6px; }
.uplab-defaults__list { margin: 0; }
.uplab-defaults__row { display: flex; gap: 14px; padding: 7px 0; border-top: 1px solid #e3eefa; }
.uplab-defaults__row:first-child { border-top: none; }
.uplab-defaults__row dt { flex: none; width: 130px; color: #475569; font-weight: 600; font-size: 12.5px; }
.uplab-defaults__row dd { margin: 0; color: #334155; font-size: 13px; }
.uplab-defaults__row dd code { background: #e6eefb; color: #1b3f66; }
.uplab-defaults__row--lock { background: #fff5f5; margin: 0 -14px; padding: 8px 14px; border-top-color: #f4dada; }
.uplab-defaults__row--lock dt { color: #a13030; }
.uplab-defaults__row--lock dt::before { content: "🔒 "; }
.uplab-defaults__row--lock dd code { background: #fbe3e3; color: #8a2a2a; }
@media (max-width: 720px) {
    .uplab-defaults__row { flex-direction: column; gap: 2px; }
    .uplab-defaults__row dt { width: auto; }
}

.uplab-resp { margin: 8px 0 0; }
.uplab-resp__row { display: flex; align-items: baseline; gap: 12px; padding: 7px 0; border-bottom: 1px solid #f1f4f7; }
.uplab-resp__desc { color: #334155; font-size: 13px; }
.uplab-resp__note { margin: 10px 0 0; font-size: 12.5px; color: #94a3b8; }
.uplab-status { flex: none; min-width: 46px; text-align: center; padding: 3px 8px; border-radius: 5px; font-size: 12px; font-weight: 700; font-family: "SFMono-Regular", Consolas, monospace; }
.uplab-status--ok { background: #e6f4ec; color: #1f7a44; }
.uplab-status--err { background: #fbeaea; color: #b0322a; }

.uplab-code { margin: 12px 0 0; border-radius: 8px; overflow: hidden; border: 1px solid #e2e6ea; }
.uplab-code__bar { display: flex; align-items: center; gap: 10px; background: #f0f3f6; border-bottom: 1px solid #e6e9ee; padding: 7px 12px; }
.uplab-code__title { font-size: 12px; color: #475569; font-weight: 600; }
.uplab-code__lang { font-size: 10px; text-transform: uppercase; letter-spacing: .06em; color: #7b8794; background: #e4e8ec; padding: 2px 7px; border-radius: 4px; }
.uplab-code__copy { margin-left: auto; padding: 3px 12px; font-size: 11.5px; color: #475569; background: #fff; border: 1px solid #d6dbe2; border-radius: 5px; cursor: pointer; transition: background .15s, color .15s; }
.uplab-code__copy:hover { background: #e9edf1; color: #0f172a; }
.uplab-code__copy.is-copied { color: #1f7a44; border-color: #b8dcc6; }
.uplab-code pre { margin: 0; background: #f8fafc; color: #24292f; padding: 14px 16px; overflow-x: auto; }
.uplab-code pre code { background: none; padding: 0; color: inherit; font-size: 12.5px; line-height: 1.6; }

/* Токены подсветки синтаксиса (светлая тема, GitHub-like) */
.uplab-hl .hl-key { color: #0550ae; }
.uplab-hl .hl-str { color: #0a7b34; }
.uplab-hl .hl-num { color: #b45309; }
.uplab-hl .hl-lit { color: #8250df; }
.uplab-hl .hl-comment { color: #6e7781; font-style: italic; }
.uplab-hl .hl-flag { color: #8250df; }
.uplab-hl .hl-cmd { color: #0550ae; font-weight: 600; }
.uplab-hl .hl-var { color: #953800; }
.uplab-hl .hl-kw { color: #cf222e; }
.uplab-hl .hl-ph { color: #b45309; background: #fdefd6; border-radius: 3px; padding: 0 2px; font-weight: 600; }

.uplab-op.is-hidden { display: none; }
.uplab-empty { padding: 24px; text-align: center; color: #94a3b8; font-size: 14px; }
</style>
HTML;
    }

    protected static function script(): string
    {
        return <<<'HTML'
<script>
(function () {
    if (window.uplabDocsToggle) { return; }

    window.uplabDocsToggle = function (btn) {
        btn.closest('.uplab-op').classList.toggle('is-collapsed');
    };

    window.uplabDocsToggleAll = function (expand) {
        document.querySelectorAll('.uplab-op').forEach(function (op) {
            op.classList.toggle('is-collapsed', !expand);
        });
    };

    window.uplabDocsCopy = function (btn) {
        var pre = btn.closest('.uplab-code').querySelector('pre code');
        var text = pre ? pre.innerText : '';
        var done = function () {
            var orig = btn.textContent;
            btn.textContent = 'Скопировано';
            btn.classList.add('is-copied');
            setTimeout(function () { btn.textContent = orig; btn.classList.remove('is-copied'); }, 1500);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done).catch(function () {});
        } else {
            var ta = document.createElement('textarea');
            ta.value = text; document.body.appendChild(ta); ta.select();
            try { document.execCommand('copy'); done(); } catch (e) {}
            document.body.removeChild(ta);
        }
    };

    window.uplabDocsFilter = function (q) {
        q = (q || '').trim().toLowerCase();
        var anyVisible = false;
        document.querySelectorAll('.uplab-op').forEach(function (op) {
            var hay = op.getAttribute('data-filter') || '';
            var match = q === '' || hay.indexOf(q) !== -1;
            op.classList.toggle('is-hidden', !match);
            if (match) {
                anyVisible = true;
                if (q !== '') { op.classList.remove('is-collapsed'); }
            }
        });
        var body = document.querySelector('.uplab-docs__body');
        if (!body) { return; }
        var empty = body.querySelector('.uplab-empty');
        if (!anyVisible && !empty) {
            empty = document.createElement('div');
            empty.className = 'uplab-empty';
            empty.textContent = 'Ничего не найдено';
            body.appendChild(empty);
        } else if (anyVisible && empty) {
            empty.remove();
        }
    };

    // Клик по якорю "#": раскрыть, обновить hash и скопировать ссылку.
    window.uplabDocsAnchor = function (e, el) {
        e.preventDefault();
        e.stopPropagation();
        var op = el.closest('.uplab-op');
        if (!op || !op.id) { return; }
        op.classList.remove('is-collapsed');
        if (history.replaceState) {
            history.replaceState(null, '', '#' + op.id);
        } else {
            location.hash = op.id;
        }
        op.scrollIntoView({ behavior: 'smooth', block: 'start' });
        var url = location.href.split('#')[0] + '#' + op.id;
        var flash = function () {
            el.classList.add('is-copied');
            setTimeout(function () { el.classList.remove('is-copied'); }, 1500);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(flash).catch(flash);
        } else {
            flash();
        }
    };

    // Раскрыть и подсветить операцию из #hash при загрузке/смене хэша.
    function uplabOpenFromHash() {
        var h = location.hash ? decodeURIComponent(location.hash.slice(1)) : '';
        if (!h) { return; }
        var op = document.getElementById(h);
        if (!op || op.className.indexOf('uplab-op') === -1) { return; }
        op.classList.remove('is-collapsed', 'is-hidden');
        op.scrollIntoView({ block: 'start' });
        op.classList.add('is-target');
        setTimeout(function () { op.classList.remove('is-target'); }, 2200);
    }

    uplabOpenFromHash();
    window.addEventListener('hashchange', uplabOpenFromHash);
})();
</script>
HTML;
    }
}
