# FSP / ИТ-MATCH PRO v6 — база данных

Папка содержит отдельный сценарий MySQL 8 для ветки `feature/fsp-match-v6-analytics`.

Для чистого развёртывания запускайте по порядку:

1. `00_create_database.sql` — создаёт `fsp_match_v6`.
2. `01_schema.sql` — создаёт полную базовую схему.
3. `02_seed.sql` — загружает синтетические данные: кандидатов, работодателей, города России, компании, вакансии, assessment, дерево компетенций, FSP evidence, процессы найма, ЛПР, задачи, комментарии, историю и алерты.
4. `03_optional_views.sql` — необязательные views для ручной аналитики.
5. `04_phase1_candidate_center.sql` — Phase 1: категории Backend / Frontend / Data / ML/AI / DevOps / Full-stack, Career Compass, банк assessment, ответы, integrity-сигналы и деревья новых направлений.
6. `05_phase1_2_live_assessment.sql` — Phase 1.2: живая assessment-сессия, события прокторинга, практические code-задачи и ответы на них.

Если база `fsp_match_v6` уже развёрнута и `04_phase1_candidate_center.sql` выполнялся, **не выполняйте 00–04 заново**. Достаточно выполнить:

```sql
SOURCE database/fsp_match_v6/05_phase1_2_live_assessment.sql;
```

или открыть `05_phase1_2_live_assessment.sql` в MySQL Workbench и выполнить целиком.

Если вы обновляетесь сразу со старой v6 без Candidate Center, сначала выполните `04_phase1_candidate_center.sql`, затем `05_phase1_2_live_assessment.sql`.

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

После `04_phase1_candidate_center.sql` кандидат получает отдельный крупный кабинет `/candidate`: профессиональный паспорт, Career Compass, дерево evidence, объяснимые вакансии, test-retest историю, cooldown смены грейда, FSP ID и Process Center.

## Phase 1.2 — live assessment + реальные действия рынка

После `05_phase1_2_live_assessment.sql` новый assessment идёт по одному вопросу за раз. Смена вкладки, потеря фокуса, copy/paste/cut, подозрительные горячие клавиши, выход из fullscreen, быстрый ответ и резкая вставка кода фиксируются как отдельные integrity-сигналы. В каждой сессии есть практическая задача с текстом решения; ответ получает отдельный score и сравнивается с прошлыми решениями как сигнал возможного копирования. Повышенный риск не обнуляет тест автоматически — попытка требует ручной проверки и не меняет подтверждённый грейд до неё.

Механика рынка использует уже существующие `interaction_cases`: кандидат может откликнуться на вакансию, работодатель — адресно пригласить кандидата. Для входящего отклика работодатель может принять его; после взаимного шага контакты разблокируются. Повторные активные процессы по одной паре кандидат-вакансия не создаются.

Правила модели сохраняются: желаемый грейд выбирает кандидат; официальный `confirmed_grade` появляется только из assessment; FSP ID и другие evidence усиливают профиль внутри уже сопоставимой категории/грейда, но не назначают грейд; неподтверждённый грейд не скрывает кандидата; integrity-сигналы сами по себе не являются автоматическим обвинением; `interaction_cases` хранит длительный сетевой процесс с несколькими ЛПР, задачами, прогрессом, алертами, комментариями, дублями, переоткрытиями и audit trail.
