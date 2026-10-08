-- FSP / ИТ-MATCH PRO v6
-- Mega update: privacy / consent / FSP-ID link / company trust.
-- Выполнять ОДИН РАЗ после 05_phase1_2_live_assessment.sql.
USE fsp_match_v6;
SET NAMES utf8mb4;

ALTER TABLE users
    ADD COLUMN profile_visibility ENUM('employers','private') NOT NULL DEFAULT 'employers' AFTER work_format,
    ADD COLUMN consent_processing BOOLEAN NOT NULL DEFAULT TRUE AFTER profile_visibility,
    ADD COLUMN consent_publication BOOLEAN NOT NULL DEFAULT TRUE AFTER consent_processing,
    ADD COLUMN fsp_id_external VARCHAR(120) NULL AFTER consent_publication,
    ADD COLUMN fsp_id_linked BOOLEAN NOT NULL DEFAULT FALSE AFTER fsp_id_external;

ALTER TABLE companies
    ADD COLUMN verification_status ENUM('unverified','pending','verified','rejected') NOT NULL DEFAULT 'verified' AFTER description,
    ADD COLUMN trust_score DECIMAL(5,2) NOT NULL DEFAULT 90 AFTER verification_status;

CREATE INDEX idx_users_visibility ON users (role, profile_visibility, consent_publication);
CREATE INDEX idx_companies_trust ON companies (verification_status, trust_score);

-- Demo records are intentionally marked verified so the existing seeded flow remains usable.
UPDATE users
SET consent_processing = TRUE,
    consent_publication = TRUE,
    profile_visibility = 'employers'
WHERE role = 'applicant';

UPDATE companies
SET verification_status = 'verified',
    trust_score = GREATEST(trust_score, 80);
