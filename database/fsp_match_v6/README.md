# FSP / ИТ-MATCH PRO v6 — база данных

Папка содержит отдельный сценарий MySQL 8 для ветки `feature/fsp-match-v6-analytics`.

## Чистое развёртывание

Запускайте SQL по порядку:

1. `00_create_database.sql` — создаёт `fsp_match_v6`.
2. `01_schema.sql` — создаёт полную базовую схему.
3. `02_seed.sql` — загружает синтетические данные: кандидатов, работодателей, города России, компании, вакансии, assessment, дерево компетенций, FSP evidence, процессы найма, ЛПР, задачи, комментарии, историю и алерты.
4. `03_optional_views.sql` — необязательные views для ручной аналитики.
5. `04_phase1_candidate_center.sql` — категории Backend / Frontend / Data / ML/AI / DevOps / Full-stack, Career Compass, банк assessment, ответы, integrity-сигналы и деревья направлений.
6. `05_phase1_2_live_assessment.sql` — live assessment, события integrity/proctoring, практические code-задачи и ответы.
7. `06_mega_update_compliance.sql` — privacy/consent кандидата, FSP ID link state и trust/verification компании.

`06_mega_update_compliance.sql` выполняется **один раз**. Это обычный upgrade script с `ALTER TABLE`, а не идемпотентная миграция.

Если ваша база уже находится на Phase 1.2, **не выполняйте 00–05 повторно**. Откройте в MySQL Workbench только:

```text
database/fsp_match_v6/06_mega_update_compliance.sql
```

и выполните файл целиком.

Если вы обновляетесь со старой v6 без Candidate Center, сначала примените `04`, затем `05`, затем `06`.

## Demo-аккаунты

У всех пароль `FspDemo2026!`:

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

После изменения `.env` или SQL-схемы:

```bash
php artisan optimize:clear
```

## Candidate Center

`/candidate` содержит профессиональный паспорт, Career Compass, Obsidian-like knowledge graph / дерево evidence, объяснимые вакансии, test-retest историю, cooldown смены грейда, FSP achievements и Process Center.

Правило неизменно: желаемый грейд выбирает кандидат, а `confirmed_grade` меняет только assessment. FSP ID / achievements добавляют evidence и небольшой ranking boost, но не назначают грейд.

## Live assessment

После `05_phase1_2_live_assessment.sql` assessment выдаёт один вопрос за раз. Отдельно фиксируются:

- `visibilitychange` / смена вкладки;
- потеря фокуса;
- выход из fullscreen;
- copy / paste / cut;
- context menu и подозрительные hotkeys;
- слишком быстрые ответы;
- резкая большая вставка кода;
- code similarity для практического ответа.

Integrity-сигнал **не является автоматическим обвинением**. Повышенный риск создаёт `reviewRequired`, а подтверждённый грейд не меняется автоматически до проверки.

## Process Center

Кандидат может самостоятельно откликнуться, работодатель — адресно пригласить. После взаимного шага контакты разблокируются. `interaction_cases` хранит длительный процесс: несколько ЛПР, задачи, комментарии, прогресс, алерты, дубли, переоткрытия и audit trail.

## Privacy / consent / FSP ID

После `06_mega_update_compliance.sql` кандидат может:

- включить/выключить участие профиля в новых подборках работодателей;
- отозвать согласие на обработку и/или публикацию профессионального профиля;
- указать mock FSP ID для демонстрации будущей интеграции.

Match Engine проверяет эти флаги и не включает приватные/отозванные профили в новые employer shortlist. Аналитика кандидата относительно других кандидатов обезличена.

## Синтетическая валидация Match

Файл `validation_pairs.json` содержит размеченные пары `кандидат ↔ вакансия` с позитивными и негативными примерами из seed-датасета.

Запуск:

```bash
php artisan fsp:validate
```

Машиночитаемый вывод:

```bash
php artisan fsp:validate --json
```

Команда показывает precision, recall, F1, accuracy, средний score релевантных/нерелевантных пар и separation. Для воспроизводимого demo boundary используется:

- `invite` / `clarify` → relevant;
- `perspective` / `observe` → not relevant.

Те же показатели выводятся администратору в `/jury-demo`.

## Docker

Для чистой демонстрационной среды:

```bash
docker compose up --build
```

MySQL при создании нового volume выполняет SQL-файлы `00..06` по имени автоматически.

Полный сброс **только Docker demo-БД**:

```bash
docker compose down -v
docker compose up --build
```

Не используйте `down -v`, если в Docker volume находятся нужные вам данные.
