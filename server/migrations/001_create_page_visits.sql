-- Page visit counter: one row per day, so the total is SUM(visits) and daily history comes for free.
-- Every migration must be idempotent: they all run on every deploy.

CREATE TABLE IF NOT EXISTS page_visits (
	visit_date DATE NOT NULL PRIMARY KEY,
	visits INT UNSIGNED NOT NULL DEFAULT 0,
	updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- statement-break
DROP PROCEDURE IF EXISTS sp_page_visit_record;

-- statement-break
-- Counts one visit for today and returns the all-time total.
CREATE PROCEDURE sp_page_visit_record()
BEGIN
	INSERT INTO page_visits (visit_date, visits) VALUES (CURRENT_DATE(), 1)
		ON DUPLICATE KEY UPDATE visits = visits + 1;
	SELECT CAST(COALESCE(SUM(visits), 0) AS UNSIGNED) AS total FROM page_visits;
END;

-- statement-break
DROP PROCEDURE IF EXISTS sp_page_visit_total;

-- statement-break
-- Returns the all-time total without counting a visit.
CREATE PROCEDURE sp_page_visit_total()
BEGIN
	SELECT CAST(COALESCE(SUM(visits), 0) AS UNSIGNED) AS total FROM page_visits;
END;
