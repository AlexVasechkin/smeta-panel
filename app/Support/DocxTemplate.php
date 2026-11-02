<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use RuntimeException;
use ZipArchive;

/**
 * Разбор docx-шаблона: нормализация плейсхолдеров {{ ... }} и состав полей.
 *
 * Зачем нормализация. Word хранит абзац как набор runs и легко рвёт
 * {{works[].name}} на несколько <w:t> — из-за автозамены, проверки орфографии
 * или правки по живому. Такой плейсхолдер не найдёт ни один подстановщик.
 * Поэтому при загрузке шаблон переписывается: каждый плейсхолдер собирается
 * обратно в один <w:t>, пробелы внутри скобок убираются. На диск попадает уже
 * починенный файл, и генерация документа работает с предсказуемой разметкой.
 *
 * Синтаксис плейсхолдеров:
 *   {{contract.number}}  — одиночное поле;
 *   {{works[].name}}     — колонка повторяющейся строки таблицы (группа works).
 *
 * Скобки [] обязательны именно потому, что таблицами в Word часто верстают
 * макет: по одному лишь положению внутри <w:tr> отличить колонку таблицы от
 * обычного поля невозможно.
 */
class DocxTemplate
{
    /**
     * Пространство имён WordprocessingML.
     */
    private const WORD_NAMESPACE = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /**
     * Части архива, в которых ищем плейсхолдеры: тело, колонтитулы, сноски.
     */
    private const PART_PATTERN = '#^word/(document|header\d*|footer\d*|footnotes|endnotes)\.xml$#';

    /**
     * Плейсхолдер целиком, вместе со скобками.
     */
    private const MACRO_PATTERN = '/\{\{(.*?)\}\}/s';

    /**
     * Допустимое имя: сегменты через точку, [] — только после первого сегмента.
     */
    private const NAME_PATTERN = '/^[a-zA-Z_][a-zA-Z0-9_]*(\[\])?(\.[a-zA-Z_][a-zA-Z0-9_]*)*$/';

    /**
     * Одиночные поля шаблона.
     *
     * @var array<int, string>
     */
    private array $fields = [];

    /**
     * Табличные группы: имя группы => список колонок.
     *
     * @var array<string, array<int, string>>
     */
    private array $tables = [];

    /**
     * Строки таблицы, в которых встретилась каждая группа (для проверок).
     *
     * @var array<string, array<int, string>>
     */
    private array $groupRows = [];

    /**
     * @var array<int, string>
     */
    private array $warnings = [];

    private bool $parsed = false;

    public function __construct(private readonly string $path) {}

    /**
     * Разобрать шаблон и переписать файл с нормализованными плейсхолдерами.
     */
    public function parse(): self
    {
        if ($this->parsed) {
            return $this;
        }

        $zip = new ZipArchive;

        if ($zip->open($this->path) !== true) {
            throw new RuntimeException('Не удалось открыть docx-файл.');
        }

        if ($zip->locateName('word/document.xml') === false) {
            $zip->close();

            throw new RuntimeException('Файл не похож на документ Word: нет word/document.xml.');
        }

        $replacements = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            if ($name === false || preg_match(self::PART_PATTERN, $name) !== 1) {
                continue;
            }

            $xml = $zip->getFromIndex($i);

            if ($xml === false || $xml === '') {
                continue;
            }

            $processed = $this->processPart($name, $xml);

            if ($processed !== $xml) {
                $replacements[$name] = $processed;
            }
        }

        foreach ($replacements as $name => $xml) {
            $zip->addFromString($name, $xml);
        }

        $zip->close();

        $this->finish();
        $this->parsed = true;

        return $this;
    }

    /**
     * Одиночные поля шаблона.
     *
     * @return array<int, string>
     */
    public function fields(): array
    {
        return $this->fields;
    }

    /**
     * Табличные группы: имя группы => список колонок.
     *
     * @return array<string, array<int, string>>
     */
    public function tables(): array
    {
        return $this->tables;
    }

    /**
     * Замечания к шаблону. Не ошибки: шаблон сохраняется, но стоит показать их
     * пользователю — скорее всего, плейсхолдер набран не так, как задумано.
     *
     * @return array<int, string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /**
     * Состав шаблона для хранения в колонке placeholders.
     *
     * @return array{fields: array<int, string>, tables: array<string, array<int, string>>}
     */
    public function placeholders(): array
    {
        return ['fields' => $this->fields, 'tables' => $this->tables];
    }

    /**
     * Обработать одну часть архива: починить разорванные плейсхолдеры
     * и собрать их состав.
     */
    private function processPart(string $part, string $xml): string
    {
        $dom = new DOMDocument;
        $dom->preserveWhiteSpace = true;

        // Word пишет корректный XML; на битом файле не падаем, а пропускаем часть.
        if (! @$dom->loadXML($xml)) {
            $this->warnings[] = "Не удалось разобрать часть документа {$part}.";

            return $xml;
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', self::WORD_NAMESPACE);

        foreach ($xpath->query('//w:p') ?: [] as $paragraph) {
            if ($paragraph instanceof DOMElement) {
                $this->repairParagraph($xpath, $paragraph);
            }
        }

        $this->collect($part, $xpath);

        return $dom->saveXML() ?: $xml;
    }

    /**
     * Собрать плейсхолдеры абзаца обратно в один run каждый.
     *
     * Текст абзаца склеивается, в нём ищутся плейсхолдеры, после чего текст
     * раскладывается обратно по <w:t>: плейсхолдер целиком попадает в тот узел,
     * где он начался (и наследует его оформление), а его «хвосты» из соседних
     * узлов удаляются. Символы вне плейсхолдеров остаются на своих местах,
     * поэтому смешанное оформление внутри абзаца не страдает.
     */
    private function repairParagraph(DOMXPath $xpath, DOMElement $paragraph): void
    {
        $nodes = [];

        foreach ($xpath->query('.//w:t', $paragraph) ?: [] as $node) {
            if ($node instanceof DOMElement) {
                $nodes[] = $node;
            }
        }

        if ($nodes === []) {
            return;
        }

        $text = '';

        // Карта «байт текста абзаца => индекс узла, которому он принадлежит».
        $owner = [];

        foreach ($nodes as $index => $node) {
            $value = $node->textContent;
            $text .= $value;

            for ($i = 0, $length = strlen($value); $i < $length; $i++) {
                $owner[] = $index;
            }
        }

        if (! str_contains($text, '{{')) {
            return;
        }

        // Начало плейсхолдера => [позиция после него, нормализованный текст].
        $macros = [];

        if (preg_match_all(self::MACRO_PATTERN, $text, $matches, PREG_OFFSET_CAPTURE) !== false) {
            foreach ($matches[0] as $match) {
                [$raw, $offset] = $match;
                $macros[$offset] = [$offset + strlen($raw), $this->normalizeMacro($raw)];
            }
        }

        if ($macros === []) {
            return;
        }

        $buffers = array_fill(0, count($nodes), '');
        $position = 0;
        $length = strlen($text);

        while ($position < $length) {
            if (isset($macros[$position])) {
                [$end, $normalized] = $macros[$position];

                $buffers[$owner[$position]] .= $normalized;
                $position = $end;

                continue;
            }

            $buffers[$owner[$position]] .= $text[$position];
            $position++;
        }

        foreach ($nodes as $index => $node) {
            if ($node->textContent === $buffers[$index]) {
                continue;
            }

            while ($node->firstChild !== null) {
                $node->removeChild($node->firstChild);
            }

            $node->appendChild($node->ownerDocument->createTextNode($buffers[$index]));

            // Пробелы по краям run'а Word схлопывает, если это не запрещено явно.
            $node->setAttribute('xml:space', 'preserve');
        }
    }

    /**
     * Убрать пробелы внутри скобок: подстановщик сравнивает имя посимвольно,
     * поэтому {{ works[].name }} и {{works[].name}} — разные плейсхолдеры.
     */
    private function normalizeMacro(string $raw): string
    {
        $inner = substr($raw, 2, -2);

        return '{{'.preg_replace('/\s+/u', '', $inner).'}}';
    }

    /**
     * Собрать состав плейсхолдеров части. Вызывается после починки абзацев,
     * поэтому каждый плейсхолдер целиком лежит в одном <w:t>.
     */
    private function collect(string $part, DOMXPath $xpath): void
    {
        foreach ($xpath->query('//w:t') ?: [] as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            if (preg_match_all(self::MACRO_PATTERN, $node->textContent, $matches) < 1) {
                continue;
            }

            $row = $this->closestRow($node);

            // Ключ строки — её путь в документе: устойчив в пределах разбора
            // и, в отличие от объекта DOM, не зависит от времени жизни обёртки.
            $rowKey = $row === null ? null : $part.$row->getNodePath();

            foreach ($matches[1] as $name) {
                $this->register($name, $rowKey);
            }
        }
    }

    /**
     * Ближайшая строка таблицы, в которой лежит узел. Для вложенных таблиц
     * это внутренняя строка — именно она повторяется.
     */
    private function closestRow(DOMNode $node): ?DOMElement
    {
        for ($current = $node->parentNode; $current !== null; $current = $current->parentNode) {
            if ($current instanceof DOMElement
                && $current->localName === 'tr'
                && $current->namespaceURI === self::WORD_NAMESPACE) {
                return $current;
            }
        }

        return null;
    }

    /**
     * Учесть один найденный плейсхолдер.
     */
    private function register(string $name, ?string $rowKey): void
    {
        if ($name === '') {
            $this->addWarning('Пустой плейсхолдер {{}} — его нечем заполнить.');

            return;
        }

        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            $this->addWarning("Плейсхолдер {{{$name}}} содержит недопустимое имя: разрешены латиница, цифры, подчёркивание и точка.");

            return;
        }

        if (! str_contains($name, '[]')) {
            if (! in_array($name, $this->fields, true)) {
                $this->fields[] = $name;
            }

            return;
        }

        $position = strpos($name, '[]');
        $group = substr($name, 0, $position);
        $column = ltrim(substr($name, $position + 2), '.');

        if ($column === '') {
            $this->addWarning("Плейсхолдер {{{$name}}} задаёт таблицу без колонки — нужно {{{$group}[].имя_колонки}}.");

            return;
        }

        if ($rowKey === null) {
            $this->addWarning("Плейсхолдер {{{$name}}} помечен как колонка таблицы, но находится вне строки таблицы.");
        } else {
            $this->groupRows[$group][$rowKey] = $rowKey;
        }

        if (! isset($this->tables[$group])) {
            $this->tables[$group] = [];
        }

        if (! in_array($column, $this->tables[$group], true)) {
            $this->tables[$group][] = $column;
        }
    }

    /**
     * Финальные проверки и приведение результата к стабильному порядку.
     */
    private function finish(): void
    {
        foreach ($this->groupRows as $group => $rows) {
            if (count($rows) > 1) {
                $this->addWarning("Колонки таблицы «{$group}» расположены в разных строках — повторяться будет только первая из них.");
            }
        }

        sort($this->fields);
        ksort($this->tables);

        foreach ($this->tables as $group => $columns) {
            sort($columns);
            $this->tables[$group] = $columns;
        }

        if ($this->fields === [] && $this->tables === []) {
            $this->addWarning('В шаблоне не найдено ни одного плейсхолдера {{ ... }}.');
        }
    }

    private function addWarning(string $warning): void
    {
        if (! in_array($warning, $this->warnings, true)) {
            $this->warnings[] = $warning;
        }
    }
}
