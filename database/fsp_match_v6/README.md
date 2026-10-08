# FSP / ИТ-MATCH PRO v6 — база данных

Папка содержит отдельный сценарий MySQL 8 для ветки `feature/fsp-match-v6-analytics`.

Для чистого развёртывания запускайте по порядку:

1. `00_create_database.sql` — создаёт `fsp_match_v6`.
2. `01_schema.sql` — создаёт полную базовую схему.
3. `02_seed.sql` — загружает синтетические данные: кандидатов, работодателей, города России, компании, вакансии, assessment, дерево компетенций, FSP evidence, процессы найма, ЛПР, задачи, комментарии, историю и алерты.
4. `03_optional_views.sql` — необязательные views для ручной аналитики.
5. `04_phase1_candidate_center.sql` — Phase 1: расширяет категории до Backend / Frontend / Data / ML/AI / DevOps / Full-stack, добавляет Career Compass, рабочий банк assessment, ответы, integrity-сигналы и базовые деревья новых направлений.

Если база `fsp_match_v6` у вас уже развёрнута по предыдущей версии, **не выполняйте 00–03 заново**. Достаточно выполнить только:

```sql
SOURCE database/fsp_match_v6/04_phase1_candidate_center.sql;
```

или открыть этот файл в MySQL Workbench и запустить целиком.

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

После изменения `.env` или SQL-схемы выполните `php artisan optimize:clear`.

## Phase 1 — Candidate Center

После `04_phase1_candidate_center.sql` кандидат получает отдельный крупный кабинет `/candidate`: профессиональный паспорт, Career Compass, дерево evidence, объяснимые вакансии, рабочий assessment с вариантами из БД, test-retest историю, cooldown смены грейда, FSP ID и Process Center.

Правила модели сохраняются: желаемый грейд выбирает кандидат; официальный `confirmed_grade` появляется только из assessment; FSP ID и другие evidence усиливают профиль внутри уже сопоставимой категории/грейда, но не назначают грейд; неподтверждённый грейд не скрывает кандидата; integrity-сигналы сами по себе не являются автоматическим обвинением; `interaction_cases` хранит длительный сетевой процесс с несколькими ЛПР, задачами, прогрессом, алертами, комментариями, дублями, переоткрытиями и audit trail.
