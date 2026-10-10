# Запуск FSP Match V6 в Docker

В этой ветке находится один проект: интерфейс и функционал `fsp-match-v6-analytics`, основная MySQL и отдельная MySQL для персональных данных.

## Первый запуск

```powershell
git switch feature/fsp-single-platform
npm run env:setup
docker compose up -d --build
```

Откройте http://127.0.0.1:8000.

Если команда `npm run env:setup` отсутствует в локальном клоне, выполните:

```powershell
node scripts/setup-env.mjs
docker compose up -d --build
```

## Demo-вход

- Соискатель: `candidate@fsp.demo`
- Работодатель: `employer@fsp.demo`
- Администратор: `admin@fsp.demo`
- Пароль всех demo-аккаунтов: `FspDemo2026!`

При старте контейнера команда `fsp:private-bootstrap` переносит demo-email в приватную БД. В основной БД вместо адреса хранится HMAC-индекс. Email, имя и телефон во второй БД шифруются ключом `APP_KEY` средствами Laravel.

## Управление

```powershell
docker compose ps
docker compose logs -f app
docker compose down
```

Команда `docker compose down` сохраняет обе БД. Для полного удаления данных нужны тома, поэтому `docker compose down -v` используйте только когда действительно хотите пересоздать базы с нуля.

Не меняйте `APP_KEY` после появления данных: старые зашифрованные значения перестанут расшифровываться.
