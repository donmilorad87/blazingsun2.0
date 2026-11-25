-- Runs once, when the MySQL volume is first created.
-- A separate, disposable database for the PHP integration tests (they drop and recreate tables).
CREATE DATABASE IF NOT EXISTS blazingsun_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON blazingsun_test.* TO 'blazingsun'@'%';

-- Disposable database for the Playwright E2E server (scripts/e2e-server.sh resets it on every run).
CREATE DATABASE IF NOT EXISTS blazingsun_e2e CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON blazingsun_e2e.* TO 'blazingsun'@'%';
