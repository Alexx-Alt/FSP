# Матрица требований FSP v6

| Требование | Реализация |
| --- | --- |
| Кандидат выбирает желаемый грейд | `users.desired_grade` |
| Грейд назначается только тестом | `assessment_runs`, `confirmed_grade`, `grade_status` |
| FSP достижения не заменяют тест | `fsp_achievements`, небольшой boost в `MatchEngine` |
| Неподтверждённый грейд не скрывается | понижающий `gradeFit` |
| Разные варианты assessment | `variant_key` + admin analytics |
| Test-retest | `retest_of`, стабильность грейда |
| Древо компетенций | `skill_nodes` + `candidate_skill_evidence` |
| Объяснимый подбор | route + reasons + gaps |
| Адресное приглашение | `interaction_cases.source=match_invite` |
| Контакты после принятия | `contacts_unlocked_at` |
| Несколько ЛПР | `case_participants`, `can_decide` |
| Таски / прогресс / SLA | `case_tasks`, `progress`, `next_action_at` |
| Алерты | `interaction_alerts` |
| Комментарии | `case_comments`; internal скрыты от кандидата |
| История | `case_events` |
| Повторы и возвраты | `duplicate_group`, `identity_signal`, `reopened_from_case_id` |
| Candidate leaderboard | peer benchmark |
| Candidate сравнивает компании | company benchmark |
| Employer сравнивает сотрудников | team benchmark |
| Employer сравнивает компании | company benchmark с `isCurrent` |
| Admin видит систему | admin workspace |
| Города России | city benchmark |
| LLM не обязателен | Match/analytics детерминированы PHP/MySQL |
| Данные для проверки | `database/fsp_match_v6/02_seed.sql` |
