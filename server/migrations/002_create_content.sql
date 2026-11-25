-- Collation: tables AND every string procedure parameter are declared utf8mb4_unicode_ci explicitly. Parameters
-- otherwise take the database default, which differs between hosts (MySQL 8 vs MariaDB) and breaks "=" comparisons.
-- CMS content behind /adminportal: languages, the fixed page sections, their items, translated values and uploads.
-- Every migration must be idempotent: they all run on every deploy. Content itself is seeded once by
-- ContentSeeder (guarded by site_meta.content_seeded), never here, so deleted items don't come back.

CREATE TABLE IF NOT EXISTS site_meta (
	meta_key VARCHAR(64) NOT NULL PRIMARY KEY,
	meta_value VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- statement-break
-- One uploaded (or seeded) image. path is the URL prefix; variants lists the generated files:
-- raster/animated: {"webp":[24,48],"fallback":"png","fallbackWidths":[24,48]} → {path}-{width}.{format}
-- svg:             {"svg":true}                                                → {path}.svg
CREATE TABLE IF NOT EXISTS media (
	id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
	kind VARCHAR(16) NOT NULL,
	path VARCHAR(255) NOT NULL,
	variants JSON NOT NULL,
	width INT UNSIGNED NOT NULL,
	height INT UNSIGNED NOT NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- statement-break
CREATE TABLE IF NOT EXISTS languages (
	iso2 CHAR(2) NOT NULL PRIMARY KEY,
	iso3 CHAR(3) NOT NULL,
	name VARCHAR(64) NOT NULL,
	flag_media_id INT UNSIGNED NULL,
	is_main TINYINT(1) NOT NULL DEFAULT 0,
	sort_order INT NOT NULL DEFAULT 0,
	UNIQUE KEY languages_iso3 (iso3),
	CONSTRAINT languages_flag FOREIGN KEY (flag_media_id) REFERENCES media (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- statement-break
-- The page's sections are fixed (they can be edited, not added or removed).
CREATE TABLE IF NOT EXISTS sections (
	slug VARCHAR(32) NOT NULL PRIMARY KEY,
	sort_order INT NOT NULL,
	in_nav TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- statement-break
-- INSERT IGNORE: a later deploy never resets the menu visibility chosen in the admin.
INSERT IGNORE INTO sections (slug, sort_order, in_nav) VALUES
	('hero', 0, 0),
	('about', 1, 1),
	('stack', 2, 1),
	('experience', 3, 1),
	('projects', 4, 1),
	('cv', 5, 0),
	('contact', 6, 1);

-- statement-break
-- A repeatable entry in a section (a card, a job, a contact link…). Its fields live in content_values (scope "item:{id}").
CREATE TABLE IF NOT EXISTS items (
	id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
	section_slug VARCHAR(32) NOT NULL,
	item_group VARCHAR(32) NOT NULL,
	sort_order INT NOT NULL DEFAULT 0,
	KEY items_order (section_slug, item_group, sort_order),
	CONSTRAINT items_section FOREIGN KEY (section_slug) REFERENCES sections (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- statement-break
-- Every editable value. scope: "section:{slug}", "item:{id}", "header", "footer" or "labels".
-- lang is the language's iso2, or '' for values that are not translated (URLs, numbers, image ids).
CREATE TABLE IF NOT EXISTS content_values (
	scope VARCHAR(64) NOT NULL,
	field VARCHAR(64) NOT NULL,
	lang VARCHAR(2) NOT NULL DEFAULT '',
	value TEXT NOT NULL,
	PRIMARY KEY (scope, field, lang)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- statement-break
-- Failed admin logins, for rate limiting by IP.
CREATE TABLE IF NOT EXISTS login_failures (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
	ip VARCHAR(45) NOT NULL,
	failed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	KEY login_failures_ip (ip, failed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- statement-break
DROP PROCEDURE IF EXISTS sp_meta_get;

-- statement-break
CREATE PROCEDURE sp_meta_get(IN p_key VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci)
BEGIN
	SELECT meta_value FROM site_meta WHERE meta_key = p_key;
END;

-- statement-break
DROP PROCEDURE IF EXISTS sp_meta_set;

-- statement-break
CREATE PROCEDURE sp_meta_set(IN p_key VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci, IN p_value VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci)
BEGIN
	INSERT INTO site_meta (meta_key, meta_value) VALUES (p_key, p_value)
		ON DUPLICATE KEY UPDATE meta_value = p_value;
END;

-- statement-break
DROP PROCEDURE IF EXISTS sp_media_create;

-- statement-break
-- Returns the new media id.
CREATE PROCEDURE sp_media_create(IN p_kind VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci, IN p_path VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci, IN p_variants JSON, IN p_width INT UNSIGNED, IN p_height INT UNSIGNED)
BEGIN
	INSERT INTO media (kind, path, variants, width, height) VALUES (p_kind, p_path, p_variants, p_width, p_height);
	SELECT LAST_INSERT_ID() AS id;
END;

-- statement-break
DROP PROCEDURE IF EXISTS sp_media_list;

-- statement-break
CREATE PROCEDURE sp_media_list()
BEGIN
	SELECT id, kind, path, variants, width, height FROM media ORDER BY id;
END;

-- statement-break
DROP PROCEDURE IF EXISTS sp_language_list;

-- statement-break
CREATE PROCEDURE sp_language_list()
BEGIN
	SELECT iso2, iso3, name, flag_media_id, is_main, sort_order FROM languages ORDER BY sort_order, iso2;
END;

-- statement-break
DROP PROCEDURE IF EXISTS sp_language_save;

-- statement-break
-- Adds a language (at the end of the list; the first one ever becomes main) or updates its iso3/name/flag.
-- iso2 is the key and never changes. A duplicate iso3 fails with SQLSTATE 23000.
CREATE PROCEDURE sp_language_save(IN p_iso2 CHAR(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci, IN p_iso3 CHAR(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci, IN p_name VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci, IN p_flag_media_id INT UNSIGNED)
BEGIN
	IF EXISTS (SELECT 1 FROM languages WHERE iso2 = p_iso2) THEN
		UPDATE languages SET iso3 = p_iso3, name = p_name, flag_media_id = p_flag_media_id WHERE iso2 = p_iso2;
	ELSE
		INSERT INTO languages (iso2, iso3, name, flag_media_id, is_main, sort_order)
			SELECT p_iso2, p_iso3, p_name, p_flag_media_id, COUNT(*) = 0, COALESCE(MAX(sort_order), 0) + 1 FROM languages;
	END IF;
END;

-- statement-break
DROP PROCEDURE IF EXISTS sp_language_delete;

-- statement-break
-- Deletes a language and all its translations. The main language can't be deleted.
CREATE PROCEDURE sp_language_delete(IN p_iso2 CHAR(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci)
BEGIN
	IF EXISTS (SELECT 1 FROM languages WHERE iso2 = p_iso2 AND is_main = 1) THEN
		SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The main language cannot be deleted';
	END IF;
	DELETE FROM content_values WHERE lang = p_iso2;
	DELETE FROM languages WHERE iso2 = p_iso2;
END;

-- statement-break
DROP PROCEDURE IF EXISTS sp_language_set_main;

-- statement-break
CREATE PROCEDURE sp_language_set_main(IN p_iso2 CHAR(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci)
BEGIN
	IF NOT EXISTS (SELECT 1 FROM languages WHERE iso2 = p_iso2) THEN
		SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown language';
	END IF;
	UPDATE languages SET is_main = (iso2 = p_iso2);
END;

-- statement-break
DROP PROCEDURE IF EXISTS sp_section_list;

-- statement-break
CREATE PROCEDURE sp_section_list()
BEGIN
	SELECT slug, sort_order, in_nav FROM sections ORDER BY sort_order;
END;

-- statement-break
DROP PROCEDURE IF EXISTS sp_section_set_nav;

-- statement-break
CREATE PROCEDURE sp_section_set_nav(IN p_slug VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci, IN p_in_nav TINYINT(1))
BEGIN
	UPDATE sections SET in_nav = p_in_nav WHERE slug = p_slug;
END;

-- statement-break
DROP PROCEDURE IF EXISTS sp_item_list;

-- statement-break
CREATE PROCEDURE sp_item_list()
BEGIN
	SELECT id, section_slug, item_group, sort_order FROM items ORDER BY section_slug, item_group, sort_order, id;
END;

-- statement-break
DROP PROCEDURE IF EXISTS sp_item_create;

-- statement-break
-- Returns the new item id.
CREATE PROCEDURE sp_item_create(IN p_section VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci, IN p_group VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci, IN p_sort_order INT)
BEGIN
	INSERT INTO items (section_slug, item_group, sort_order) VALUES (p_section, p_group, p_sort_order);
	SELECT LAST_INSERT_ID() AS id;
END;

-- statement-break
DROP PROCEDURE IF EXISTS sp_item_set_order;

-- statement-break
CREATE PROCEDURE sp_item_set_order(IN p_id INT UNSIGNED, IN p_sort_order INT)
BEGIN
	UPDATE items SET sort_order = p_sort_order WHERE id = p_id;
END;

-- statement-break
DROP PROCEDURE IF EXISTS sp_item_delete;

-- statement-break
-- Deletes an item and its values.
CREATE PROCEDURE sp_item_delete(IN p_id INT UNSIGNED)
BEGIN
	DELETE FROM content_values WHERE scope = CONCAT('item:', p_id);
	DELETE FROM items WHERE id = p_id;
END;

-- statement-break
DROP PROCEDURE IF EXISTS sp_value_list;

-- statement-break
-- All values at once: the whole site is a few hundred rows, and the page renderer needs every one of them.
CREATE PROCEDURE sp_value_list()
BEGIN
	SELECT scope, field, lang, value FROM content_values;
END;

-- statement-break
DROP PROCEDURE IF EXISTS sp_value_set;

-- statement-break
-- Saves one value; an empty value deletes it (so the main language is used as the fallback).
CREATE PROCEDURE sp_value_set(IN p_scope VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci, IN p_field VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci, IN p_lang VARCHAR(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci, IN p_value TEXT)
BEGIN
	IF p_value = '' THEN
		DELETE FROM content_values WHERE scope = p_scope AND field = p_field AND lang = p_lang;
	ELSE
		INSERT INTO content_values (scope, field, lang, value) VALUES (p_scope, p_field, p_lang, p_value)
			ON DUPLICATE KEY UPDATE value = p_value;
	END IF;
END;

-- statement-break
DROP PROCEDURE IF EXISTS sp_login_failure_count;

-- statement-break
-- Failed logins from this IP within the last p_window_seconds.
CREATE PROCEDURE sp_login_failure_count(IN p_ip VARCHAR(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci, IN p_window_seconds INT)
BEGIN
	SELECT COUNT(*) AS failures FROM login_failures
		WHERE ip = p_ip AND failed_at > NOW() - INTERVAL p_window_seconds SECOND;
END;

-- statement-break
DROP PROCEDURE IF EXISTS sp_login_failure_record;

-- statement-break
-- Records a failed login and prunes entries older than a day.
CREATE PROCEDURE sp_login_failure_record(IN p_ip VARCHAR(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci)
BEGIN
	INSERT INTO login_failures (ip) VALUES (p_ip);
	DELETE FROM login_failures WHERE failed_at < NOW() - INTERVAL 1 DAY;
END;

-- statement-break
DROP PROCEDURE IF EXISTS sp_login_failure_clear;

-- statement-break
CREATE PROCEDURE sp_login_failure_clear(IN p_ip VARCHAR(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci)
BEGIN
	DELETE FROM login_failures WHERE ip = p_ip;
END;
