<?php

namespace App\Support\Documents;

use RuntimeException;
use ZipArchive;

/**
 * Общая генерация .docx подстановкой плейсхолдеров вида {{ ... }} в шаблон.
 *
 * Word может разбивать плейсхолдер на несколько текстовых узлов (runs), поэтому
 * замена выполняется по нормализованному токену внутри word/document.xml.
 * Наследники задают карту «токен => значение» для конкретного шаблона.
 */
abstract class AbstractDocxWriter
{
    /**
     * Карта нормализованный токен => значение для подстановки в шаблон.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    abstract protected function map(array $payload): array;

    /**
     * Блоки «сырого» OOXML для подстановки вместо абзаца с плейсхолдером.
     *
     * Скалярная замена {{ ... }} экранирует значение и не может вставить таблицу
     * или иную разметку. Для таких плейсхолдеров (например, таблица позиций акта)
     * наследник возвращает карту «токен => готовый XML»; абзац, содержащий
     * {{ токен }}, целиком заменяется этим XML (разрыв страницы сохраняется).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    protected function rawBlocks(array $payload): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function write(array $payload, string $templateAbsPath, string $outAbsPath): void
    {
        if (! copy($templateAbsPath, $outAbsPath)) {
            throw new RuntimeException('Не удалось подготовить файл документа.');
        }

        $zip = new ZipArchive;
        if ($zip->open($outAbsPath) !== true) {
            throw new RuntimeException('Не удалось открыть шаблон документа.');
        }

        $xml = (string) $zip->getFromName('word/document.xml');
        $xml = $this->mergePlaceholderRuns($xml);
        $xml = $this->injectRawBlocks($xml, $this->rawBlocks($payload));
        $zip->addFromString('word/document.xml', $this->replace($xml, $this->map($payload)));
        $zip->close();
    }

    /**
     * Заменить абзац, содержащий {{ токен }}, на готовый XML-блок.
     *
     * @param  array<string, string>  $blocks
     */
    private function injectRawBlocks(string $xml, array $blocks): string
    {
        foreach ($blocks as $token => $rawXml) {
            $pattern = '/<w:p\b[^>]*>(?:(?!<\/w:p>).)*?\{\{[^{}]*'.preg_quote($token, '/').'[^{}]*\}\}(?:(?!<\/w:p>).)*?<\/w:p>/su';

            $xml = (string) preg_replace_callback($pattern, function (array $m) use ($rawXml): string {
                // Сохранить разрыв страницы, если он был в исходном абзаце.
                $pageBreak = str_contains($m[0], 'w:type="page"')
                    ? '<w:p><w:r><w:br w:type="page"/></w:r></w:p>'
                    : '';

                return $rawXml.$pageBreak;
            }, $xml, 1);
        }

        return $xml;
    }

    /**
     * Убрать XML-теги внутри каждого {{ ... }}, схлопнув плейсхолдер в один текст.
     */
    private function mergePlaceholderRuns(string $xml): string
    {
        return (string) preg_replace_callback(
            '/\{\{.*?\}\}/su',
            fn (array $m) => preg_replace('/<[^>]+>/', '', $m[0]),
            $xml,
        );
    }

    /**
     * Заменить все {{ ... }} по нормализованному токену; неизвестные — очистить.
     *
     * @param  array<string, string>  $map
     */
    private function replace(string $xml, array $map): string
    {
        // Ключи карты нормализуем так же, как и токены из шаблона.
        $normalized = [];
        foreach ($map as $key => $value) {
            $normalized[$this->normalize($key)] = $value;
        }

        return (string) preg_replace_callback('/\{\{(.+?)\}\}/su', function (array $m) use ($normalized) {
            $token = $this->normalize($m[1]);

            return htmlspecialchars($normalized[$token] ?? '', ENT_QUOTES | ENT_XML1, 'UTF-8');
        }, $xml);
    }

    /**
     * Нормализация токена: привести типографские кавычки к прямым и убрать
     * все пробелы (включая неразрывные), чтобы сопоставление не зависело от вёрстки.
     */
    private function normalize(string $token): string
    {
        $token = str_replace(['”', '“', '„', '"'], '"', $token);

        return (string) preg_replace('/[\s\p{Z}]+/u', '', $token);
    }
}
