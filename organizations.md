# Организации

Документ описывает, как в панели устроены организации: своя организация пользователя,
справочник организаций-партнёров и связи между ними.

## Главная идея

Все организации — и своя, и партнёры — лежат в **одной таблице `organizations`**.
Отдельной таблицы для партнёров нет: роль организации определяется не колонкой,
а тем, какие связи на неё ссылаются.

| Роль организации | Чем определяется |
|------------------|------------------|
| Рабочая организация пользователя | есть запись в `organization_members` |
| Карточка партнёра в справочнике | есть запись в `partner_organization_links.organization_id` |

Роли не исключают друг друга: одна и та же запись может быть и рабочей организацией,
и числиться партнёром в справочниках других организаций.

## Схема данных

```
users
  └─ organization_members (user_id, organization_id, is_owner)
        └─ organizations ──────────────────────────────┐
             │  id, type, name, legal_name,            │
             │  inn, kpp, ogrn, address, phone, email, │
             │  director_surname, director_name,       │
             │  director_father_name,                  │
             │  bank_name, bank_bik,                   │
             │  bank_account, bank_corr_account        │
             │                                         │
             ├─ partner_organization_links             │
             │    parent_organization_id ──────────────┘ (владелец справочника)
             │    organization_id ─────────────────────── (партнёр)
             │
             └─ attribute_values (EAV: паспорт руководителя)
                  attribute_id → attributes (entity_type = App\Models\Organization)
```

### `organizations`

Миграции: `2026_08_09_000000_create_organizations_table`,
`2026_08_16_000003_add_director_name_parts_to_organizations_table`,
`2026_10_01_000000_add_type_to_organizations_table`.

Все колонки nullable, кроме `id`. Soft-deletes нет.

| Колонка | Назначение |
|---|---|
| `type` | организационно-правовая форма, `App\Enums\OrganizationType` (`ip` / `ooo`) |
| `name` | краткое наименование |
| `legal_name` | полное юридическое наименование |
| `inn`, `kpp`, `ogrn` | ИНН, КПП, ОГРН/ОГРНИП |
| `address`, `phone`, `email` | контакты и юридический адрес |
| `director_surname`, `director_name`, `director_father_name` | ФИО руководителя по частям |
| `bank_name`, `bank_bik`, `bank_account`, `bank_corr_account` | банковские реквизиты |

### `organization_members`

Связь пользователя с организацией. Миграция
`2026_08_09_000001_create_organization_members_table`.

- `organization_id`, `user_id` — внешние ключи с `cascadeOnDelete`;
- `is_owner` — признак владельца (условно генеральный директор);
- `unique(organization_id, user_id, is_owner)` — пользователь не числится в
  организации дважды в одной роли;
- частичный уникальный индекс `organization_members_single_owner_unique`
  на `(organization_id) WHERE is_owner` — у организации только один владелец.
  Создаётся только на PostgreSQL и SQLite.

### `partner_organization_links`

Справочник: кто с кем может работать. Миграция
`2026_10_01_000001_create_partner_organization_links_table`.

- `parent_organization_id` → `organizations.id` — организация-владелец справочника;
- `organization_id` → `organizations.id` — организация-партнёр;
- оба ключа с `cascadeOnDelete`;
- `unique(parent_organization_id, organization_id)` — партнёр добавляется в
  справочник один раз.

## Связь организации с пользователем

Пользователь связан с организацией **только через `organization_members`**.
Прямого `user_id` в `organizations` нет — это даёт запас на несколько участников
в одной организации.

Цепочка, по которой находится организация текущего пользователя:

```
User.id → OrganizationMember.user_id → OrganizationMember.organization_id → Organization.id
```

Реализация — `App\Models\User`:

```php
public function currentOrganization(): ?Organization
{
    return $this->ownedOrganization ?? $this->organizations()->first();
}
```

Сначала берётся организация, где пользователь владелец (`is_owner = true`),
иначе — первая, где он просто участник.

### Отношения в моделях

`App\Models\User`:

| Метод | Тип | Что возвращает |
|---|---|---|
| `memberships()` | HasMany | записи `organization_members` пользователя |
| `organizations()` | BelongsToMany | организации пользователя (`withPivot('is_owner')`) |
| `ownedOrganization()` | HasOneThrough | организация, где пользователь владелец |
| `currentOrganization()` | — | организация пользователя (не отношение, а метод) |

`App\Models\Organization`:

| Метод | Тип | Что возвращает |
|---|---|---|
| `members()` | HasMany | участники организации |
| `users()` | BelongsToMany | пользователи организации |
| `owner()` | HasOneThrough | владелец организации |
| `partnerLinks()` | HasMany | записи справочника, где эта организация — владелец |
| `partners()` | BelongsToMany | организации-партнёры из её справочника |
| `partnerOf()` | BelongsToMany | справочники, в которых числится сама организация |

`App\Models\PartnerOrganizationLink`: `parentOrganization()`, `organization()`.

### Инвариант: у каждого пользователя есть организация

Разделы, привязанные к организации, при `currentOrganization() === null`
отдают **404**. Организация создаётся:

- при регистрации — `RegisteredUserController::store()` в транзакции создаёт
  пустую `Organization` и делает пользователя владельцем;
- при сидировании — `DatabaseSeeder` делает то же для `test@example.com`
  (идемпотентно, повторный `php artisan db:seed` безопасен).

Если пользователь появился в базе в обход этих двух путей, он получит 404
на `/partners` и `/settings/organization`.

## Справочник организаций-партнёров

### Что это

Список организаций, с которыми может работать организация пользователя: форма
(ИП или ООО), реквизиты, банковские данные и данные руководителя. Карточка
партнёра — это запись в `organizations` плюс связь в `partner_organization_links`.

Партнёр — не пользователь системы: у него нет ни аккаунта, ни записей в
`organization_members`.

### Маршруты

`routes/web.php`, внутри группы `middleware('auth')`:

```php
Route::resource('partners', PartnerOrganizationController::class)
    ->parameters(['partners' => 'organization']);
```

| Метод | URL | Имя | Действие |
|---|---|---|---|
| GET | `/partners` | `partners.index` | список с поиском и фильтром |
| GET | `/partners/create` | `partners.create` | форма добавления |
| POST | `/partners` | `partners.store` | создание карточки и связи |
| GET | `/partners/{organization}` | `partners.show` | карточка партнёра |
| GET | `/partners/{organization}/edit` | `partners.edit` | форма редактирования |
| PUT/PATCH | `/partners/{organization}` | `partners.update` | сохранение |
| DELETE | `/partners/{organization}` | `partners.destroy` | удаление из справочника |

Пункт меню «Организации» → `/partners` живёт в `mainNavItems`
(`resources/js/components/app-sidebar.tsx`).

### Создание

`PartnerOrganizationController::store()` — всё в одной транзакции:

```php
$organization = Organization::create($request->safe()->except('passport'));

// User.id -> OrganizationMember.user_id -> OrganizationMember.organization_id
// -> partner_organization_links.parent_organization_id
$parent->partnerLinks()->create(['organization_id' => $organization->id]);

$this->syncPassport($organization, $request);
```

`$parent` — результат `currentOrganization()`, то есть владельцем связи всегда
становится организация текущего пользователя.

### Удаление

`destroy()` снимает связь, а саму организацию удаляет только если она больше
никому не нужна:

```php
$parent->partnerLinks()->where('organization_id', $organization->id)->delete();

if (! $organization->members()->exists() && ! $organization->partnerOf()->exists()) {
    $organization->attributeValues()->delete();
    $organization->delete();
}
```

- организация с участниками (чья-то рабочая организация) не удаляется никогда;
- организация, которая есть в справочниках других организаций, остаётся;
- EAV-значения удаляются вручную: у полиморфной связи `attribute_values.entity`
  нет внешнего ключа, каскада от `organizations` к ней не существует.

### Доступ

| Проверка | Где | Результат |
|---|---|---|
| у пользователя есть организация | `parentOrganization()` | иначе `abort(404)` |
| партнёр в справочнике пользователя | `assertInDirectory()` | иначе `abort(404)` |
| право на запись | `authorize()` в form request | иначе `403` |

Чужая карточка по прямому URL недоступна на `show`, `edit`, `update`, `destroy`.

### Список

`index()` отдаёт `partners/index` с пагинацией по 15 записей:

- поиск по `name`, `legal_name`, `inn`;
- фильтр по форме (`type`);
- сортировка по `name`;
- оператор поиска зависит от драйвера: `ilike` на PostgreSQL, `like` на SQLite
  (в тестах) — см. `likeOperator()`.

## Форма: ИП или ООО

`App\Enums\OrganizationType` — строковый enum, значения `ip` и `ooo`,
кастится на `organizations.type`.

| Метод | `ip` | `ooo` |
|---|---|---|
| `label()` | ИП | ООО |
| `fullLabel()` | Индивидуальный предприниматель | Общество с ограниченной ответственностью |
| `color()` | `amber` | `blue` |
| `innLength()` | 12 | 10 |
| `registrationNumberLength()` | 15 | 13 |
| `registrationNumberLabel()` | ОГРНИП | ОГРН |

`options()` отдаёт `[{value, label}]` для `<Select>` на фронтенде.
`color()` используется компонентом `StatusBadge`.

Что меняется от формы:

- **КПП** — только у ООО. У ИП поле скрыто в форме, а
  `prepareForValidation()` принудительно обнуляет `kpp`, чтобы не осталось
  значения после смены формы;
- **ИНН** — проверяется точная разрядность: 12 цифр у ИП, 10 у ООО;
- **ОГРН** — 15 цифр (ОГРНИП) у ИП, 13 у ООО; подпись поля тоже меняется;
- **подписи блоков** — «Предприниматель» вместо «Руководитель», «Адрес»
  вместо «Юридический адрес».

## EAV: данные руководителя

Реквизиты организации лежат в колонках `organizations`, а паспортные данные
руководителя — в EAV (`attributes` / `attribute_values`) через трейт
`App\Models\Concerns\HasEavAttributes`.

Атрибуты объявлены для `entity_type = 'App\Models\Organization'`, поэтому
карточки партнёров и своя организация используют **один и тот же набор полей** —
отдельного объявления для партнёров не требуется.

Источник правды — `App\Support\Passport::extendedFields()`, сидер —
`OrganizationAttributeSeeder`:

| Код атрибута | Поле формы | Тип |
|---|---|---|
| `passport_type` | Паспорт | `select` (`Passport::TYPES`) |
| `passport_series` | Серия | `string` |
| `passport_number` | Номер | `string` |
| `passport_issued_by` | Кем выдан | `text` |
| `passport_issued_at` | Дата выдачи | `date` |
| `passport_registration_address` | Адрес регистрации | `text` |

Работа со значениями:

```php
$organization->setEav('passport_series', '4509');   // записать
$organization->getEav('passport_series');           // прочитать с приведением типа
$organization->eav();                               // ['код' => значение, ...]
```

**Важно:** `setEav()` ищет определение атрибута через `firstOrFail()`. Если
`OrganizationAttributeSeeder` не прогнан, сохранение паспорта упадёт
`ModelNotFoundException`, то есть вернёт 404. На новой базе сидер обязателен:

```bash
php artisan db:seed --class=OrganizationAttributeSeeder
```

Контроллер раскладывает EAV в плоский массив `passport` для фронтенда
(`passportPayload()`) и обратно (`syncPassport()`); даты приводятся к `Y-m-d`.

## Валидация

`StorePartnerOrganizationRequest` (и наследник `UpdatePartnerOrganizationRequest`
с теми же правилами):

| Поле | Правила |
|---|---|
| `type` | обязательно, enum `OrganizationType` |
| `name` | обязательно, до 255 символов |
| `legal_name` | до 255 |
| `inn` | 10–12 цифр + точная разрядность по форме + нет дубля в справочнике |
| `kpp` | 9 цифр; обнуляется для ИП |
| `ogrn` | 13–15 цифр + точная разрядность по форме |
| `address` | до 255 |
| `phone` | до 50 |
| `email` | email, до 255 |
| `director_surname`, `director_name`, `director_father_name` | до 255 |
| `bank_name` | до 255 |
| `bank_bik` | 9 цифр |
| `bank_account`, `bank_corr_account` | 20 цифр |
| `passport.type` | одно из `Passport::TYPES` |
| `passport.series` / `number` | до 10 / до 20 |
| `passport.issued_by` | до 255 |
| `passport.issued_at` | дата |
| `passport.registration_address` | до 1000 |

Запрет дублей: организация с таким же ИНН не добавится в справочник повторно
(при редактировании текущая запись из проверки исключается).

## Фронтенд

| Файл | Назначение |
|---|---|
| `resources/js/pages/partners/index.tsx` | список, поиск, фильтр по форме, пагинация |
| `resources/js/pages/partners/create.tsx` | страница добавления |
| `resources/js/pages/partners/edit.tsx` | страница редактирования |
| `resources/js/pages/partners/show.tsx` | карточка партнёра |
| `resources/js/pages/partners/partner-form.tsx` | общая форма для создания и редактирования |

Форма разбита на блоки: наименование, «Реквизиты», «Руководитель»
(ФИО + паспорт), «Банковские реквизиты».

## Своя организация пользователя

Редактируется отдельно, в настройках: `/settings/organization`,
`Settings\OrganizationController`, страница
`resources/js/pages/settings/organization.tsx`. Набор полей тот же, паспорт
руководителя — тот же EAV.

Известное расхождение: `OrganizationUpdateRequest` не содержит поля `type`,
поэтому через настройки форму (ИП/ООО) своей организации выбрать нельзя —
у организаций, созданных при регистрации, `type` остаётся `null`.

## Тесты

`tests/Feature/PartnerOrganizationTest.php` — 17 тестов:

- гость не видит справочник;
- в списке только свои партнёры, чужие не видны;
- работают поиск и фильтр по форме;
- открывается форма добавления;
- организация сохраняется и связывается с организацией пользователя;
- паспорт руководителя пишется в EAV;
- обязательны `type` и `name`, неизвестная форма отклоняется;
- разрядность ИНН проверяется по форме;
- дубль по ИНН в одном справочнике отклоняется;
- КПП не сохраняется для ИП;
- партнёр обновляется (включая EAV);
- открываются карточка и форма редактирования;
- чужой партнёр недоступен (404 на четырёх маршрутах);
- удаление снимает связь, удаляет карточку и её EAV-значения;
- организация, нужная кому-то ещё, при удалении связи сохраняется;
- пользователь без организации не может пользоваться справочником.

## Памятка по подводным камням

1. **Нет организации — 404.** Любой раздел, привязанный к организации, требует
   `currentOrganization()`. Новым пользователям организация создаётся
   автоматически только при регистрации и при сидировании.
2. **Нужен `OrganizationAttributeSeeder`.** Без него сохранение паспорта
   руководителя падает с 404.
3. **`organizations.type` бывает `null`** — у организаций, созданных при
   регистрации. На фронтенде это учтено (`type_label` может быть `null`).
4. **Удаление организации не каскадит в EAV.** Полиморфная связь
   `attribute_values.entity` без внешнего ключа; чистить значения нужно явно,
   как это делает `destroy()`.
5. **Одна организация — в нескольких справочниках.** Удаление из одного
   справочника не удаляет карточку, если она используется где-то ещё.
6. **У `organizations` нет soft-deletes**, в отличие от `City`.
