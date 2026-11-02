<?php

namespace Tests\Feature;

use App\Models\DocumentTemplate;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\DocxBuilder;
use Tests\TestCase;

class DocumentTemplateTest extends TestCase
{
    use RefreshDatabase;

    private string $disk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->disk = (string) config('filesystems.default');
        Storage::fake($this->disk);
    }

    /**
     * Пользователь-владелец организации: шаблоны принадлежат ей.
     *
     * @return array{0: User, 1: Organization}
     */
    private function userWithOrganization(): array
    {
        $user = User::factory()->create();
        $organization = Organization::create(['name' => 'Своя орг']);
        $organization->members()->create(['user_id' => $user->id, 'is_owner' => true]);

        return [$user, $organization];
    }

    /**
     * Договор с одиночными полями и таблицей работ.
     */
    private function docx(): string
    {
        return DocxBuilder::build(
            DocxBuilder::paragraph('Договор № {{contract.number}} от {{contract.date}}')
            .DocxBuilder::table(
                DocxBuilder::row(DocxBuilder::cell('№'), DocxBuilder::cell('Наименование')),
                DocxBuilder::row(DocxBuilder::cell('{{works[].num}}'), DocxBuilder::cell('{{works[].name}}')),
            )
        );
    }

    private function file(string $name = 'contract.docx', ?string $binary = null): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $binary ?? $this->docx());
    }

    /**
     * Готовая карточка шаблона в базе (файл кладём на фейковый диск).
     */
    private function templateFor(Organization $organization, array $attributes = []): DocumentTemplate
    {
        $path = 'document-templates/'.$organization->id.'/'.uniqid().'.docx';
        Storage::disk($this->disk)->put($path, $this->docx());

        return DocumentTemplate::create($attributes + [
            'organization_id' => $organization->id,
            'name' => 'Договор подряда',
            'description' => null,
            'disk' => $this->disk,
            'path' => $path,
            'original_name' => 'contract.docx',
            'size' => 1024,
            'checksum' => str_repeat('a', 64),
            'placeholders' => ['fields' => ['contract.number'], 'tables' => ['works' => ['name']]],
            'warnings' => [],
        ]);
    }

    public function test_guests_cannot_see_the_storage(): void
    {
        $this->get(route('document-templates.index'))->assertRedirect(route('login'));
    }

    public function test_storage_lists_only_own_templates(): void
    {
        [$user, $organization] = $this->userWithOrganization();
        $this->templateFor($organization, ['name' => 'Свой шаблон']);

        $other = Organization::create(['name' => 'Чужая орг']);
        $this->templateFor($other, ['name' => 'Чужой шаблон']);

        $this->actingAs($user)
            ->get(route('document-templates.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('document-templates/index')
                ->has('templates.data', 1)
                ->where('templates.data.0.name', 'Свой шаблон'));
    }

    public function test_storage_can_be_searched(): void
    {
        [$user, $organization] = $this->userWithOrganization();
        $this->templateFor($organization, ['name' => 'Договор подряда']);
        $this->templateFor($organization, ['name' => 'Акт приёмки', 'description' => 'Форма KS-2']);

        $this->actingAs($user)
            ->get(route('document-templates.index', ['search' => 'Акт']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('templates.data', 1)
                ->where('templates.data.0.name', 'Акт приёмки'));

        // Поиск идёт и по описанию, и без учёта регистра. Латиница проверяет
        // это на любом драйвере: like в SQLite регистронезависим только для
        // ASCII, кириллицу вытягивает уже ilike в PostgreSQL.
        $this->actingAs($user)
            ->get(route('document-templates.index', ['search' => 'ks-2']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('templates.data', 1)
                ->where('templates.data.0.name', 'Акт приёмки'));
    }

    public function test_create_page_is_displayed(): void
    {
        [$user] = $this->userWithOrganization();

        $this->actingAs($user)
            ->get(route('document-templates.create'))
            ->assertInertia(fn (Assert $page) => $page->component('document-templates/create'));
    }

    public function test_template_is_stored_and_parsed(): void
    {
        [$user, $organization] = $this->userWithOrganization();

        $this->actingAs($user)
            ->post(route('document-templates.store'), [
                'name' => 'Договор подряда',
                'description' => 'Основная форма',
                'file' => $this->file(),
            ])
            ->assertRedirect(route('document-templates.index'));

        $template = DocumentTemplate::sole();

        $this->assertSame($organization->id, $template->organization_id);
        $this->assertSame('contract.docx', $template->original_name);
        $this->assertSame(['contract.date', 'contract.number'], $template->fields());
        $this->assertSame(['works' => ['name', 'num']], $template->tables());
        $this->assertSame([], $template->warnings);

        Storage::disk($this->disk)->assertExists($template->path);
    }

    public function test_stored_file_is_normalised(): void
    {
        [$user] = $this->userWithOrganization();

        // Word разорвал плейсхолдер на три run'а и оставил пробелы в скобках.
        $binary = DocxBuilder::build(
            DocxBuilder::paragraph('№ ', '{{contr', 'act.num', 'ber}} и {{ org.inn }}')
        );

        $this->actingAs($user)->post(route('document-templates.store'), [
            'name' => 'Рваный шаблон',
            'file' => $this->file('broken.docx', $binary),
        ]);

        $template = DocumentTemplate::sole();

        $this->assertSame(['contract.number', 'org.inn'], $template->fields());

        // На диск попал уже починенный файл.
        $stored = Storage::disk($this->disk)->get($template->path);
        $this->assertContains('{{contract.number}}', DocxBuilder::texts($stored));
    }

    public function test_warnings_are_saved_with_the_template(): void
    {
        [$user] = $this->userWithOrganization();

        $this->actingAs($user)->post(route('document-templates.store'), [
            'name' => 'Спорный шаблон',
            'file' => $this->file('odd.docx', DocxBuilder::build(DocxBuilder::paragraph('{{works[].name}}'))),
        ]);

        $this->assertStringContainsString('вне строки таблицы', implode("\n", DocumentTemplate::sole()->warnings));
    }

    public function test_it_rejects_a_file_that_is_not_a_word_document(): void
    {
        [$user] = $this->userWithOrganization();

        $this->actingAs($user)
            ->post(route('document-templates.store'), [
                'name' => 'Не документ',
                'file' => UploadedFile::fake()->create('notes.txt', 10),
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, DocumentTemplate::count());
    }

    public function test_it_rejects_a_docx_that_cannot_be_parsed(): void
    {
        [$user] = $this->userWithOrganization();

        // Расширение верное, содержимое — не архив.
        $this->actingAs($user)
            ->post(route('document-templates.store'), [
                'name' => 'Битый файл',
                'file' => $this->file('broken.docx', 'это не zip'),
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, DocumentTemplate::count());
        Storage::disk($this->disk)->assertDirectoryEmpty('/');
    }

    public function test_it_requires_a_name_and_a_file(): void
    {
        [$user] = $this->userWithOrganization();

        $this->actingAs($user)
            ->post(route('document-templates.store'), [])
            ->assertSessionHasErrors(['name', 'file']);
    }

    public function test_it_rejects_a_duplicate_name_in_the_same_organization(): void
    {
        [$user, $organization] = $this->userWithOrganization();
        $this->templateFor($organization, ['name' => 'Договор подряда']);

        $this->actingAs($user)
            ->post(route('document-templates.store'), [
                'name' => 'Договор подряда',
                'file' => $this->file(),
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_the_same_name_is_allowed_in_another_organization(): void
    {
        [$user] = $this->userWithOrganization();

        $other = Organization::create(['name' => 'Чужая орг']);
        $this->templateFor($other, ['name' => 'Договор подряда']);

        $this->actingAs($user)
            ->post(route('document-templates.store'), [
                'name' => 'Договор подряда',
                'file' => $this->file(),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, DocumentTemplate::count());
    }

    public function test_show_and_edit_pages_are_displayed(): void
    {
        [$user, $organization] = $this->userWithOrganization();
        $template = $this->templateFor($organization);

        $this->actingAs($user)
            ->get(route('document-templates.show', $template))
            ->assertInertia(fn (Assert $page) => $page
                ->component('document-templates/show')
                ->where('template.name', 'Договор подряда')
                ->where('template.tables.0.name', 'works'));

        $this->actingAs($user)
            ->get(route('document-templates.edit', $template))
            ->assertInertia(fn (Assert $page) => $page->component('document-templates/edit'));
    }

    public function test_template_can_be_renamed_without_replacing_the_file(): void
    {
        [$user, $organization] = $this->userWithOrganization();
        $template = $this->templateFor($organization);
        $path = $template->path;

        $this->actingAs($user)
            ->put(route('document-templates.update', $template), [
                'name' => 'Договор подряда 2026',
                'description' => 'Обновлённая формулировка',
            ])
            ->assertRedirect(route('document-templates.show', $template));

        $template->refresh();

        $this->assertSame('Договор подряда 2026', $template->name);
        $this->assertSame($path, $template->path);
        Storage::disk($this->disk)->assertExists($path);
    }

    public function test_replacing_the_file_removes_the_previous_object(): void
    {
        [$user, $organization] = $this->userWithOrganization();
        $template = $this->templateFor($organization);
        $previousPath = $template->path;

        $binary = DocxBuilder::build(DocxBuilder::paragraph('{{act.number}}'));

        $this->actingAs($user)->put(route('document-templates.update', $template), [
            'name' => $template->name,
            'file' => $this->file('act.docx', $binary),
        ]);

        $template->refresh();

        $this->assertNotSame($previousPath, $template->path);
        $this->assertSame(['act.number'], $template->fields());
        $this->assertSame('act.docx', $template->original_name);

        Storage::disk($this->disk)->assertMissing($previousPath);
        Storage::disk($this->disk)->assertExists($template->path);
    }

    public function test_template_is_deleted_together_with_its_file(): void
    {
        [$user, $organization] = $this->userWithOrganization();
        $template = $this->templateFor($organization);
        $path = $template->path;

        $this->actingAs($user)
            ->delete(route('document-templates.destroy', $template))
            ->assertRedirect(route('document-templates.index'));

        $this->assertSame(0, DocumentTemplate::count());
        Storage::disk($this->disk)->assertMissing($path);
    }

    public function test_template_can_be_downloaded(): void
    {
        [$user, $organization] = $this->userWithOrganization();
        $template = $this->templateFor($organization);

        $this->actingAs($user)
            ->get(route('document-templates.download', $template))
            ->assertOk()
            ->assertDownload('contract.docx');
    }

    public function test_a_template_of_another_organization_is_not_accessible(): void
    {
        [$user] = $this->userWithOrganization();

        $other = Organization::create(['name' => 'Чужая орг']);
        $template = $this->templateFor($other);

        $this->actingAs($user)->get(route('document-templates.show', $template))->assertNotFound();
        $this->actingAs($user)->get(route('document-templates.edit', $template))->assertNotFound();
        $this->actingAs($user)->get(route('document-templates.download', $template))->assertNotFound();
        $this->actingAs($user)->put(route('document-templates.update', $template), ['name' => 'Чужой'])->assertNotFound();
        $this->actingAs($user)->delete(route('document-templates.destroy', $template))->assertNotFound();
    }

    public function test_a_user_without_an_organization_cannot_use_the_storage(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('document-templates.index'))->assertNotFound();
        $this->actingAs($user)->get(route('document-templates.create'))->assertNotFound();
    }
}
