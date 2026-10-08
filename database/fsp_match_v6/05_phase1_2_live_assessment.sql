-- FSP / ИТ-MATCH PRO v6 — Phase 1.2: live assessment, proctoring signals and code tasks
USE fsp_match_v6;
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS assessment_live_sessions (
    live_session_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    candidate_id INT UNSIGNED NOT NULL,
    category ENUM('Backend','Frontend','Data','ML/AI','DevOps','Full-stack') NOT NULL,
    desired_grade ENUM('intern','junior','middle','senior','lead') NOT NULL,
    status ENUM('created','active','completed','abandoned') NOT NULL DEFAULT 'created',
    variant_key VARCHAR(100) NOT NULL,
    current_question SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    risk_score SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_live_assessment_candidate FOREIGN KEY (candidate_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_live_candidate_status (candidate_id, status),
    INDEX idx_live_created (created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS assessment_live_events (
    live_event_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    live_session_id BIGINT UNSIGNED NOT NULL,
    candidate_id INT UNSIGNED NOT NULL,
    event_type VARCHAR(80) NOT NULL,
    weight TINYINT UNSIGNED NOT NULL DEFAULT 1,
    metadata JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_live_event_session FOREIGN KEY (live_session_id) REFERENCES assessment_live_sessions(live_session_id) ON DELETE CASCADE,
    CONSTRAINT fk_live_event_candidate FOREIGN KEY (candidate_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_live_event_session (live_session_id, created_at),
    INDEX idx_live_event_type (event_type)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS assessment_code_questions (
    code_question_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category ENUM('Backend','Frontend','Data','ML/AI','DevOps','Full-stack') NOT NULL,
    skill_code VARCHAR(120) NOT NULL,
    title VARCHAR(180) NOT NULL,
    prompt TEXT NOT NULL,
    language VARCHAR(40) NOT NULL DEFAULT 'text',
    starter_code TEXT NULL,
    validator_json JSON NOT NULL,
    difficulty TINYINT UNSIGNED NOT NULL DEFAULT 3,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_code_question_pick (category, active, difficulty)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS assessment_code_answers (
    code_answer_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    assessment_id BIGINT UNSIGNED NOT NULL,
    code_question_id INT UNSIGNED NOT NULL,
    candidate_id INT UNSIGNED NOT NULL,
    answer_text MEDIUMTEXT NOT NULL,
    normalized_hash CHAR(64) NOT NULL,
    score DECIMAL(5,2) NOT NULL DEFAULT 0,
    similarity_score DECIMAL(5,2) NOT NULL DEFAULT 0,
    response_ms INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_code_answer_assessment FOREIGN KEY (assessment_id) REFERENCES assessment_runs(assessment_id) ON DELETE CASCADE,
    CONSTRAINT fk_code_answer_question FOREIGN KEY (code_question_id) REFERENCES assessment_code_questions(code_question_id) ON DELETE RESTRICT,
    CONSTRAINT fk_code_answer_candidate FOREIGN KEY (candidate_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_code_answer_question (code_question_id),
    INDEX idx_code_answer_candidate (candidate_id, created_at)
) ENGINE=InnoDB;

DELETE FROM assessment_code_questions WHERE title LIKE 'Phase 1.2:%';
INSERT INTO assessment_code_questions(category,skill_code,title,prompt,language,starter_code,validator_json,difficulty,active) VALUES
('Backend','Idempotency','Phase 1.2: dedupeLatest','Напишите функцию dedupeLatest(items), которая за O(n) оставляет для каждого id только объект с самым свежим updated_at. Достаточно понятного PHP-решения.','php','function dedupeLatest(array $items): array {\n    // your code\n}','{"min_chars":80,"required_groups":[["foreach"],["updated_at"],["id"],["return"]]}',3,1),
('Frontend','TypeScript','Phase 1.2: groupBy','Реализуйте TypeScript-функцию groupBy<T>(items, key), возвращающую Record<string, T[]> без мутации входного массива.','typescript','function groupBy<T>(items: T[], key: (item: T) => string): Record<string, T[]> {\n  // your code\n}','{"min_chars":90,"required_groups":[["Record"],["forEach","reduce","for ("],["key("],["return"]]}',3,1),
('Data','SQL','Phase 1.2: latest row','Напишите SQL-запрос, который оставляет только последнюю запись по customer_id, используя ROW_NUMBER() и created_at.','sql','WITH ranked AS (\n  SELECT\n)','{"min_chars":70,"required_groups":[["row_number"],["partition by"],["customer_id"],["order by"],["created_at"]]}',3,1),
('ML/AI','Validation','Phase 1.2: temporal split','Опишите псевдокод временного train/validation/test split так, чтобы будущее не попадало в train.','python','# timestamps are already parsed\n','{"min_chars":100,"required_groups":[["train"],["validation","val"],["test"],["time","date","timestamp"]]}',3,1),
('DevOps','CI/CD','Phase 1.2: safe deploy','Опишите YAML/псевдокод безопасного pipeline: build → tests → publish → deploy → health-check → rollback при ошибке.','yaml','steps:\n','{"min_chars":100,"required_groups":[["build"],["test"],["deploy"],["health","check"],["rollback"]]}',3,1),
('Full-stack','Architecture','Phase 1.2: idempotent API','Напишите псевдокод обработчика POST /payments с idempotency key и защитой от двойного списания.','typescript','async function createPayment(req) {\n','{"min_chars":110,"required_groups":[["idempot"],["key"],["payment"],["return"],["transaction","lock","unique"]]}',4,1);
