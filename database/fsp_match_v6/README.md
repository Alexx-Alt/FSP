# FSP / ИТ-MATCH PRO v6 — база данных

Папка содержит отдельный сценарий MySQL 8 для ветки `feature/fsp-match-v6-analytics`.

Запускайте по порядку:

1. `00_create_database.sql` — создаёт `fsp_match_v6`.
2. `01_schema.sql` — создаёт полную схему. Внутри выбранной demo-базы таблицы пересоздаются.
3. `02_seed.sql` — загружает синтетические данные: кандидатов, работодателей, города России, компании, вакансии, assessment, дерево компетенций, FSP evidence, процессы найма, ЛПР, задачи, комментарии, историю и алерты.
4. `03_optional_views.sql` — необязательные views для ручной аналитики.

Demo-аккаунты используют один пароль `FspDemo2026!`:

- кандидат: `candidate@fsp.demo`
- работодатель: `employer@fsp.demo`
- администратор: `admin@fsp.demo`

Минимальная настройка `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=fsp_match_v6
DB_USERNAME=root
DB_PASSWORD=your_password
SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
```

После изменения `.env`: `php artisan optimize:clear`.

Ключевые правила модели: желаемый грейд выбирает кандидат; официальный `confirmed_grade` появляется только из assessment; FSP ID и другие evidence усиливают профиль внутри уже сопоставимой категории/грейда, но не назначают грейд; неподтверждённый грейд не скрывает кандидата; `interaction_cases` хранит длительный сетевой процесс с несколькими ЛПР, задачами, прогрессом, алертами, комментариями, дублями, переоткрытиями и audit trail.
