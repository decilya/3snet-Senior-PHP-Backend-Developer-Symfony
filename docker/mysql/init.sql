-- ============================================================================
-- Инициализация БД: utf8mb4 и таймзона UTC (совпадает с php.ini)
-- ============================================================================
SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- Тестовая БД (Symfony подхватит через DATABASE_URL с ?dbname=app_test)
CREATE DATABASE IF NOT EXISTS app_test
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON app_test.* TO 'app'@'%';
FLUSH PRIVILEGES;