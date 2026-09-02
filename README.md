# Remont Panel

Панель для работы со сметами и документами по ремонту квартир.

**Стек:** Laravel 12 · Inertia 2 · React + TypeScript · Tailwind CSS · PostgreSQL 17.

## Требования

- PHP 8.3+ и Composer
- Node.js 20+ и npm
- Docker (для PostgreSQL)

## Быстрый старт

```bash
# 1. Зависимости
composer install
npm install

# 2. Окружение (.env уже настроен на pgsql; при клонировании — скопировать пример)
cp .env.example .env
php artisan key:generate

# 3. Поднять PostgreSQL 17
docker compose up -d

# 4. Миграции (+ демо-данные, когда появятся сидеры)
php artisan migrate
# php artisan migrate:fresh --seed

# 5. Запуск в dev-режиме (сервер + очередь + логи + Vite)
composer run dev
```

Приложение: http://127.0.0.1:8000

## База данных

PostgreSQL 17 поднимается через `docker-compose.yml` (том `pgdata`).

| Параметр | Значение |
|----------|----------|
| host | `127.0.0.1` |
| **port** | **`5438`** (порт 5432 занят другим сервисом; при необходимости смените `DB_PORT` в `.env`) |
| database | `remont` |
| user | `remont` |
| password | `secret` *(только для локальной разработки — на прод задать через секреты)* |

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

## Разработка

План работ по этапам — в [`plan.md`](./plan.md).
