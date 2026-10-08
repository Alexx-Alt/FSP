-- FSP / ИТ-MATCH PRO v6 — Phase 1: Candidate Center
USE fsp_match_v6;
SET NAMES utf8mb4;

ALTER TABLE users MODIFY primary_category ENUM('Backend','Frontend','Data','ML/AI','DevOps','Full-stack') NULL;
ALTER TABLE jobs MODIFY category ENUM('Backend','Frontend','Data','ML/AI','DevOps','Full-stack') NOT NULL;
ALTER TABLE assessment_runs MODIFY category ENUM('Backend','Frontend','Data','ML/AI','DevOps','Full-stack') NOT NULL;
ALTER TABLE skill_nodes MODIFY category ENUM('Backend','Frontend','Data','ML/AI','DevOps','Full-stack') NOT NULL;

CREATE TABLE IF NOT EXISTS career_compass_runs (
    run_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    candidate_id INT UNSIGNED NOT NULL,
    recommended_category ENUM('Backend','Frontend','Data','ML/AI','DevOps','Full-stack') NOT NULL,
    confidence TINYINT UNSIGNED NOT NULL,
    answers_json JSON NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_career_candidate FOREIGN KEY (candidate_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_career_candidate_time (candidate_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS assessment_questions (
    question_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category ENUM('Backend','Frontend','Data','ML/AI','DevOps','Full-stack') NOT NULL,
    skill_code VARCHAR(120) NOT NULL,
    prompt TEXT NOT NULL,
    options_json JSON NOT NULL,
    correct_index TINYINT UNSIGNED NOT NULL,
    difficulty TINYINT UNSIGNED NOT NULL DEFAULT 3,
    variant_group VARCHAR(80) NULL,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_assessment_question_pick (category, active, difficulty),
    INDEX idx_assessment_variant_group (variant_group)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS assessment_answers (
    answer_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    assessment_id BIGINT UNSIGNED NOT NULL,
    question_id INT UNSIGNED NOT NULL,
    selected_index TINYINT UNSIGNED NOT NULL,
    is_correct BOOLEAN NOT NULL,
    response_ms INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_assessment_answer_run FOREIGN KEY (assessment_id) REFERENCES assessment_runs(assessment_id) ON DELETE CASCADE,
    CONSTRAINT fk_assessment_answer_question FOREIGN KEY (question_id) REFERENCES assessment_questions(question_id) ON DELETE RESTRICT,
    INDEX idx_assessment_answer_run (assessment_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS assessment_integrity_events (
    event_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    candidate_id INT UNSIGNED NOT NULL,
    assessment_id BIGINT UNSIGNED NULL,
    event_type VARCHAR(80) NOT NULL,
    weight TINYINT UNSIGNED NOT NULL DEFAULT 1,
    metadata JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_integrity_candidate FOREIGN KEY (candidate_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_integrity_assessment FOREIGN KEY (assessment_id) REFERENCES assessment_runs(assessment_id) ON DELETE CASCADE,
    INDEX idx_integrity_assessment (assessment_id),
    INDEX idx_integrity_candidate_time (candidate_id, created_at)
) ENGINE=InnoDB;

DELETE FROM assessment_questions WHERE variant_group LIKE 'phase1-%';
INSERT INTO assessment_questions (category, skill_code, prompt, options_json, correct_index, difficulty, variant_group, active) VALUES
('Backend','API','Какой HTTP-метод обычно используют для полного обновления ресурса?','["GET", "PUT", "HEAD", "TRACE"]',1,1,'phase1-backend-1-1',1),
('Backend','SQL','Что чаще всего ускоряет фильтрацию по селективному столбцу в PostgreSQL?','["Индекс", "VACUUM перед каждым SELECT", "Увеличение VARCHAR", "Триггер"]',0,2,'phase1-backend-2-2',1),
('Backend','Transactions','Какое свойство ACID гарантирует, что транзакция выполняется целиком или не выполняется вовсе?','["Consistency", "Isolation", "Atomicity", "Durability"]',2,3,'phase1-backend-3-3',1),
('Backend','Queues','Какой приём помогает безопасно повторять обработку сообщения очереди?','["Idempotency key", "Случайный sleep", "Отключение retry", "Глобальный mutex навсегда"]',0,3,'phase1-backend-3-4',1),
('Backend','Caching','Что важно предусмотреть при cache-aside после изменения данных?','["Инвалидацию/обновление кеша", "Только увеличение TTL", "Отключение БД", "Хранение пароля в ключе"]',0,4,'phase1-backend-4-5',1),
('Backend','Architecture','Какой сигнал сильнее всего указывает на необходимость разделить синхронную операцию на фоновые задачи?','["Операция долгая и допускает eventual consistency", "Ответ всегда <10 мс", "Нет внешних зависимостей", "Одна строка в таблице"]',0,5,'phase1-backend-5-6',1),
('Frontend','HTML','Какой элемент семантически предназначен для основной навигации?','["nav", "span", "b", "canvas"]',0,1,'phase1-frontend-1-7',1),
('Frontend','TypeScript','Что даёт union type `string | null`?','["Разрешает строку или null", "Всегда превращает null в строку", "Запрещает строки", "Создаёт массив"]',0,2,'phase1-frontend-2-8',1),
('Frontend','Vue','Для чего в Vue чаще используют computed?','["Для производного реактивного значения", "Для прямого доступа к DOM вместо ref", "Для HTTP-сервера", "Для CSS reset"]',0,3,'phase1-frontend-3-9',1),
('Frontend','Performance','Что обычно уменьшает первоначальный JS bundle?','["Code splitting", "Дублирование зависимостей", "Inline всех изображений base64", "Отключение кеша"]',0,3,'phase1-frontend-3-10',1),
('Frontend','Accessibility','Что обязательно для интерактивного элемента, управляемого только иконкой?','["Доступное имя/aria-label", "Отрицательный tabindex всегда", "display:none", "Только title"]',0,4,'phase1-frontend-4-11',1),
('Frontend','Architecture','Когда выгодно вводить дизайн-систему компонентов?','["Когда интерфейсы повторяются между продуктами и нужна согласованность", "Когда страница одна и одноразовая", "Только ради увеличения bundle", "Чтобы убрать типизацию"]',0,5,'phase1-frontend-5-12',1),
('Data','SQL','Какая операция объединяет строки двух таблиц по условию?','["JOIN", "VACUUM", "GRANT", "COMMIT"]',0,1,'phase1-data-1-13',1),
('Data','ETL','Что означает idempotent pipeline?','["Повторный запуск не портит результат", "Запускается только один раз", "Не имеет логов", "Работает без данных"]',0,2,'phase1-data-2-14',1),
('Data','Warehousing','Что обычно хранится в fact table?','["Измеряемые события/метрики", "Только справочник стран", "CSS-токены", "Пароли пользователей"]',0,3,'phase1-data-3-15',1),
('Data','Streaming','Что помогает избежать повторной обработки события в consumer?','["Хранение offset/idempotency", "Удаление ключа события", "Случайный порядок", "Отключение checkpoint"]',0,3,'phase1-data-3-16',1),
('Data','Quality','Какой контроль полезен для проверки полноты дневной загрузки?','["Сравнение ожидаемого и фактического числа записей", "Проверка цвета графика", "Смена кодировки каждый день", "Удаление null без анализа"]',0,4,'phase1-data-4-17',1),
('Data','Modeling','Когда денормализация аналитической модели оправдана?','["Когда чтение доминирует и нужны простые быстрые запросы", "Всегда", "Только для OLTP-транзакций", "Когда запрещены индексы"]',0,5,'phase1-data-5-18',1),
('ML/AI','Metrics','Какая метрика особенно важна при сильном дисбалансе классов?','["F1/PR-AUC", "Только accuracy", "FPS интерфейса", "Размер шрифта"]',0,1,'phase1-ml-ai-1-19',1),
('ML/AI','Validation','Зачем нужен отдельный validation set?','["Для выбора модели без подглядывания в test", "Для хранения логов", "Для увеличения batch size", "Для деплоя CSS"]',0,2,'phase1-ml-ai-2-20',1),
('ML/AI','Leakage','Что такое data leakage?','["Использование информации, недоступной в момент реального предсказания", "Удаление дубликатов", "Сжатие модели", "Квантизация"]',0,3,'phase1-ml-ai-3-21',1),
('ML/AI','Serving','Что важно мониторить после выката модели?','["Drift, latency и качество", "Только размер README", "Количество git-веток", "Цвет кнопок"]',0,3,'phase1-ml-ai-3-22',1),
('ML/AI','Experiment','Что делает эксперимент воспроизводимым?','["Фиксация данных, seed, кода и параметров", "Случайные параметры без логов", "Только название модели", "Удаление окружения"]',0,4,'phase1-ml-ai-4-23',1),
('ML/AI','Production','Когда batch inference предпочтительнее online inference?','["Когда ответы не нужны мгновенно и объём большой", "Когда нужна реакция за миллисекунды", "Всегда", "Только для изображений"]',0,5,'phase1-ml-ai-5-24',1),
('DevOps','Containers','Что описывает Dockerfile?','["Сборку образа", "DNS-зону", "SQL-транзакцию", "UI-маршрут"]',0,1,'phase1-devops-1-25',1),
('DevOps','CI/CD','Зачем разделять build и deploy?','["Для воспроизводимого артефакта и контролируемой доставки", "Чтобы не хранить артефакты", "Чтобы отключить тесты", "Только ради логов"]',0,2,'phase1-devops-2-26',1),
('DevOps','Kubernetes','Что делает readiness probe?','["Показывает, готов ли pod принимать трафик", "Всегда перезапускает pod", "Создаёт namespace", "Шифрует secret"]',0,3,'phase1-devops-3-27',1),
('DevOps','IaC','Главное преимущество Infrastructure as Code?','["Версионируемая и воспроизводимая инфраструктура", "Отсутствие ревью", "Ручные изменения production", "Нет state"]',0,3,'phase1-devops-3-28',1),
('DevOps','Reliability','Что выражает SLO?','["Целевой уровень надёжности сервиса", "Версию ОС", "Количество разработчиков", "Размер контейнера"]',0,4,'phase1-devops-4-29',1),
('DevOps','Incident','Что важнее всего после серьёзного инцидента?','["Blameless postmortem и системные действия", "Найти виноватого", "Удалить логи", "Скрыть метрики"]',0,5,'phase1-devops-5-30',1),
('Full-stack','Web','Что связывает frontend с backend в типичном web-приложении?','["HTTP API", "Только CSS", "DNS TXT", "Git tag"]',0,1,'phase1-full-stack-1-31',1),
('Full-stack','Database','Где должна проверяться авторизация на изменение ресурса?','["На сервере", "Только в кнопке интерфейса", "В CSS", "В localStorage клиента"]',0,2,'phase1-full-stack-2-32',1),
('Full-stack','State','Что лучше хранить как server state?','["Данные, общие между устройствами и пользователями", "Hover кнопки", "Открыт ли локальный tooltip", "Размер окна"]',0,3,'phase1-full-stack-3-33',1),
('Full-stack','Security','Как защитить серверный endpoint от недоверенных входных данных?','["Валидация и авторизация на сервере", "Скрыть кнопку", "Минифицировать JS", "Поменять шрифт"]',0,3,'phase1-full-stack-3-34',1),
('Full-stack','Performance','Как найти узкое место end-to-end запроса?','["Трассировка/метрики по слоям", "Оптимизировать всё вслепую", "Отключить БД", "Удалить тесты"]',0,4,'phase1-full-stack-4-35',1),
('Full-stack','Architecture','Когда BFF (Backend for Frontend) может быть полезен?','["Когда клиенту нужен специализированный агрегированный API", "Всегда", "Только без frontend", "Когда API запрещён"]',0,5,'phase1-full-stack-5-36',1);

INSERT IGNORE INTO skill_nodes(parent_id,code,name,category,sort_order) VALUES
(NULL,'Data','Data','Data',500),(NULL,'MLAI','ML/AI','ML/AI',600),(NULL,'DevOps','DevOps','DevOps',700),(NULL,'FullStack','Full-stack','Full-stack',800);
SET @data_root = (SELECT skill_node_id FROM skill_nodes WHERE code='Data' LIMIT 1);
INSERT IGNORE INTO skill_nodes(parent_id,code,name,category,sort_order) VALUES
(@data_root,'Data.SQL','SQL','Data',510),(@data_root,'Data.ETL','ETL','Data',520),(@data_root,'Data.Streaming','Потоки','Data',530),(@data_root,'Data.Warehousing','Хранилища','Data',540),(@data_root,'Data.Quality','Качество','Data',550),(@data_root,'Data.Modeling','Моделирование','Data',560);
SET @ml_root = (SELECT skill_node_id FROM skill_nodes WHERE code='MLAI' LIMIT 1);
INSERT IGNORE INTO skill_nodes(parent_id,code,name,category,sort_order) VALUES
(@ml_root,'MLAI.Python','Python','ML/AI',610),(@ml_root,'MLAI.Metrics','Metrics','ML/AI',620),(@ml_root,'MLAI.Validation','Валидация','ML/AI',630),(@ml_root,'MLAI.Leakage','Leakage','ML/AI',640),(@ml_root,'MLAI.Serving','Serving','ML/AI',650),(@ml_root,'MLAI.Experiment','Эксперименты','ML/AI',660),(@ml_root,'MLAI.Production','Production','ML/AI',670);
SET @devops_root = (SELECT skill_node_id FROM skill_nodes WHERE code='DevOps' LIMIT 1);
INSERT IGNORE INTO skill_nodes(parent_id,code,name,category,sort_order) VALUES
(@devops_root,'DevOps.Containers','Контейнеры','DevOps',710),(@devops_root,'DevOps.CICD','CI/CD','DevOps',720),(@devops_root,'DevOps.Kubernetes','Kubernetes','DevOps',730),(@devops_root,'DevOps.IaC','IaC','DevOps',740),(@devops_root,'DevOps.Reliability','Надёжность','DevOps',750),(@devops_root,'DevOps.Incident','Инциденты','DevOps',760);
SET @fs_root = (SELECT skill_node_id FROM skill_nodes WHERE code='FullStack' LIMIT 1);
INSERT IGNORE INTO skill_nodes(parent_id,code,name,category,sort_order) VALUES
(@fs_root,'FullStack.Web','Web','Full-stack',810),(@fs_root,'FullStack.Database','Database','Full-stack',820),(@fs_root,'FullStack.State','State','Full-stack',830),(@fs_root,'FullStack.Security','Безопасность','Full-stack',840),(@fs_root,'FullStack.Performance','Производительность','Full-stack',850),(@fs_root,'FullStack.Architecture','Architecture','Full-stack',860);
