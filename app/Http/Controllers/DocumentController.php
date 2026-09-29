<?php

namespace App\Http\Controllers;

use App\Enums\DocumentType;
use App\Http\Requests\StoreDocumentRequest;
use App\Models\Document;
use App\Models\Project;
use App\Services\DocumentGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function __construct(private readonly DocumentGenerator $generator) {}

    /**
     * Список всех документов.
     */
    public function index(): Response
    {
        $documents = Document::query()
            ->with(['project:id,name,client_id', 'project.client:id,name'])
            // Порядковый номер документа среди документов того же типа и объекта.
            ->select('documents.*')
            ->selectRaw(
                'row_number() over (partition by project_id, type order by id) as type_sequence'
            )
            ->orderBy('id')
            ->get();

        // Группировка: объект → тип документа → документы (по порядковому номеру).
        $projects = $documents
            ->groupBy('project_id')
            ->map(function ($projectDocuments) {
                $project = $projectDocuments->first()->project;

                $types = $projectDocuments
                    ->groupBy(fn (Document $document) => $document->type->value)
                    ->map(fn ($typeDocuments) => [
                        'type_label' => $typeDocuments->first()->type->label(),
                        'documents' => $typeDocuments
                            ->sortBy('type_sequence')
                            ->map(fn (Document $document) => [
                                'id' => $document->id,
                                'name' => $document->type->label().' '.$document->type_sequence,
                                'created_at' => $document->created_at?->format('d.m.Y H:i'),
                            ])
                            ->values(),
                    ])
                    ->values();

                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'client' => $project->client?->name,
                    'types' => $types,
                ];
            })
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return Inertia::render('documents/index', [
            'projects' => $projects,
        ]);
    }

    /**
     * Шаг 1: выбор типа документа и объекта.
     */
    public function create(): Response
    {
        $projects = Project::query()
            ->with('client:id,name')
            ->withCount([
                'documents as preliminary_estimates_count' => fn ($query) => $query
                    ->where('type', DocumentType::PreliminaryEstimate->value)->reorder(),
                'documents as estimates_count' => fn ($query) => $query
                    ->where('type', DocumentType::Estimate->value)->reorder(),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Project $project) => [
                'value' => (string) $project->id,
                'label' => $project->name.($project->client ? ' — '.$project->client->name : ''),
                'has_preliminary' => $project->preliminary_estimates_count > 0,
                'has_estimate' => $project->estimates_count > 0,
            ]);

        return Inertia::render('documents/create', [
            'documentTypes' => DocumentType::options(),
            'projects' => $projects,
        ]);
    }

    /**
     * Шаг 2: предпросмотр с редактированием данных перед сохранением.
     */
    public function preview(Request $request, DocumentType $type, Project $project): Response
    {
        abort_unless($type->isImplemented(), 404);

        $payload = $this->generator->defaultPayload(
            $project,
            $type,
            $request->user()->currentOrganization(),
        );

        // Договор и акты редактируются на отдельных страницах (плоский набор полей).
        $component = match ($type) {
            DocumentType::Contract => 'documents/contract',
            DocumentType::CashAcceptanceAct => 'documents/cash-act',
            DocumentType::WorkCompletionAct => 'documents/works-act',
            default => 'documents/preview',
        };

        return Inertia::render($component, [
            'documentType' => ['value' => $type->value, 'label' => $type->label()],
            'project' => ['id' => $project->id, 'name' => $project->name],
            'payload' => $payload,
        ]);
    }

    /**
     * Принять и сохранить: сгенерировать файл и создать документ.
     */
    public function store(StoreDocumentRequest $request): RedirectResponse
    {
        $type = DocumentType::from($request->validated('type'));
        $project = Project::findOrFail($request->validated('project_id'));

        $this->generator->generate($project, $type, $request->validated('payload'), $request->user());

        return to_route('documents.index')->with('success', 'Документ создан.');
    }

    /**
     * Скачать файл документа.
     */
    public function download(Document $document): StreamedResponse
    {
        return $document->storage()->download($document->path, $document->fileName());
    }

    /**
     * Удалить документ вместе с файлом.
     */
    public function destroy(Document $document): RedirectResponse
    {
        $document->storage()->delete($document->path);
        $document->delete();

        return to_route('documents.index')->with('success', 'Документ удалён.');
    }
}
