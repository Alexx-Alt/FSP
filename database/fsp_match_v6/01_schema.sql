-- FSP / ИТ-MATCH PRO v6
-- Шаг 2. Полная схема MySQL 8.
USE fsp_match_v6;
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS interaction_alerts;
DROP TABLE IF EXISTS case_events;
DROP TABLE IF EXISTS case_comments;
DROP TABLE IF EXISTS case_tasks;
DROP TABLE IF EXISTS case_participants;
DROP TABLE IF EXISTS interaction_cases;
DROP TABLE IF EXISTS match_results;
DROP TABLE IF EXISTS fsp_achievements;
DROP TABLE IF EXISTS candidate_skill_evidence;
DROP TABLE IF EXISTS skill_nodes;
DROP TABLE IF EXISTS assessment_runs;
DROP TABLE IF EXISTS resumes;
DROP TABLE IF EXISTS jobs;
DROP TABLE IF EXISTS company_members;
DROP TABLE IF EXISTS companies;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(80) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    email_verified_at TIMESTAMP NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('applicant', 'employer', 'admin') NOT NULL DEFAULT 'applicant',
    remember_token VARCHAR(100) NULL,
    two_factor_secret TEXT NULL,
    two_factor_recovery_codes TEXT NULL,
    two_factor_confirmed_at TIMESTAMP NULL,
    headline VARCHAR(180) NULL,
    city VARCHAR(100) NULL,
    bio TEXT NULL,
    skills VARCHAR(600) NULL,
    company VARCHAR(160) NULL,
    primary_category ENUM('Backend', 'Frontend') NULL,
    desired_grade ENUM('intern', 'junior', 'middle', 'senior', 'lead') NULL,
    confirmed_grade ENUM('intern', 'junior', 'middle', 'senior', 'lead') NULL,
    grade_status ENUM('unverified', 'confirmed', 'not_confirmed') NOT NULL DEFAULT 'unverified',
    salary_expected INT UNSIGNED NULL,
    work_format ENUM('office', 'hybrid', 'remote', 'any') NULL DEFAULT 'any',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_users_role (role),
    INDEX idx_users_city (city),
    INDEX idx_users_category_grade (primary_category, confirmed_grade, grade_status)
) ENGINE=InnoDB;

CREATE TABLE companies (
    company_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(180) NOT NULL UNIQUE,
    city VARCHAR(100) NOT NULL,
    industry VARCHAR(140) NULL,
    size_band VARCHAR(60) NULL,
    website VARCHAR(255) NULL,
    description TEXT NULL,
    hiring_quality_score DECIMAL(5,2) NOT NULL DEFAULT 0,
    avg_response_hours DECIMAL(7,2) NOT NULL DEFAULT 0,
    acceptance_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_companies_city (city),
    INDEX idx_companies_quality (hiring_quality_score)
) ENGINE=InnoDB;

CREATE TABLE company_members (
    company_member_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    position_title VARCHAR(160) NULL,
    is_decision_maker BOOLEAN NOT NULL DEFAULT FALSE,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    joined_at DATE NULL,
    UNIQUE KEY uq_company_member (company_id, user_id),
    CONSTRAINT fk_company_members_company FOREIGN KEY (company_id) REFERENCES companies(company_id) ON DELETE CASCADE,
    CONSTRAINT fk_company_members_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE jobs (
    job_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employer_id INT UNSIGNED NOT NULL,
    company_id INT UNSIGNED NULL,
    company VARCHAR(180) NOT NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT NOT NULL,
    location VARCHAR(100) NULL,
    salary_min INT UNSIGNED NULL,
    salary_max INT UNSIGNED NULL,
    skills VARCHAR(600) NULL,
    category ENUM('Backend', 'Frontend') NOT NULL,
    grade_required ENUM('intern', 'junior', 'middle', 'senior', 'lead') NOT NULL,
    work_format ENUM('office', 'hybrid', 'remote', 'any') NOT NULL DEFAULT 'hybrid',
    status ENUM('draft', 'open', 'paused', 'closed') NOT NULL DEFAULT 'open',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_jobs_employer FOREIGN KEY (employer_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_jobs_company FOREIGN KEY (company_id) REFERENCES companies(company_id) ON DELETE SET NULL,
    INDEX idx_jobs_search (status, category, grade_required, location),
    INDEX idx_jobs_company (company_id)
) ENGINE=InnoDB;

CREATE TABLE resumes (
    resume_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT NOT NULL,
    location VARCHAR(100) NULL,
    salary_expected INT UNSIGNED NULL,
    skills VARCHAR(600) NULL,
    experience_years TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_resumes_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_resumes_user (user_id),
    INDEX idx_resumes_location (location)
) ENGINE=InnoDB;

CREATE TABLE assessment_runs (
    assessment_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    candidate_id INT UNSIGNED NOT NULL,
    category ENUM('Backend', 'Frontend') NOT NULL,
    desired_grade ENUM('intern', 'junior', 'middle', 'senior', 'lead') NOT NULL,
    result_grade ENUM('intern', 'junior', 'middle', 'senior', 'lead') NOT NULL,
    score DECIMAL(5,2) NOT NULL,
    integrity_score DECIMAL(5,2) NOT NULL DEFAULT 100,
    variant_key VARCHAR(80) NOT NULL,
    started_at DATETIME NOT NULL,
    completed_at DATETIME NOT NULL,
    retest_of BIGINT UNSIGNED NULL,
    CONSTRAINT fk_assessment_candidate FOREIGN KEY (candidate_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_assessment_retest FOREIGN KEY (retest_of) REFERENCES assessment_runs(assessment_id) ON DELETE SET NULL,
    INDEX idx_assessment_candidate (candidate_id, completed_at),
    INDEX idx_assessment_category_grade (category, result_grade),
    INDEX idx_assessment_variant (variant_key)
) ENGINE=InnoDB;

CREATE TABLE skill_nodes (
    skill_node_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id INT UNSIGNED NULL,
    code VARCHAR(220) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    category ENUM('Backend', 'Frontend') NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    CONSTRAINT fk_skill_parent FOREIGN KEY (parent_id) REFERENCES skill_nodes(skill_node_id) ON DELETE CASCADE,
    INDEX idx_skill_category (category, sort_order)
) ENGINE=InnoDB;

CREATE TABLE candidate_skill_evidence (
    evidence_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    candidate_id INT UNSIGNED NOT NULL,
    skill_node_id INT UNSIGNED NOT NULL,
    state ENUM('confirmed', 'partial', 'claimed', 'unknown') NOT NULL DEFAULT 'unknown',
    source_type ENUM('assessment', 'practical_task', 'fsp_id', 'external', 'self') NOT NULL,
    source_label VARCHAR(180) NOT NULL,
    score DECIMAL(5,2) NULL,
    verified_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_candidate_skill_source (candidate_id, skill_node_id, source_type, source_label),
    CONSTRAINT fk_evidence_candidate FOREIGN KEY (candidate_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_evidence_skill FOREIGN KEY (skill_node_id) REFERENCES skill_nodes(skill_node_id) ON DELETE CASCADE,
    INDEX idx_evidence_candidate_state (candidate_id, state)
) ENGINE=InnoDB;

CREATE TABLE fsp_achievements (
    achievement_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    candidate_id INT UNSIGNED NOT NULL,
    discipline VARCHAR(140) NOT NULL,
    achievement_type VARCHAR(100) NOT NULL,
    title VARCHAR(220) NOT NULL,
    place VARCHAR(140) NULL,
    rank_name VARCHAR(120) NULL,
    team_result VARCHAR(180) NULL,
    relevance_score TINYINT UNSIGNED NOT NULL DEFAULT 50,
    verified BOOLEAN NOT NULL DEFAULT FALSE,
    achieved_at DATE NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_fsp_candidate FOREIGN KEY (candidate_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_fsp_candidate_verified (candidate_id, verified)
) ENGINE=InnoDB;

CREATE TABLE match_results (
    match_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    candidate_id INT UNSIGNED NOT NULL,
    job_id INT UNSIGNED NOT NULL,
    route ENUM('invite', 'clarify', 'perspective', 'observe') NOT NULL,
    match_score DECIMAL(5,2) NOT NULL,
    category_match BOOLEAN NOT NULL,
    grade_factor DECIMAL(4,3) NOT NULL,
    stack_coverage DECIMAL(4,3) NOT NULL,
    salary_overlap BOOLEAN NOT NULL,
    format_match BOOLEAN NOT NULL,
    fsp_boost DECIMAL(5,4) NOT NULL DEFAULT 0,
    reasons_json JSON NULL,
    gaps_json JSON NULL,
    calculated_at DATETIME NOT NULL,
    UNIQUE KEY uq_match_pair (candidate_id, job_id),
    CONSTRAINT fk_match_candidate FOREIGN KEY (candidate_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_match_job FOREIGN KEY (job_id) REFERENCES jobs(job_id) ON DELETE CASCADE,
    INDEX idx_match_route_score (route, match_score)
) ENGINE=InnoDB;

CREATE TABLE interaction_cases (
    case_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    candidate_id INT UNSIGNED NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    job_id INT UNSIGNED NULL,
    owner_user_id INT UNSIGNED NULL,
    status ENUM('new', 'screening', 'task', 'interview', 'offer', 'hired', 'rejected', 'declined', 'paused', 'reopened', 'archived') NOT NULL DEFAULT 'new',
    progress TINYINT UNSIGNED NOT NULL DEFAULT 0,
    priority ENUM('low', 'normal', 'high', 'critical') NOT NULL DEFAULT 'normal',
    source ENUM('match_invite', 'self_application', 'referral', 'reopened', 'manual') NOT NULL DEFAULT 'match_invite',
    duplicate_group VARCHAR(80) NULL,
    reopened_from_case_id BIGINT UNSIGNED NULL,
    last_interaction_at DATETIME NULL,
    next_action_at DATETIME NULL,
    contacts_unlocked_at DATETIME NULL,
    identity_signal ENUM('normal', 'review', 'possible_duplicate', 'low_signal') NOT NULL DEFAULT 'normal',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_case_candidate FOREIGN KEY (candidate_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_case_company FOREIGN KEY (company_id) REFERENCES companies(company_id) ON DELETE CASCADE,
    CONSTRAINT fk_case_job FOREIGN KEY (job_id) REFERENCES jobs(job_id) ON DELETE SET NULL,
    CONSTRAINT fk_case_owner FOREIGN KEY (owner_user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_case_candidate (candidate_id, status),
    INDEX idx_case_company (company_id, status),
    INDEX idx_case_next_action (next_action_at),
    INDEX idx_case_duplicate (duplicate_group)
) ENGINE=InnoDB;

CREATE TABLE case_participants (
    case_participant_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id BIGINT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    role_in_case VARCHAR(100) NOT NULL,
    can_decide BOOLEAN NOT NULL DEFAULT FALSE,
    UNIQUE KEY uq_case_participant (case_id, user_id),
    CONSTRAINT fk_case_participant_case FOREIGN KEY (case_id) REFERENCES interaction_cases(case_id) ON DELETE CASCADE,
    CONSTRAINT fk_case_participant_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE case_tasks (
    task_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT NULL,
    skill_node_id INT UNSIGNED NULL,
    status ENUM('open', 'in_progress', 'done', 'cancelled') NOT NULL DEFAULT 'open',
    due_at DATETIME NULL,
    result_score DECIMAL(5,2) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    CONSTRAINT fk_task_case FOREIGN KEY (case_id) REFERENCES interaction_cases(case_id) ON DELETE CASCADE,
    CONSTRAINT fk_task_skill FOREIGN KEY (skill_node_id) REFERENCES skill_nodes(skill_node_id) ON DELETE SET NULL,
    CONSTRAINT fk_task_creator FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_task_case_status (case_id, status),
    INDEX idx_task_due (due_at)
) ENGINE=InnoDB;

CREATE TABLE case_comments (
    comment_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id BIGINT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NULL,
    body TEXT NOT NULL,
    is_internal BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_comment_case FOREIGN KEY (case_id) REFERENCES interaction_cases(case_id) ON DELETE CASCADE,
    CONSTRAINT fk_comment_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_comment_case_time (case_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE case_events (
    event_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id BIGINT UNSIGNED NOT NULL,
    actor_user_id INT UNSIGNED NULL,
    event_type VARCHAR(80) NOT NULL,
    title VARCHAR(200) NOT NULL,
    details TEXT NULL,
    metadata JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_event_case FOREIGN KEY (case_id) REFERENCES interaction_cases(case_id) ON DELETE CASCADE,
    CONSTRAINT fk_event_actor FOREIGN KEY (actor_user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_event_case_time (case_id, created_at),
    INDEX idx_event_type (event_type)
) ENGINE=InnoDB;

CREATE TABLE interaction_alerts (
    alert_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    case_id BIGINT UNSIGNED NULL,
    type VARCHAR(80) NOT NULL,
    severity ENUM('info', 'warning', 'critical') NOT NULL DEFAULT 'info',
    title VARCHAR(220) NOT NULL,
    is_read BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_alert_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_alert_case FOREIGN KEY (case_id) REFERENCES interaction_cases(case_id) ON DELETE CASCADE,
    INDEX idx_alert_user_read (user_id, is_read),
    INDEX idx_alert_severity (severity, is_read)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;
