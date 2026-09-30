# App Base

Заготовка приложения: Laravel 12 · Inertia 2 · React 19 + TypeScript · Tailwind CSS 4 · PostgreSQL 17.

Доменной логики нет — только каркас, на который наращивается новый проект.

## Что уже есть

| Блок | Что внутри |
|------|------------|
| Аутентификация | login / register / сброс и подтверждение пароля (стартовый набор Laravel) |
| Организации | `Organization` + `OrganizationMember`, владелец, реквизиты и банковские данные; организация создаётся при регистрации пользователя |
| EAV-свойства | `Attribute` / `AttributeValue` + трейт `HasEavAttributes`: произвольные типизированные поля (`string`, `decimal`, `boolean`, `date`, `select`) для любой модели, с областью видимости по типу сущности |
| Паспортные данные | `App\Support\Passport` — набор полей документа, удостоверяющего личность, поверх EAV (профиль пользователя, руководитель организации) |
| Справочник городов | `City` со скоупами `active` / `availableFor` / `ordered`: системные записи плюс собственные записи организации |
| Настройки | профиль, пароль, оформление (светлая/тёмная тема), реквизиты организации |
| UI-каркас | сайдбар-лейаут, breadcrumbs, flash-уведомления, пагинация, `StatusBadge`, компоненты shadcn/ui |
| Деплой | Ansible-плейбук на чистый VPS (см. `deploy/ansible/README.md`) |
| CI | GitHub Actions: `lint.yml`, `tests.yml` |

## Требования

- PHP 8.3+ и Composer
- Node.js 20+ и npm
- Docker (для локального PostgreSQL)

## Быстрый старт

```bash
# 1. Зависимости
composer install
npm install

# 2. Окружение
cp .env.example .env
php artisan key:generate

# 3. Поднять PostgreSQL 17
docker compose up -d

# 4. Миграции и демо-данные
php artisan migrate --seed

# 5. Dev-режим (сервер + очередь + логи + Vite)
composer run dev
```

Приложение: http://127.0.0.1:8000

## База данных

PostgreSQL 17 поднимается через `docker-compose.yml` (том `pgdata`). Параметры берутся
из `.env`; по умолчанию — база `remont`, пользователь `remont`, порт **5438**
(5432 обычно занят другим сервисом).

```bash
docker compose up -d       # поднять
docker compose down        # остановить (данные сохраняются в томе)
docker compose down -v     # остановить и удалить данные
```

## Полезные команды

```bash
npm run build          # production-сборка фронта
npm run lint           # ESLint --fix
npm run format         # Prettier
./vendor/bin/pint      # PHP code style
php artisan test       # тесты
```

## С чего начинать новый проект

1. Переименовать приложение: `APP_NAME` в `.env`/`.env.example`, `name` и `description`
   в `composer.json`, заголовок этого README.
2. Заменить логотип (`resources/js/components/app-logo*.tsx`, `public/logo.svg`) и
   ссылки в футере сайдбара (`resources/js/components/app-sidebar.tsx`).
3. Добавлять доменные модели, миграции, контроллеры и страницы Inertia;
   пункты меню — в `mainNavItems` того же `app-sidebar.tsx`.
4. Решить судьбу справочника городов (`City`, `CityController`): сейчас он остался
   без UI — создание города доступно только эндпоинтом `POST /cities`.
