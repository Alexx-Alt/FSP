# Матрица требований FSP v6

Матрица привязывает функциональность ветки `feature/fsp-match-v6-analytics` к требованиям ФСП и ключевым решениям команды.

| Требование | Реализация / доказательство |
| --- | --- |
| Кабинет кандидата | `/candidate`, `CandidateController`, `CandidateCenter.vue` |
| Кабинет работодателя | `/workspace` в employer mode + `/employer/jobs` |
| Кандидат выбирает желаемый грейд | `users.desired_grade`, Career Compass |
| Грейд назначается только тестом | `assessment_runs`, `confirmed_grade`, `grade_status`, `LiveAssessmentService` |
| FSP достижения не заменяют тест | `fsp_achievements`, evidence + небольшой boost в `MatchEngine` |
| Кандидат без FSP-истории поддерживается | обычный профиль/assessment/skills работают независимо от FSP ID |
| Неподтверждённый грейд не скрывается | понижающий `gradeFit`, кандидат остаётся в выдаче |
| Cooldown повторной проверки | assessment history + 30-дневная логика смены грейда |
| Разные варианты assessment | случайная выборка вопросов + `variant_key` |
| Вопросы по одному | `/candidate/assessment/session`, `AssessmentSession.vue` |
| Практическая задача | `assessment_code_questions`, `assessment_code_answers` |
| Integrity / anti-cheat signals | `assessment_live_events`, fullscreen/blur/visibility/copy/paste/rapid insert |
| Проверка похожести кода | `maxSimilarity`, `code_similarity` signal |
| Сигнал ≠ автоматический бан | `reviewRequired`; грейд не подтверждается автоматически при высоком риске |
| Test-retest | `retest_of`, avg delta, grade stability |
| Банк заданий | `assessment_questions` + category/difficulty |
| Древо компетенций | `skill_nodes` + `candidate_skill_evidence` |
| Граф как Obsidian | `fspEnhancements.ts`: graph/tree, zoom, pan, drag, fit, filters, relations |
| Evidence states | `confirmed / partial / claimed / unknown` |
| Объяснимый подбор | `MatchEngine`: route + reasons + matchedSkills + missingSkills + factor scores |
| Категоризация | `primary_category`, assessment по категории, Match category fit |
| Фильтры работодателя | город, категория, грейд, формат, Match, assessment, integrity |
| Фильтры кандидата | город, грейд, формат, Match score, поиск и сортировки |
| Зарплата до контакта | `jobs.salary_min/salary_max`, отображение в Vacancy Studio / Match |
| Работодатель создаёт/редактирует вакансии | `/employer/jobs`, `EmployerJobsController` |
| Статусы вакансии | `draft/open/paused/closed` |
| Самостоятельный отклик | `InteractionController::apply`, `source=self_application` |
| Адресное приглашение | `InteractionController::invite`, `source=match_invite` |
| Массовая работа с shortlist | выбор нескольких кандидатов, фильтры и comparison UI |
| Контакты после взаимного шага | `contacts_unlocked_at` |
| Обратная связь при отклике | message → `case_comments` / `case_events` |
| Обратная связь при найме | `/workspace/cases/{case}/feedback` |
| Несколько ЛПР | `case_participants`, `can_decide` |
| Таски / прогресс / SLA | `case_tasks`, `progress`, `next_action_at` |
| Алерты | `interaction_alerts` |
| Комментарии | `case_comments`; internal скрыты от кандидата |
| История | `case_events` |
| Повторы и возвраты | `duplicate_group`, `identity_signal`, `reopened_from_case_id` |
| Кандидат сравнивает себя с рынком | **обезличенный** peer benchmark; другие профили/имена не раскрываются |
| Кандидат сравнивает компании | multi-select company comparison |
| Employer сравнивает кандидатов | multi-select candidate comparison |
| Employer сравнивает сотрудников | team benchmark |
| Employer сравнивает компанию с рынком | company benchmark с `isCurrent` |
| Admin видит систему | admin workspace + `/jury-demo` |
| Города России | city benchmark + выбор/сортировка по городам |
| 3-срезная аналитика | люди / компании / география |
| Privacy / согласия | `/candidate/settings`, `06_mega_update_compliance.sql` |
| Отзыв публикации профиля | `profile_visibility`, `consent_processing`, `consent_publication`; MatchEngine фильтрует выдачу |
| FSP ID / Keycloak stub | `fsp_id_external`, `fsp_id_linked`, `fsp_achievements` |
| Проверка работодателя / trust skeleton | `companies.verification_status`, `trust_score` |
| LLM не обязателен | Match/analytics/assessment критический путь детерминированы PHP/MySQL |
| Работа без GPU | Laravel + MySQL; GPU/LLM не требуются для обязательного сценария |
| Синтетический датасет | `02_seed.sql` |
| Размеченные пары для Match | `validation_pairs.json` |
| Воспроизводимая валидация | `php artisan fsp:validate` → precision/recall/F1/accuracy/separation |
| OpenAPI | `docs/openapi.yaml` |
| Docker | `docker-compose.yml`, `docker/Dockerfile` |
| CI | `.github/workflows/tests.yml` выполняет setup/build/checks/PHP syntax |
| Матрица официальных критериев | `/jury-demo`, веса 35/25/10/7/6/7/10 |
| Сценарий защиты | `docs/EVALUATION_READY.md` + `/jury-demo` |

## Официальные веса оценки

- новая механика подбора — **35%**;
- тестирование / валидация / категоризация — **25%**;
- UX — **10%**;
- техническая проработка — **7%**;
- полнота и документация — **6%**;
- дополнительный функционал — **7%**;
- презентация — **10%**.

Итого: **100%**.
