# Деплой Remont Panel через Ansible

Ansible-плейбук для развёртывания приложения (Laravel 12 + Inertia/React + PostgreSQL)
на чистый VPS с **Ubuntu 22.04 / 24.04** (или Debian 12).

## Что ставится и настраивается

| Роль       | Что делает |
|------------|------------|
| `common`   | Базовые пакеты, пользователь `deploy`, таймзона |
| `php`      | PHP-FPM 8.3 + расширения (pgsql, mbstring, xml, zip, gd, bcmath, intl, gmp), выделенный FPM-пул под `deploy`, Composer |
| `node`     | Node.js 20 (для сборки фронтенда) |
| `app`      | Клонирует код, пишет `.env`, `composer install`, `npm ci && npm run build`, `migrate`, `storage:link`, кэш конфигов |
| `queue`    | systemd-воркер очереди (`queue:work`) + cron планировщика (`schedule:run`) |
| `caddy`    | Веб-сервер под Laravel с **автоматическим HTTPS** (Let's Encrypt из коробки) |

Драйверы `session`/`cache`/`queue` — `database` (Redis не требуется). Файлы — диск
`local` по умолчанию; для S3 задайте `filesystem_disk: s3` и AWS-креды в vault.

**База данных — внешняя (управляемый PostgreSQL).** Локально СУБД не ставится;
приложение подключается к `db_host:db_port` (по умолчанию `217.18.61.243:23482`).
База `db_name` и роль `db_user` с паролем из vault должны уже существовать на этом
инстансе, а сам инстанс — быть доступен с VPS. Таблицы создаются миграциями при деплое.

Зависит только от `ansible.builtin` — **внешние коллекции не нужны**.

## Предварительные требования

- Управляющая машина с Ansible ≥ 2.14.
- VPS с доступом по SSH и sudo. Рекомендуется **≥ 1 ГБ RAM** (сборка Vite требует памяти;
  на 512 МБ добавьте swap). Плейбук firewall не трогает — откройте нужные порты
  (80/443 и ваш SSH-порт) на стороне провайдера/сервера.
- Внешний PostgreSQL, доступный с VPS (по умолчанию `217.18.61.243:23482`) с готовыми
  базой и ролью приложения.
- DNS: A-запись домена указывает на IP сервера (Caddy автоматически выпустит
  TLS-сертификат Let's Encrypt для этого домена). Для локального запуска без домена
  поставьте `auto_https: false` в `group_vars/all.yml`.
- Код приложения. Два способа доставки (`deploy_method` в `group_vars/all.yml`):
  - **`local`** (по умолчанию) — git не нужен: плейбук пакует локальный проект
    (`project_src`) в архив и распаковывает на сервере. Нужен только `tar` на
    управляющей машине (есть везде).
  - **`git`** — сервер клонирует репозиторий. Публичный HTTPS — просто укажите
    `app_repo`; приватный по SSH — добавьте deploy-ключ на сервер.

## Настройка

```bash
cd deploy/ansible

# 1. Инвентарь
cp inventory.ini.example inventory.ini
$EDITOR inventory.ini                 # ansible_host / ansible_user

# 2. Переменные окружения приложения
$EDITOR group_vars/all.yml            # app_domain, deploy_method, db_*, TLS...
                                      # deploy_method=local — git не нужен (по умолчанию)
                                      # deploy_method=git   — задайте app_repo/app_version

# 3. Секреты (пароль БД, APP_KEY, при необходимости AWS)
cp group_vars/vault.yml.example group_vars/vault.yml
$EDITOR group_vars/vault.yml
ansible-vault encrypt group_vars/vault.yml
```

`APP_KEY` можно:
- сгенерировать заранее локально `php artisan key:generate --show` и вписать в vault, **или**
- оставить `vault_app_key: ""` — ключ будет создан на сервере при первом деплое и
  сохранён в `.env` (при повторных запусках не перезатирается).

## Запуск

```bash
# Проверка связи
ansible -i inventory.ini web -m ping

# Полный деплой
ansible-playbook site.yml --ask-vault-pass

# Если заходите не под root, а через sudo с паролем:
ansible-playbook site.yml --ask-vault-pass --ask-become-pass
```

## Обновление приложения (повторный деплой)

Тот же плейбук идемпотентен. Чтобы выкатить новую версию, поменяйте `app_version`
(ветка/тег/коммит) и запустите снова — код обновится, ассеты пересоберутся,
миграции применятся, конфиг перекэшируется, PHP-FPM и воркер очереди перезапустятся:

```bash
ansible-playbook site.yml --ask-vault-pass
```

Можно ускорить прогон отдельными тегами роли (например только приложение):

```bash
ansible-playbook site.yml --ask-vault-pass --tags app   # если добавите теги
```

## Полезное на сервере

```bash
systemctl status 'remont-worker@*'      # воркеры очереди
journalctl -u 'remont-worker@1' -f      # логи воркера
journalctl -u caddy -f                  # логи Caddy (в т.ч. выпуск сертификата)
tail -f /var/log/caddy/remont-access.log
sudo -u deploy php /var/www/remont-panel/artisan about
```

## Примечания по безопасности

- `inventory.ini` и `group_vars/vault.yml` не коммитятся (см. `.gitignore`).
- `.env` на сервере пишется с правами `0640`, владелец — `deploy`.
- PHP-FPM работает под `deploy` (владелец кода), Caddy обращается к нему по
  unix-сокету; скрытые файлы (`.env`, `.git`) закрыты в Caddyfile.
