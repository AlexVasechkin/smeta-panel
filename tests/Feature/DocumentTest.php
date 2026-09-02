<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Models\City;
use App\Models\Client;
use App\Models\Document;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    use RefreshDatabase;

    private const TEMPLATE = 'templates/docs/smeta-sample.xlsx';

    private const TEMPLATE_FINAL = 'templates/docs/smeta-final.xlsx';

    private const TEMPLATE_CONTRACT = 'templates/docs/order-sample.docx';

    private const TEMPLATE_CASH_ACT = 'templates/docs/money-act.docx';

    private const TEMPLATE_WORKS_ACT = 'templates/docs/works-act.docx';

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());

        // Реальные шаблоны помещаем на фейковый диск, чтобы генератор их нашёл.
        Storage::fake('local');
        foreach ([self::TEMPLATE, self::TEMPLATE_FINAL, self::TEMPLATE_CONTRACT, self::TEMPLATE_CASH_ACT, self::TEMPLATE_WORKS_ACT] as $tpl) {
            Storage::disk('local')->put($tpl, file_get_contents(base_path('storage/app/private/'.$tpl)));
        }
    }

    /**
     * Аутентифицировать пользователя-владельца организации (Подрядчик).
     */
    private function actingUserWithOrganization(): Organization
    {
        $user = User::factory()->create();
        $organization = Organization::create([
            'name' => 'ООО Строй',
            'director_surname' => 'Погосян',
            'director_name' => 'Сергей',
            'director_father_name' => 'Левонович',
        ]);
        $organization->members()->create(['user_id' => $user->id, 'is_owner' => true]);
        $this->actingAs($user);

        return $organization;
    }

    private function docxText(string $binary): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'dx').'.docx';
        file_put_contents($tmp, $binary);
        $zip = new \ZipArchive;
        $zip->open($tmp);
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();
        @unlink($tmp);

        return html_entity_decode(preg_replace('/<[^>]+>/', '', $xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function estimatePayload(array $overrides = []): array
    {
        return array_merge([
            'header' => [
                'date' => '«10» августа 2026',
                'title' => 'Смета на работы.',
                'object' => '50 кв.м',
                'customer' => 'Иванов И. И.',
                'address' => 'г. Москва',
                'contractor_signatory' => 'Петров П.',
                'customer_signatory' => 'Иванов И.',
            ],
            'work_groups' => [
                ['title' => 'Работы', 'items' => [
                    ['name' => 'Работа 1', 'unit' => 'шт', 'quantity' => 2, 'price' => 1000],
                ]],
            ],
            'materials' => [
                ['name' => 'Материал 1', 'unit' => 'шт', 'quantity' => 3, 'price' => 500],
            ],
            'extras' => [
                ['label' => 'Транспорт', 'amount' => 10000],
            ],
            'discount_percent' => 10,
        ], $overrides);
    }

    private function projectWithEstimate(): Project
    {
        $client = Client::factory()->create([
            'surname' => 'Смирнов',
            'name' => 'Пётр',
            'father_name' => 'Иванович',
        ]);
        $project = Project::factory()->create(['client_id' => $client->id]);

        $this->put(route('projects.estimate.update', $project), [
            'works' => [
                ['category' => 'Кухня', 'name' => 'Укладка плитки', 'unit' => 'м²', 'quantity' => 10, 'price' => 500],
            ],
            'materials' => [
                ['name' => 'Плитка', 'unit' => 'м²', 'quantity' => 12, 'price' => 800],
            ],
        ]);

        return $project;
    }

    public function test_index_renders(): void
    {
        $this->get(route('documents.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('documents/index'));
    }

    public function test_index_groups_documents_by_project_and_type(): void
    {
        $project = Project::factory()->create();

        $make = function (DocumentType $type) use ($project) {
            Document::create([
                'project_id' => $project->id,
                'type' => $type->value,
                'title' => 'x',
                'disk' => 'local',
                'path' => 'documents/'.$project->id.'/'.\Illuminate\Support\Str::uuid().'.docx',
                'payload' => [],
            ]);
        };

        $make(DocumentType::PreliminaryEstimate);
        $make(DocumentType::WorkCompletionAct);
        $make(DocumentType::WorkCompletionAct);

        $this->get(route('documents.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('documents/index')
                ->has('projects', 1)
                ->where('projects.0.id', $project->id)
                // Внутри объекта — группы по типу; акт содержит 2 документа с номерами.
                ->has('projects.0.types', 2)
                ->where('projects.0.types.1.type_label', DocumentType::WorkCompletionAct->label())
                ->has('projects.0.types.1.documents', 2)
                ->where('projects.0.types.1.documents.0.name', DocumentType::WorkCompletionAct->label().' 1')
                ->where('projects.0.types.1.documents.1.name', DocumentType::WorkCompletionAct->label().' 2'));
    }

    public function test_preview_prefills_from_project(): void
    {
        $project = $this->projectWithEstimate();

        $this->get(route('documents.preview', ['type' => DocumentType::PreliminaryEstimate->value, 'project' => $project->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('documents/preview')
                ->where('payload.header.customer', 'Смирнов П. И.')
                ->where('payload.header.address', $project->address)
                ->has('payload.work_groups', 1)
                ->where('payload.work_groups.0.title', 'Кухня')
                ->has('payload.materials', 1));
    }

    public function test_preview_prefills_address_with_city(): void
    {
        $city = City::create(['name' => 'Казань']);
        $project = Project::factory()->create(['city_id' => $city->id, 'address' => 'ул. Баумана, 1']);

        $this->get(route('documents.preview', ['type' => DocumentType::PreliminaryEstimate->value, 'project' => $project->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('payload.header.address', 'г. Казань, ул. Баумана, 1'));
    }

    public function test_estimate_signatories_use_short_name_format(): void
    {
        $this->actingUserWithOrganization();
        $client = Client::factory()->create([
            'surname' => 'Смирнов',
            'name' => 'Пётр',
            'father_name' => 'Иванович',
        ]);
        $project = Project::factory()->create(['client_id' => $client->id]);

        $this->get(route('documents.preview', ['type' => DocumentType::PreliminaryEstimate->value, 'project' => $project->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('payload.header.contractor_signatory', 'Погосян С. Л.')
                ->where('payload.header.customer_signatory', 'Смирнов П. И.'));
    }

    public function test_store_generates_and_saves_document(): void
    {
        $project = $this->projectWithEstimate();

        $payload = [
            'header' => [
                'date' => '«10» августа 2026',
                'title' => 'Смета на работы.',
                'object' => '50 кв.м',
                'customer' => 'Иванов И. И.',
                'address' => 'г. Москва',
                'contractor_signatory' => 'Петров П.',
                'customer_signatory' => 'Иванов И.',
            ],
            'work_groups' => [
                ['title' => 'Работы', 'items' => [
                    ['name' => 'Работа 1', 'unit' => 'шт', 'quantity' => 2, 'price' => 1000],
                ]],
            ],
            'materials' => [
                ['name' => 'Материал 1', 'unit' => 'шт', 'quantity' => 3, 'price' => 500],
            ],
            'extras' => [
                ['label' => 'Транспорт', 'amount' => 10000],
            ],
            'discount_percent' => 10,
        ];

        $response = $this->post(route('documents.store'), [
            'type' => DocumentType::PreliminaryEstimate->value,
            'project_id' => $project->id,
            'payload' => $payload,
        ]);

        $response->assertRedirect(route('documents.index'));

        $document = Document::firstOrFail();
        $this->assertSame($project->id, $document->project_id);
        $this->assertSame(DocumentType::PreliminaryEstimate, $document->type);
        Storage::disk('local')->assertExists($document->path);

        // Проверяем содержимое сгенерированного файла.
        $tmp = tempnam(sys_get_temp_dir(), 'chk').'.xlsx';
        file_put_contents($tmp, Storage::disk('local')->get($document->path));
        $sheet = IOFactory::load($tmp)->getActiveSheet();

        $this->assertStringContainsString('Иванов И. И.', (string) $sheet->getCell('A4')->getValue());
        $this->assertStringContainsString('г. Москва', (string) $sheet->getCell('A5')->getValue());

        // Всего = работы*(1-0.10) + материалы + доп. = 2000*0.9 + 1500 + 10000 = 13300
        $grand = $this->findValueByLabel($sheet, 'Всего:');
        $this->assertEqualsWithDelta(13300, $grand, 0.01);

        @unlink($tmp);
    }

    public function test_estimate_preview_is_based_on_the_preliminary_estimate(): void
    {
        $project = $this->projectWithEstimate();

        // Сохраняем предварительную смету с узнаваемыми данными.
        $this->post(route('documents.store'), [
            'type' => DocumentType::PreliminaryEstimate->value,
            'project_id' => $project->id,
            'payload' => $this->estimatePayload([
                'header' => [
                    'date' => 'd', 'title' => 't', 'object' => 'o',
                    'customer' => 'Предв. Заказчик', 'address' => 'a',
                    'contractor_signatory' => 's', 'customer_signatory' => 's',
                ],
                'work_groups' => [
                    ['title' => 'Демонтаж', 'items' => [
                        ['name' => 'Снос стены', 'unit' => 'м²', 'quantity' => 5, 'price' => 300],
                    ]],
                ],
            ]),
        ]);

        // Предпросмотр сметы подставляет данные из предварительной сметы.
        $this->get(route('documents.preview', ['type' => DocumentType::Estimate->value, 'project' => $project->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('documents/preview')
                ->where('documentType.value', DocumentType::Estimate->value)
                ->where('payload.header.customer', 'Предв. Заказчик')
                ->has('payload.work_groups', 1)
                ->where('payload.work_groups.0.title', 'Демонтаж'));
    }

    public function test_create_flags_projects_that_have_a_preliminary_estimate(): void
    {
        $withPreliminary = $this->projectWithEstimate();
        $this->post(route('documents.store'), [
            'type' => DocumentType::PreliminaryEstimate->value,
            'project_id' => $withPreliminary->id,
            'payload' => $this->estimatePayload(),
        ]);

        $withoutPreliminary = Project::factory()->create();

        $this->get(route('documents.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('documents/create')
                ->where('projects', fn ($projects) => $projects->firstWhere('value', (string) $withPreliminary->id)['has_preliminary'] === true
                    && $projects->firstWhere('value', (string) $withoutPreliminary->id)['has_preliminary'] === false));
    }

    public function test_estimate_store_generates_and_saves_document(): void
    {
        $project = $this->projectWithEstimate();

        $this->post(route('documents.store'), [
            'type' => DocumentType::Estimate->value,
            'project_id' => $project->id,
            'payload' => $this->estimatePayload(),
        ])->assertRedirect(route('documents.index'));

        $document = Document::firstOrFail();
        $this->assertSame(DocumentType::Estimate, $document->type);
        Storage::disk('local')->assertExists($document->path);

        $tmp = tempnam(sys_get_temp_dir(), 'chk').'.xlsx';
        file_put_contents($tmp, Storage::disk('local')->get($document->path));
        $sheet = IOFactory::load($tmp)->getActiveSheet();

        // Шапка шаблона сметы: ЗАКАЗЧИК — A5, АДРЕС — A7.
        $this->assertStringContainsString('Иванов И. И.', (string) $sheet->getCell('A5')->getValue());
        $this->assertStringContainsString('г. Москва', (string) $sheet->getCell('A7')->getValue());

        // Всего = 2000*0.9 + 1500 + 10000 = 13300
        $this->assertEqualsWithDelta(13300, $this->findValueByLabel($sheet, 'Всего:'), 0.01);

        @unlink($tmp);
    }

    public function test_contract_preview_is_based_on_the_estimate(): void
    {
        $organization = $this->actingUserWithOrganization();
        $project = $this->projectWithEstimate();

        // Смета объекта — источник сумм для договора.
        $this->post(route('documents.store'), [
            'type' => DocumentType::Estimate->value,
            'project_id' => $project->id,
            'payload' => $this->estimatePayload(),
        ]);

        $this->get(route('documents.preview', ['type' => DocumentType::Contract->value, 'project' => $project->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('documents/contract')
                ->where('documentType.value', DocumentType::Contract->value)
                // Подрядчик — из организации пользователя.
                ->where('payload.contractor_surname', 'Погосян')
                // Заказчик — из клиента объекта.
                ->where('payload.customer_name', $project->client->name)
                // Суммы — из сметы (works=2000, grand=13300, extras=10000).
                ->where('payload.works_cost', fn ($v) => abs((float) $v - 2000) < 0.01)
                ->where('payload.total_cost', fn ($v) => abs((float) $v - 13300) < 0.01)
                ->where('payload.extras_cost', fn ($v) => abs((float) $v - 10000) < 0.01));
    }

    public function test_contract_store_generates_and_saves_docx(): void
    {
        $project = $this->projectWithEstimate();

        $payload = [
            'number' => '2026-0007', 'city' => 'Москва', 'day' => '17', 'month' => 'августа', 'year' => '2026',
            'contractor_surname' => 'Погосян', 'contractor_name' => 'Сергей', 'contractor_father_name' => 'Левонович',
            'contractor_passport_type' => 'Гражданина РФ', 'contractor_passport_series' => '4509', 'contractor_passport_number' => '123456',
            'contractor_passport_issued_by' => 'ОУФМС', 'contractor_passport_issued_at' => '20.06.2015', 'contractor_passport_registration_address' => 'г. Москва',
            'org_bank_name' => 'Сбербанк', 'org_card_number' => '1234 5678', 'org_phone' => '+7 495 111-22-33', 'org_email' => 'org@stroy.ru',
            'customer_surname' => 'Иванов', 'customer_name' => 'Иван', 'customer_father_name' => 'Иванович',
            'customer_passport_type' => 'Гражданина РФ', 'customer_passport_series' => '1234', 'customer_passport_number' => '654321',
            'customer_passport_issued_by' => 'ОВД', 'customer_passport_issued_at' => '01.02.2010', 'customer_passport_registration_address' => 'г. Москва',
            'customer_phone' => '+7 916 555-44-33', 'customer_email' => 'ivanov@mail.ru',
            'area' => '50', 'address' => 'ул. Ленина, 5',
            'works_cost' => 200000, 'materials_cost' => 150000, 'total_cost' => 340000, 'extras_cost' => 90000,
        ];

        $this->post(route('documents.store'), [
            'type' => DocumentType::Contract->value,
            'project_id' => $project->id,
            'payload' => $payload,
        ])->assertRedirect(route('documents.index'));

        $document = Document::firstOrFail();
        $this->assertSame(DocumentType::Contract, $document->type);
        $this->assertStringEndsWith('.docx', $document->path);
        Storage::disk('local')->assertExists($document->path);

        $text = $this->docxText(Storage::disk('local')->get($document->path));

        $this->assertStringNotContainsString('{{', $text);
        $this->assertStringContainsString('Иванов Иван Иванович', $text);
        $this->assertStringContainsString('2026-0007', $text);
        // total_cost прописью и число.
        $this->assertStringContainsString('340 000,00', $text);
        $this->assertStringContainsString('Триста сорок тысяч', $text);
        // Раздел 16 «Реквизиты и подписи сторон».
        $this->assertStringContainsString('Сбербанк', $text);
        $this->assertStringContainsString('org@stroy.ru', $text);
        $this->assertStringContainsString('+7 916 555-44-33', $text);
    }

    public function test_cash_act_preview_prefills_from_project(): void
    {
        $this->actingUserWithOrganization();
        $project = $this->projectWithEstimate();

        // Смета объекта — источник суммы по умолчанию.
        $this->post(route('documents.store'), [
            'type' => DocumentType::Estimate->value,
            'project_id' => $project->id,
            'payload' => $this->estimatePayload(),
        ]);

        $this->get(route('documents.preview', ['type' => DocumentType::CashAcceptanceAct->value, 'project' => $project->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('documents/cash-act')
                ->where('documentType.value', DocumentType::CashAcceptanceAct->value)
                // Подрядчик (принимает) — из организации пользователя.
                ->where('payload.contractor_surname', 'Погосян')
                // Заказчик (передаёт) — из клиента объекта.
                ->where('payload.customer_name', $project->client->name)
                ->where('payload.order_number', $project->order_number)
                // Сумма — итог сметы (works=2000*0.9 + materials=1500 + extras=10000 = 13300).
                ->where('payload.cost', fn ($v) => abs((float) $v - 13300) < 0.01));
    }

    public function test_cash_act_store_generates_and_saves_docx(): void
    {
        $project = $this->projectWithEstimate();

        $payload = [
            'number' => '2026-0007', 'city' => 'Москва', 'day' => '17', 'month' => 'августа', 'year' => '2026',
            'contractor_surname' => 'Погосян', 'contractor_name' => 'Сергей', 'contractor_father_name' => 'Левонович',
            'contractor_passport_type' => 'Гражданина РФ', 'contractor_passport_series' => '4509', 'contractor_passport_number' => '123456',
            'contractor_passport_issued_by' => 'ОУФМС', 'contractor_passport_issued_at' => '20.06.2015', 'contractor_passport_registration_address' => 'г. Москва',
            'org_bank_name' => 'Сбербанк', 'org_card_number' => '1234 5678', 'org_phone' => '+7 495 111-22-33', 'org_email' => 'org@stroy.ru',
            'customer_surname' => 'Иванов', 'customer_name' => 'Иван', 'customer_father_name' => 'Иванович',
            'customer_passport_type' => 'Гражданина РФ', 'customer_passport_series' => '1234', 'customer_passport_number' => '654321',
            'customer_passport_issued_by' => 'ОВД', 'customer_passport_issued_at' => '01.02.2010', 'customer_passport_registration_address' => 'г. Москва',
            'customer_phone' => '+7 916 555-44-33', 'customer_email' => 'ivanov@mail.ru',
            'order_number' => '2026-0007', 'order_created_at' => '01.08.2026',
            'cost' => 340000, 'purpose' => 'Оплата за ремонтно-отделочные работы',
        ];

        $this->post(route('documents.store'), [
            'type' => DocumentType::CashAcceptanceAct->value,
            'project_id' => $project->id,
            'payload' => $payload,
        ])->assertRedirect(route('documents.index'));

        $document = Document::firstOrFail();
        $this->assertSame(DocumentType::CashAcceptanceAct, $document->type);
        $this->assertStringEndsWith('.docx', $document->path);
        Storage::disk('local')->assertExists($document->path);

        $text = $this->docxText(Storage::disk('local')->get($document->path));

        $this->assertStringNotContainsString('{{', $text);
        $this->assertStringContainsString('Иванов Иван Иванович', $text);
        // Сумма прописью и числом.
        $this->assertStringContainsString('340 000,00', $text);
        $this->assertStringContainsString('Триста сорок тысяч', $text);
        // Назначение платежа и реквизиты.
        $this->assertStringContainsString('Оплата за ремонтно-отделочные работы', $text);
        $this->assertStringContainsString('Сбербанк', $text);
    }

    /**
     * Сохранить смету объекта (документ-основание для акта выполненных работ).
     *
     * @param  array<int, array<string, mixed>>  $workGroups
     */
    private function saveEstimateDocument(Project $project, array $workGroups): void
    {
        $this->post(route('documents.store'), [
            'type' => DocumentType::Estimate->value,
            'project_id' => $project->id,
            'payload' => $this->estimatePayload(['work_groups' => $workGroups, 'materials' => [], 'extras' => [], 'discount_percent' => 0]),
        ]);
    }

    public function test_works_act_preview_lists_estimate_positions_with_remaining(): void
    {
        $this->actingUserWithOrganization();
        $project = $this->projectWithEstimate();

        $this->saveEstimateDocument($project, [
            ['title' => 'Стены', 'items' => [
                ['name' => 'Грунтовка стен', 'unit' => 'м²', 'quantity' => 20, 'price' => 80],
                ['name' => 'Шпаклевка стен', 'unit' => 'м²', 'quantity' => 20, 'price' => 300],
            ]],
        ]);

        $this->get(route('documents.preview', ['type' => DocumentType::WorkCompletionAct->value, 'project' => $project->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('documents/works-act')
                ->where('documentType.value', DocumentType::WorkCompletionAct->value)
                ->where('payload.advance_percent', fn ($v) => (float) $v === 20.0)
                ->has('payload.available_positions', 2)
                ->where('payload.available_positions.0.key', 'g0-i0')
                ->where('payload.available_positions.0.name', 'Грунтовка стен')
                ->where('payload.available_positions.0.remaining', fn ($v) => (float) $v === 20.0)
                ->where('payload.available_positions.1.key', 'g0-i1'));
    }

    public function test_works_act_excludes_positions_closed_in_other_acts(): void
    {
        $this->actingUserWithOrganization();
        $project = $this->projectWithEstimate();

        $this->saveEstimateDocument($project, [
            ['title' => 'Стены', 'items' => [
                ['name' => 'Грунтовка стен', 'unit' => 'м²', 'quantity' => 20, 'price' => 80],
                ['name' => 'Шпаклевка стен', 'unit' => 'м²', 'quantity' => 20, 'price' => 300],
            ]],
        ]);

        // Первый акт: полностью закрываем позицию g0-i0 и частично g0-i1.
        $this->post(route('documents.store'), [
            'type' => DocumentType::WorkCompletionAct->value,
            'project_id' => $project->id,
            'payload' => [
                'advance_percent' => 20,
                'positions' => [
                    ['key' => 'g0-i0', 'group' => 'Стены', 'name' => 'Грунтовка стен', 'unit' => 'м²', 'price' => 80, 'quantity' => 20],
                    ['key' => 'g0-i1', 'group' => 'Стены', 'name' => 'Шпаклевка стен', 'unit' => 'м²', 'price' => 300, 'quantity' => 8],
                ],
            ],
        ])->assertRedirect(route('documents.index'));

        // Второй акт: g0-i0 закрыта полностью (исключена), у g0-i1 остаток 12.
        $this->get(route('documents.preview', ['type' => DocumentType::WorkCompletionAct->value, 'project' => $project->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('payload.available_positions', 1)
                ->where('payload.available_positions.0.key', 'g0-i1')
                ->where('payload.available_positions.0.remaining', fn ($v) => (float) $v === 12.0));
    }

    public function test_works_act_store_generates_and_saves_docx(): void
    {
        $project = $this->projectWithEstimate();

        $payload = [
            'number' => '2026-0007', 'city' => 'Москва', 'day' => '20', 'month' => 'августа', 'year' => '2026',
            'contractor_surname' => 'Погосян', 'contractor_name' => 'Сергей', 'contractor_father_name' => 'Левонович',
            'contractor_passport_type' => 'Гражданина РФ', 'contractor_passport_series' => '4509', 'contractor_passport_number' => '123456',
            'contractor_passport_issued_by' => 'ОУФМС', 'contractor_passport_issued_at' => '20.06.2015', 'contractor_passport_registration_address' => 'г. Москва',
            'org_bank_name' => 'Сбербанк', 'org_card_number' => '1234 5678', 'org_phone' => '+7 495 111-22-33', 'org_email' => 'org@stroy.ru',
            'customer_surname' => 'Иванов', 'customer_name' => 'Иван', 'customer_father_name' => 'Иванович',
            'customer_passport_type' => 'Гражданина РФ', 'customer_passport_series' => '1234', 'customer_passport_number' => '654321',
            'customer_passport_issued_by' => 'ОВД', 'customer_passport_issued_at' => '01.02.2010', 'customer_passport_registration_address' => 'г. Москва',
            'customer_phone' => '+7 916 555-44-33', 'customer_email' => 'ivanov@mail.ru',
            'order_number' => '2026-0007', 'order_created_at' => '01.08.2026', 'square' => '50', 'address' => 'ул. Ленина, 5',
            'advance_percent' => 20,
            'positions' => [
                ['key' => 'g0-i0', 'group' => 'Стены', 'name' => 'Грунтовка стен', 'unit' => 'м²', 'price' => 80, 'quantity' => 20],
                ['key' => 'g0-i2', 'group' => 'Стены', 'name' => 'Шпаклевка стен', 'unit' => 'м²', 'price' => 300, 'quantity' => 8],
            ],
        ];

        $this->post(route('documents.store'), [
            'type' => DocumentType::WorkCompletionAct->value,
            'project_id' => $project->id,
            'payload' => $payload,
        ])->assertRedirect(route('documents.index'));

        $document = Document::firstOrFail();
        $this->assertSame(DocumentType::WorkCompletionAct, $document->type);
        $this->assertStringEndsWith('.docx', $document->path);
        Storage::disk('local')->assertExists($document->path);

        $text = $this->docxText(Storage::disk('local')->get($document->path));

        $this->assertStringNotContainsString('{{', $text);
        // Позиции попали в ведомость работ; номера = порядковым номерам в смете
        // (ключи g0-i0 и g0-i2 → номера 1 и 3, а не последовательные 1 и 2).
        $this->assertStringContainsString('1Грунтовка стен', $text);
        $this->assertStringContainsString('3Шпаклевка стен', $text);
        $this->assertStringContainsString('Наименование работ', $text);
        // Работы = 20*80 + 8*300 = 4000; за вычетом аванса 20% => 3200:
        // сумма цифрами и прописью в скобках.
        $this->assertStringContainsString('3 200 рублей 00 копеек.', $text);
        $this->assertStringContainsString('(Три тысячи двести рублей 00 копеек)', $text);
        // Стороны.
        $this->assertStringContainsString('Иванов Иван Иванович', $text);
        // Заключительные фразы после ведомости работ.
        $this->assertStringContainsString('Стороны претензий друг к другу не имеют', $text);
        $this->assertStringContainsString('составлен в двух экземплярах', $text);
    }

    public function test_project_card_lists_its_documents(): void
    {
        $project = $this->projectWithEstimate();

        $this->post(route('documents.store'), [
            'type' => DocumentType::PreliminaryEstimate->value,
            'project_id' => $project->id,
            'payload' => [
                'header' => ['date' => 'd', 'title' => 't', 'object' => 'o', 'customer' => 'c', 'address' => 'a', 'contractor_signatory' => 's', 'customer_signatory' => 's'],
                'work_groups' => [], 'materials' => [], 'extras' => [], 'discount_percent' => 0,
            ],
        ]);

        $this->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('projects/show')
                ->has('project.documents', 1)
                ->where('project.documents.0.type_label', DocumentType::PreliminaryEstimate->label()));
    }

    public function test_store_rejects_unimplemented_type(): void
    {
        $project = Project::factory()->create();

        $this->from(route('documents.create'))
            ->post(route('documents.store'), [
                'type' => DocumentType::KeysHandoverAct->value,
                'project_id' => $project->id,
                'payload' => ['header' => [], 'work_groups' => [], 'materials' => [], 'extras' => []],
            ])
            ->assertSessionHasErrors('type');

        $this->assertDatabaseCount('documents', 0);
    }

    public function test_download_and_destroy(): void
    {
        $project = $this->projectWithEstimate();

        $this->post(route('documents.store'), [
            'type' => DocumentType::PreliminaryEstimate->value,
            'project_id' => $project->id,
            'payload' => [
                'header' => ['date' => 'd', 'title' => 't', 'object' => 'o', 'customer' => 'c', 'address' => 'a', 'contractor_signatory' => 's', 'customer_signatory' => 's'],
                'work_groups' => [], 'materials' => [], 'extras' => [], 'discount_percent' => 0,
            ],
        ]);

        $document = Document::firstOrFail();

        $this->get(route('documents.download', $document))->assertOk();

        $this->delete(route('documents.destroy', $document))->assertRedirect(route('documents.index'));
        Storage::disk('local')->assertMissing($document->path);
        $this->assertDatabaseCount('documents', 0);
    }

    private function findValueByLabel(Worksheet $sheet, string $label): ?float
    {
        for ($row = 1; $row <= $sheet->getHighestRow(); $row++) {
            if (trim((string) $sheet->getCell("A{$row}")->getValue()) === $label) {
                return (float) $sheet->getCell("K{$row}")->getValue();
            }
        }

        return null;
    }
}
