# План: раздел «Документы» и генерация документов

Артефакт для новых сессий. Описывает задачу, архитектуру и текущее состояние.
Связанный общий роадмап проекта — в `plan.md` (Этап 5 «Документы и файлы»).

---

## Задача

Глобальный раздел **«Документы»** с генерацией документов по шаблонам (xlsx).
Типы документов (пользователь добавляет шаблоны поэтапно):

1. Предварительная смета  ✅ реализовано
2. Договор  ✅ реализовано (на основании сметы, шаблон .docx)
3. Смета  ✅ реализовано (на основании предварительной сметы)
4. Акт приёма-передачи денежных средств  ✅ реализовано (на основании сметы, шаблон .docx)
5. Акт выполненных работ  ✅ реализовано (на основании сметы, шаблон .docx; закрытие позиций по актам)
6. Акт приёма-передачи ключей

**Двухэтапное создание любого документа:**
1. Пользователь выбирает тип документа и объект → попадает на **предпросмотр**, где
   редактирует основные данные (шапка + позиции).
2. **«Принять и сохранить»** → генерируется и сохраняется файл документа.

**Хранилище:** только через фасад `Storage`, диск по умолчанию. Сейчас `local`,
далее переключение на S3 **без изменения кода** (`FILESYSTEM_DISK=s3` + AWS-креды).

**Данные:** берутся из выбранного объекта (Project → client/address/area/type) и его
сметы (работы/материалы). Формат готового файла — XLSX.

---

## Архитектура (как устроено)

- Диск по умолчанию: `config('filesystems.default')`. Файлы читаются/пишутся через
  `Storage::disk(...)`. PhpSpreadsheet требует реальный путь → шаблон копируется во
  временный файл, результат пишется обратно через `Storage::put`. Диск-агностично.
- У каждого `Document` хранится `disk`, на котором лежит файл → старые документы
  остаются доступны после переключения диска по умолчанию.
- Шаблон предварительной сметы: `storage/app/private/templates/docs/smeta-sample.xlsx`
  (на диске `local` путь относительно корня: `templates/docs/smeta-sample.xlsx`).
- Тело сметы (работы по разделам с подытогами, материалы, блок итогов со скидкой и
  доп. статьями, подписанты) перестраивается **динамически** по данным payload;
  суммы считаются в PHP и пишутся значениями. Шапка/стили берутся из шаблона.
- Позиции по умолчанию: из сметы объекта; если смета пуста — парсятся из образца-шаблона.

---

## Сделано ✅

- [x] Установлен `phpoffice/phpspreadsheet`
- [x] Enum `App\Enums\DocumentType` (6 типов, `isImplemented()`, `templatePath()`)
- [x] Модель `App\Models\Document` + миграция `documents`
      (`project_id`, `created_by`, `type`, `title`, `disk`, `path`, `payload` json)
- [x] `App\Support\Documents\PreliminaryEstimateData` — сбор payload из объекта/сметы,
      парсинг образца-шаблона как fallback, формат даты и описания объекта
- [x] `App\Support\Documents\PreliminaryEstimateWriter` — генерация xlsx из шаблона
- [x] `App\Services\DocumentGenerator` — оркестрация (Storage + запись Document),
      диспетчеризация по типу; `defaultPayload()` для предпросмотра, `generate()` для сохранения
- [x] `App\Http\Controllers\DocumentController` — index / create / preview / store / download / destroy
- [x] `App\Http\Requests\StoreDocumentRequest` — валидация payload (в т.ч. `Rule::in` по реализованным типам)
- [x] Маршруты в `routes/web.php` (группа `auth`)
- [x] Пункт «Документы» в сайдбаре (`resources/js/components/app-sidebar.tsx`)
- [x] Фронтенд: `resources/js/pages/documents/{index,create,preview}.tsx`
      (предпросмотр — редактирование шапки, разделов работ/материалов с add/remove,
      доп. статей и скидки, живые итоги)
- [x] Feature-тесты `tests/Feature/DocumentTest.php` (5 тестов, в т.ч. проверка
      содержимого сгенерированного xlsx: шапка + «Всего»)
- [x] Вся связка проверена: полный набор тестов зелёный, сборка фронтенда и ESLint чистые

---

## Смета (на основании предварительной) ✅

- [x] Шаблон `storage/app/private/templates/docs/smeta-final.xlsx`
- [x] Общая логика заполнения xlsx вынесена в `AbstractEstimateWriter`; предварительная
      и финальная сметы — наследники (задают строки тела, ячейки стилей и раскладку шапки)
- [x] `FinalEstimateData::forProject` — payload берётся из последней сохранённой
      предварительной сметы объекта; если её нет — сбор из объекта (как предварительная)
- [x] `DocumentType::Estimate` включён (`isImplemented`, `templatePath`)
- [x] Диспетчеризация в `DocumentGenerator` (defaultPayload + generate)
- [x] Переиспользуются те же create/preview формы и валидация payload
- [x] Feature-тесты: предпросмотр берёт данные из предварительной; генерация xlsx (шапка + «Всего»)

## Договор (на основании сметы) ✅

- [x] Шаблон `storage/app/private/templates/docs/order-sample.docx` с плейсхолдерами `{{ ... }}`
- [x] `DocumentType::Contract` включён; добавлен `fileExtension()` (docx/xlsx),
      генератор пишет файл с нужным расширением
- [x] `ContractData::forProject` — сбор плоского payload: реквизиты организации
      (ФИО директора + паспорт из EAV), клиента (ФИО + паспорт из EAV), объект,
      дата, суммы из сметы объекта (documents type=estimate → `computeTotals`)
- [x] `ContractWriter` — подстановка в `word/document.xml`: слияние разорванных на
      runs плейсхолдеров, нормализация токена (кавычки/пробелы), карта токен→значение
- [x] `RublesInWords` — сумма прописью; в договоре используется целая часть словами
      (`::human`/«текстом»), т.к. шаблон сам дописывает «рублей 00 копеек»
- [x] Валидация договора в `StoreDocumentRequest` (ветка для плоского payload)
- [x] Отдельная страница предпросмотра-редактирования `documents/contract.tsx`
      (поля сгруппированы: Договор / Подрядчик / Заказчик / Объект / Суммы)
- [x] В форме создания тип «Договор» доступен только для объектов со сметой (`has_estimate`)
- [x] Feature-тесты: предпросмотр берёт суммы из сметы; генерация .docx (без остатков `{{`)

## Акт приёма-передачи денежных средств (на основании сметы) ✅

- [x] Шаблон `storage/app/private/templates/docs/money-act.docx` с плейсхолдерами `{{ ... }}`
- [x] `DocumentType::CashAcceptanceAct` включён (`isImplemented`, `templatePath`, `fileExtension`=docx)
- [x] Общая docx-механика (слияние runs, нормализация токена, замена) вынесена в
      `AbstractDocxWriter`; `ContractWriter` и `CashAcceptanceActWriter` — наследники (только `map()`)
- [x] `CashAcceptanceActData::forProject` — плоский payload: заказчик (клиент, передаёт средства,
      ФИО + паспорт из EAV), подрядчик (организация, принимает, ФИО директора + паспорт),
      номер/дата заказа, сумма по умолчанию из сметы объекта, назначение платежа
- [x] Валидация в `StoreDocumentRequest` (ветка плоского payload — общая для договора и актов)
- [x] Отдельная страница предпросмотра `documents/cash-act.tsx` (Акт / Подрядчик / Заказчик / Платёж)
- [x] Диспетчеризация в `DocumentGenerator` и выбор компонента в `DocumentController::preview`
- [x] Feature-тесты: предпросмотр берёт сумму из сметы; генерация .docx (без остатков `{{`,
      ФИО клиента, сумма числом и прописью, назначение платежа, реквизиты)

## Акт выполненных работ (на основании сметы) ✅

- [x] Шаблон `storage/app/private/templates/docs/works-act.docx` с плейсхолдерами `{{ ... }}`
      и маркером таблицы `{{ document.positions }}`
- [x] `DocumentType::WorkCompletionAct` включён (`isImplemented`, `templatePath`, `fileExtension`=docx)
- [x] Поддержка «сырых» OOXML-блоков в `AbstractDocxWriter` (`rawBlocks()`): абзац с
      `{{ document.positions }}` целиком заменяется сгенерированной таблицей (разрыв страницы сохраняется)
- [x] `WorkCompletionActData::forProject` — реквизиты сторон (как в акте денег) + `available_positions`:
      позиции последней Сметы объекта с остатком (кол-во в смете − суммарно закрытый объём в других актах);
      позиция идентифицируется по месту в смете (`g{раздел}-i{строка}`); `advance_percent` по умолчанию 20
- [x] `WorkCompletionActWriter` — карта скалярных плейсхолдеров + таблица «Ведомость выполненных работ»
      (группировка по разделам, подытоги, «Всего»); сумма `document.cost` = работы по акту × (1 − аванс/100), прописью
- [x] Валидация в `StoreDocumentRequest` (ветка: плоские поля + `positions[]` + `advance_percent`)
- [x] Отдельная страница предпросмотра `documents/works-act.tsx`: отметка позиций (чекбокс), ввод объёма
      в акте (по умолчанию = остаток), поле процента аванса, живые суммы работ и «к оплате»
- [x] В форме создания тип доступен только для объектов со сметой (`has_estimate`)
- [x] Feature-тесты: предпросмотр с остатком; исключение закрытых в других актах позиций; генерация .docx

## Осталось / на будущее

- [ ] Шаблон и генератор для акта приёма-передачи ключей
- [ ] (Опционально) строгое соответствие исходной вёрстке образца при произвольных данных
- [ ] (Опционально) генерация PDF-версии документа
- [ ] (Опционально) документы на карточке объекта (сейчас — только глобальный список)
- [ ] При переходе на S3: залить шаблоны (`templates/docs/…`) на s3-диск

---

## Как добавить новый тип документа (playbook)

1. Положить шаблон в `storage/app/private/templates/docs/<name>.xlsx`.
2. В `DocumentType`: включить `isImplemented()` для типа и вернуть путь в `templatePath()`.
3. Написать свой `App\Support\Documents\<Type>Data` (сбор payload из объекта) и
   `<Type>Writer` (заполнение шаблона).
4. Подключить их в `App\Services\DocumentGenerator` (`defaultPayload()` и `generate()`).
5. При необходимости — своя форма предпросмотра (сейчас `documents/preview.tsx`
   рассчитана на структуру предварительной сметы; для других типов может понадобиться
   отдельная страница/вариативность по `documentType.value`).
6. Добавить/расширить feature-тесты в духе `DocumentTest`.

Каркас общий: 2 этапа (create → preview → store), хранилище, список, скачивание/удаление
уже переиспользуются.

---

## Ключевые файлы

```
app/Enums/DocumentType.php
app/Models/Document.php
app/Http/Controllers/DocumentController.php
app/Http/Requests/StoreDocumentRequest.php
app/Services/DocumentGenerator.php
app/Support/Documents/PreliminaryEstimateData.php
app/Support/Documents/AbstractEstimateWriter.php    (общая логика заполнения xlsx)
app/Support/Documents/PreliminaryEstimateWriter.php
app/Support/Documents/FinalEstimateData.php         (смета на основании предварительной)
app/Support/Documents/FinalEstimateWriter.php
app/Support/Documents/AbstractDocxWriter.php        (общая механика подстановки плейсхолдеров в .docx)
app/Support/Documents/ContractData.php              (договор на основании сметы)
app/Support/Documents/ContractWriter.php            (карта плейсхолдеров договора)
app/Support/Documents/CashAcceptanceActData.php     (акт приёма-передачи денег на основании сметы)
app/Support/Documents/CashAcceptanceActWriter.php   (карта плейсхолдеров акта)
app/Support/Documents/WorkCompletionActData.php     (акт выполненных работ: позиции с остатком по актам)
app/Support/Documents/WorkCompletionActWriter.php   (карта плейсхолдеров + таблица ведомости работ)
resources/js/pages/documents/works-act.tsx          (предпросмотр: отметка позиций и объёмов, аванс)
storage/app/private/templates/docs/works-act.docx
resources/js/pages/documents/cash-act.tsx           (предпросмотр/редактирование акта)
storage/app/private/templates/docs/money-act.docx
app/Support/RublesInWords.php                       (сумма прописью)
resources/js/pages/documents/contract.tsx           (предпросмотр/редактирование договора)
storage/app/private/templates/docs/smeta-final.xlsx
storage/app/private/templates/docs/order-sample.docx
database/migrations/2026_08_10_000000_create_documents_table.php
routes/web.php                       (группа documents.*)
resources/js/pages/documents/index.tsx
resources/js/pages/documents/create.tsx
resources/js/pages/documents/preview.tsx
resources/js/components/app-sidebar.tsx
tests/Feature/DocumentTest.php
storage/app/private/templates/docs/smeta-sample.xlsx
```
