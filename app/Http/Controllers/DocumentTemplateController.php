<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDocumentTemplateRequest;
use App\Http\Requests\UpdateDocumentTemplateRequest;
use App\Models\DocumentTemplate;
use App\Models\Organization;
use App\Support\DocxTemplate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Хранилище шаблонов документов: .docx с плейсхолдерами {{ ... }}.
 *
 * Файл лежит на диске из config/filesystems.php (в проде — S3), карточка и
 * разобранный состав полей — в таблице document_templates. При загрузке файл
 * нормализуется (см. App\Support\DocxTemplate), поэтому на диск попадает
 * вариант, пригодный для подстановки.
 */
class DocumentTemplateController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $organization = $this->organization($request);

        $search = trim($request->string('search')->toString());

        $templates = DocumentTemplate::query()
            ->forOrganization($organization->id)
            ->when($search !== '', function (Builder $query) use ($search) {
                $like = $this->likeOperator();

                $query->where(function (Builder $inner) use ($search, $like) {
                    $inner->where('name', $like, "%{$search}%")
                        ->orWhere('description', $like, "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (DocumentTemplate $template) => [
                'id' => $template->id,
                'name' => $template->name,
                'description' => $template->description,
                'size' => $template->size,
                'field_count' => count($template->fields()),
                'table_count' => count($template->tables()),
                'warning_count' => count($template->warnings ?? []),
                'updated_at' => $template->updated_at?->format('d.m.Y'),
            ]);

        return Inertia::render('document-templates/index', [
            'templates' => $templates,
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request): Response
    {
        $this->organization($request);

        return Inertia::render('document-templates/create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDocumentTemplateRequest $request): RedirectResponse
    {
        $organization = $this->organization($request);

        /** @var UploadedFile $file */
        $file = $request->file('file');

        $attributes = $this->storeDocx($file, $organization);

        DB::transaction(fn () => DocumentTemplate::create([
            'organization_id' => $organization->id,
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            ...$attributes,
        ]));

        return to_route('document-templates.index')->with('success', 'Шаблон загружен.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, DocumentTemplate $documentTemplate): Response
    {
        $this->assertOwned($request, $documentTemplate);

        return Inertia::render('document-templates/show', [
            'template' => $this->payload($documentTemplate),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, DocumentTemplate $documentTemplate): Response
    {
        $this->assertOwned($request, $documentTemplate);

        return Inertia::render('document-templates/edit', [
            'template' => $this->payload($documentTemplate),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDocumentTemplateRequest $request, DocumentTemplate $documentTemplate): RedirectResponse
    {
        $organization = $this->assertOwned($request, $documentTemplate);

        $file = $request->file('file');

        // Новый файл заменяет прежний: старый объект на диске больше не нужен.
        $attributes = $file instanceof UploadedFile
            ? $this->storeDocx($file, $organization)
            : [];

        $previousPath = $documentTemplate->path;
        $previousDisk = $documentTemplate->disk;

        DB::transaction(fn () => $documentTemplate->update([
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            ...$attributes,
        ]));

        if ($attributes !== []) {
            Storage::disk($previousDisk)->delete($previousPath);
        }

        return to_route('document-templates.show', $documentTemplate)->with('success', 'Шаблон обновлён.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, DocumentTemplate $documentTemplate): RedirectResponse
    {
        $this->assertOwned($request, $documentTemplate);

        $disk = $documentTemplate->disk;
        $path = $documentTemplate->path;

        DB::transaction(fn () => $documentTemplate->delete());

        Storage::disk($disk)->delete($path);

        return to_route('document-templates.index')->with('success', 'Шаблон удалён.');
    }

    /**
     * Скачать исходный файл шаблона (уже нормализованный).
     */
    public function download(Request $request, DocumentTemplate $documentTemplate): StreamedResponse
    {
        $this->assertOwned($request, $documentTemplate);

        return Storage::disk($documentTemplate->disk)
            ->download($documentTemplate->path, $documentTemplate->original_name);
    }

    /**
     * Разобрать, нормализовать и положить файл на диск.
     *
     * @return array<string, mixed>
     */
    private function storeDocx(UploadedFile $file, Organization $organization): array
    {
        // Парсер переписывает файл на месте, поэтому работаем с копией,
        // а не с временным файлом самой загрузки.
        $working = tempnam(sys_get_temp_dir(), 'docx');

        if ($working === false || ! copy($file->getRealPath(), $working)) {
            throw new RuntimeException('Не удалось подготовить загруженный файл.');
        }

        try {
            $template = (new DocxTemplate($working))->parse();

            $path = sprintf('document-templates/%d/%s.docx', $organization->id, Str::ulid());
            $disk = (string) config('filesystems.default');

            Storage::disk($disk)->put($path, file_get_contents($working));

            return [
                'disk' => $disk,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'size' => filesize($working) ?: 0,
                'checksum' => hash_file('sha256', $working),
                'placeholders' => $template->placeholders(),
                'warnings' => $template->warnings(),
            ];
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        } finally {
            @unlink($working);
        }
    }

    /**
     * Карточка шаблона для фронтенда.
     *
     * @return array<string, mixed>
     */
    private function payload(DocumentTemplate $template): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'description' => $template->description,
            'original_name' => $template->original_name,
            'size' => $template->size,
            'checksum' => $template->checksum,
            'fields' => $template->fields(),

            // Таблицы отдаём списком, чтобы порядок не зависел от объекта JS.
            'tables' => array_map(
                fn (string $group, array $columns) => ['name' => $group, 'columns' => $columns],
                array_keys($template->tables()),
                array_values($template->tables()),
            ),
            'warnings' => $template->warnings ?? [],
            'updated_at' => $template->updated_at?->format('d.m.Y H:i'),
        ];
    }

    /**
     * Организация текущего пользователя — владелец шаблонов.
     */
    private function organization(Request $request): Organization
    {
        return $request->user()->currentOrganization()
            ?? abort(404);
    }

    /**
     * Шаблон должен принадлежать организации текущего пользователя.
     */
    private function assertOwned(Request $request, DocumentTemplate $template): Organization
    {
        $organization = $this->organization($request);

        abort_unless($template->organization_id === $organization->id, 404);

        return $organization;
    }

    /**
     * Регистронезависимый поиск: ilike в PostgreSQL, like в остальных драйверах.
     */
    private function likeOperator(): string
    {
        return DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
    }
}
