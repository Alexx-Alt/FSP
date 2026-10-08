USE fsp_match_v6;

CREATE OR REPLACE VIEW v_city_market AS
SELECT city,
       SUM(entity_type='candidate') AS candidates,
       SUM(entity_type='company') AS companies,
       SUM(entity_type='job') AS open_jobs
FROM (
    SELECT city,'candidate' entity_type FROM users WHERE role='applicant' AND city IS NOT NULL
    UNION ALL SELECT city,'company' FROM companies WHERE city IS NOT NULL
    UNION ALL SELECT location,'job' FROM jobs WHERE status='open' AND location IS NOT NULL
) market
GROUP BY city;

CREATE OR REPLACE VIEW v_case_pipeline AS
SELECT company_id,status,COUNT(*) cases_count,ROUND(AVG(progress),1) avg_progress
FROM interaction_cases
GROUP BY company_id,status;
