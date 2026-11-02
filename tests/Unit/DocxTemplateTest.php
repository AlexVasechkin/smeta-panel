<?php

namespace Tests\Unit;

use App\Support\DocxTemplate;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\DocxBuilder;

class DocxTemplateTest extends TestCase
{
    /**
     * @var array<int, string>
     */
    private array $paths = [];

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    /**
     * Разобрать документ из готового тела word/document.xml.
     */
    private function parse(string $body): DocxTemplate
    {
        return (new DocxTemplate($this->write(DocxBuilder::build($body))))->parse();
    }

    private function write(string $binary): string
    {
        $path = tempnam(sys_get_temp_dir(), 'docx-test');
        file_put_contents($path, $binary);
        $this->paths[] = $path;

        return $path;
    }

    public function test_it_collects_single_fields(): void
    {
        $template = $this->parse(
            DocxBuilder::paragraph('Договор № {{contract.number}} от {{contract.date}}')
        );

        $this->assertSame(['contract.date', 'contract.number'], $template->fields());
        $this->assertSame([], $template->tables());
        $this->assertSame([], $template->warnings());
    }

    public function test_it_repairs_a_placeholder_split_across_runs(): void
    {
        // Так Word и рвёт набранный текст: автозамена, проверка орфографии.
        $path = $this->write(DocxBuilder::build(
            DocxBuilder::paragraph('Договор № ', '{{contr', 'act.num', 'ber}}')
        ));

        $template = (new DocxTemplate($path))->parse();

        $this->assertSame(['contract.number'], $template->fields());

        // Плейсхолдер целиком лежит в том run'е, где он начался.
        $texts = DocxBuilder::texts((string) file_get_contents($path));
        $this->assertContains('{{contract.number}}', $texts);
    }

    public function test_it_strips_spaces_inside_braces(): void
    {
        $path = $this->write(DocxBuilder::build(
            DocxBuilder::paragraph('ИНН: {{ org.inn }}')
        ));

        $template = (new DocxTemplate($path))->parse();

        $this->assertSame(['org.inn'], $template->fields());

        $texts = DocxBuilder::texts((string) file_get_contents($path));
        $this->assertSame('ИНН: {{org.inn}}', $texts[0]);
    }

    public function test_it_keeps_text_around_a_placeholder_in_place(): void
    {
        $path = $this->write(DocxBuilder::build(
            DocxBuilder::paragraph('До ', '{{a.b}}', ' после')
        ));

        (new DocxTemplate($path))->parse();

        $this->assertSame(['До ', '{{a.b}}', ' после'], DocxBuilder::texts((string) file_get_contents($path)));
    }

    public function test_it_recognises_table_groups(): void
    {
        $template = $this->parse(DocxBuilder::table(
            DocxBuilder::row(DocxBuilder::cell('№'), DocxBuilder::cell('Наименование')),
            DocxBuilder::row(DocxBuilder::cell('{{works[].num}}'), DocxBuilder::cell('{{works[].name}}')),
        ));

        $this->assertSame(['works' => ['name', 'num']], $template->tables());
        $this->assertSame([], $template->fields());
        $this->assertSame([], $template->warnings());
    }

    public function test_a_placeholder_in_a_layout_table_stays_a_single_field(): void
    {
        // Таблицами в Word часто верстают макет — без [] это обычное поле.
        $template = $this->parse(DocxBuilder::table(
            DocxBuilder::row(DocxBuilder::cell('Адрес:'), DocxBuilder::cell('{{org.address}}')),
        ));

        $this->assertSame(['org.address'], $template->fields());
        $this->assertSame([], $template->tables());
    }

    public function test_it_warns_about_a_table_placeholder_outside_a_table(): void
    {
        $template = $this->parse(DocxBuilder::paragraph('{{works[].name}}'));

        $this->assertSame(['works' => ['name']], $template->tables());
        $this->assertStringContainsString('вне строки таблицы', implode("\n", $template->warnings()));
    }

    public function test_it_warns_when_a_group_is_spread_across_rows(): void
    {
        $template = $this->parse(DocxBuilder::table(
            DocxBuilder::row(DocxBuilder::cell('{{works[].num}}')),
            DocxBuilder::row(DocxBuilder::cell('{{works[].name}}')),
        ));

        $this->assertStringContainsString('в разных строках', implode("\n", $template->warnings()));
    }

    public function test_it_warns_about_an_invalid_name(): void
    {
        $template = $this->parse(DocxBuilder::paragraph('{{плохое имя!}}'));

        $this->assertSame([], $template->fields());
        $this->assertStringContainsString('недопустимое имя', implode("\n", $template->warnings()));
    }

    public function test_it_warns_about_a_group_without_a_column(): void
    {
        $template = $this->parse(DocxBuilder::paragraph('{{works[]}}'));

        $this->assertSame([], $template->tables());
        $this->assertStringContainsString('без колонки', implode("\n", $template->warnings()));
    }

    public function test_it_warns_when_there_are_no_placeholders(): void
    {
        $template = $this->parse(DocxBuilder::paragraph('Просто текст без разметки'));

        $this->assertStringContainsString('не найдено ни одного плейсхолдера', implode("\n", $template->warnings()));
    }

    public function test_it_rejects_a_file_that_is_not_a_word_document(): void
    {
        $path = $this->write(DocxBuilder::zip(['readme.txt' => 'не документ']));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('word/document.xml');

        (new DocxTemplate($path))->parse();
    }

    public function test_it_rejects_a_file_that_is_not_an_archive(): void
    {
        $path = $this->write('это просто текст');

        $this->expectException(RuntimeException::class);

        (new DocxTemplate($path))->parse();
    }
}
