<?php

namespace Tests\Support;

use RuntimeException;
use ZipArchive;

/**
 * Сборка минимального .docx для тестов: нужен только word/document.xml
 * с разметкой WordprocessingML.
 */
class DocxBuilder
{
    private const NAMESPACE_URI = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /**
     * Абзац из нескольких run'ов. Разбиение на несколько аргументов повторяет
     * то, как Word рвёт набранный текст на части.
     */
    public static function paragraph(string ...$runs): string
    {
        $xml = '<w:p>';

        foreach ($runs as $run) {
            $xml .= '<w:r><w:t>'.htmlspecialchars($run, ENT_XML1).'</w:t></w:r>';
        }

        return $xml.'</w:p>';
    }

    public static function cell(string ...$runs): string
    {
        return '<w:tc>'.self::paragraph(...$runs).'</w:tc>';
    }

    public static function row(string ...$cells): string
    {
        return '<w:tr>'.implode('', $cells).'</w:tr>';
    }

    public static function table(string ...$rows): string
    {
        return '<w:tbl>'.implode('', $rows).'</w:tbl>';
    }

    /**
     * Готовый .docx в виде строки байтов.
     */
    public static function build(string $body): string
    {
        $document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="'.self::NAMESPACE_URI.'"><w:body>'.$body.'</w:body></w:document>';

        return self::zip(['word/document.xml' => $document]);
    }

    /**
     * Архив с произвольным набором файлов — чтобы собрать и «не docx».
     *
     * @param  array<string, string>  $files
     */
    public static function zip(array $files): string
    {
        $path = tempnam(sys_get_temp_dir(), 'docx-fixture');

        if ($path === false) {
            throw new RuntimeException('Не удалось создать временный файл.');
        }

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($files as $name => $contents) {
            $zip->addFromString($name, $contents);
        }

        $zip->close();

        $binary = (string) file_get_contents($path);
        unlink($path);

        return $binary;
    }

    /**
     * Текст всех <w:t> документа — чтобы проверить нормализацию.
     *
     * @return array<int, string>
     */
    public static function texts(string $binary): array
    {
        $path = tempnam(sys_get_temp_dir(), 'docx-read');
        file_put_contents($path, $binary);

        $zip = new ZipArchive;
        $zip->open($path);
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();
        unlink($path);

        $dom = new \DOMDocument;
        $dom->loadXML($xml);

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('w', self::NAMESPACE_URI);

        $texts = [];

        foreach ($xpath->query('//w:t') ?: [] as $node) {
            $texts[] = $node->textContent;
        }

        return $texts;
    }
}
