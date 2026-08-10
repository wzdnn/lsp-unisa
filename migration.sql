-- ============================================================================
-- LSP UNISA - Dynamic Assessment Schema
-- Generated: 2026-08-07
-- Schema version: 2026.08.10.5-mysql57
-- Source migrations:
--   2026_08_03_000100_create_dynamic_assessment_tables.php
--   2026_08_07_000200_complete_assessment_mvp_workflow.php
--   2026_08_10_000300_complete_assessment_lifecycle.php
--   2026_08_10_000400_link_assessment_forms_to_scheme_program_and_units.php
-- Target: MySQL 5.7.8+
-- Compatibility notes:
--   - Menggunakan utf8mb4_unicode_ci (tersedia di MySQL 5.7).
--   - Menggunakan tipe JSON native (tersedia mulai MySQL 5.7.8).
--   - Tidak menggunakan CTE, window function, CHECK constraint,
--     invisible index, expression index, atau collation MySQL 8.
--   - Engine seluruh tabel adalah InnoDB agar foreign key berfungsi.
--
-- Jalankan pada database aplikasi LSP/SIMPTT yang sudah memiliki tabel:
--   lsp_user, lsp_periode_skema, lsp_apl01_pengajuan,
--   lsp_skema_unitkompetensi, dan tabel master LSP lainnya.
--
-- Skrip ini hanya membuat tabel baru untuk assessment dinamis.
-- Tidak ada DROP TABLE dan tidak ada perubahan pada data tabel lama.
-- ============================================================================

SET NAMES utf8mb4;
USE `simptt`;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `lsp_assessment_forms` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `kdlsp_skema` BIGINT UNSIGNED NULL,
    `code` VARCHAR(40) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `stage` ENUM('pra_asesmen', 'asesmen', 'pasca_asesmen') NOT NULL,
    `filled_by` ENUM('asesi', 'asesor', 'bersama', 'admin') NOT NULL,
    `reviewed_by` ENUM('asesor', 'admin', 'lead_asesor') NULL,
    `description` TEXT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `lsp_assessment_forms_code_unique` (`code`),
    KEY `lsp_assessment_forms_kdlsp_skema_index` (`kdlsp_skema`),
    KEY `lsp_assessment_forms_stage_index` (`stage`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lsp_assessment_form_prodi` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `form_id` BIGINT UNSIGNED NOT NULL,
    `kdunitkerja` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `assessment_form_prodi_unique` (`form_id`, `kdunitkerja`),
    KEY `assessment_form_prodi_kdunitkerja_index` (`kdunitkerja`),
    CONSTRAINT `assessment_form_prodi_form_fk`
        FOREIGN KEY (`form_id`) REFERENCES `lsp_assessment_forms` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lsp_assessment_form_versions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `form_id` BIGINT UNSIGNED NOT NULL,
    `version` INT UNSIGNED NOT NULL,
    `status` ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',
    `settings` JSON NULL,
    `published_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `assessment_form_versions_form_version_unique` (`form_id`, `version`),
    KEY `assessment_form_versions_status_index` (`status`),
    CONSTRAINT `assessment_form_versions_form_fk`
        FOREIGN KEY (`form_id`) REFERENCES `lsp_assessment_forms` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lsp_assessment_sections` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `form_version_id` BIGINT UNSIGNED NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `assessment_sections_version_order_index` (`form_version_id`, `sort_order`),
    CONSTRAINT `assessment_sections_version_fk`
        FOREIGN KEY (`form_version_id`) REFERENCES `lsp_assessment_form_versions` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lsp_assessment_questions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `section_id` BIGINT UNSIGNED NOT NULL,
    `code` VARCHAR(80) NOT NULL,
    `type` VARCHAR(40) NOT NULL,
    `label` TEXT NOT NULL,
    `instructions` TEXT NULL,
    `is_required` TINYINT(1) NOT NULL DEFAULT 0,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `kdlsp_skema_unitkompetensi` BIGINT UNSIGNED NULL,
    `kdlsp_skema_unitkompetensi_elemen` BIGINT UNSIGNED NULL,
    `kdlsp_skema_unitkompetensi_elemen_kriteria` BIGINT UNSIGNED NULL,
    `options` JSON NULL,
    `settings` JSON NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `assessment_questions_section_code_unique` (`section_id`, `code`),
    KEY `assessment_questions_section_order_index` (`section_id`, `sort_order`),
    KEY `assessment_questions_unit_index` (`kdlsp_skema_unitkompetensi`),
    KEY `assessment_questions_element_index` (`kdlsp_skema_unitkompetensi_elemen`),
    KEY `assessment_questions_criteria_index` (`kdlsp_skema_unitkompetensi_elemen_kriteria`),
    CONSTRAINT `assessment_questions_section_fk`
        FOREIGN KEY (`section_id`) REFERENCES `lsp_assessment_sections` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lsp_assessment_question_units` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `question_id` BIGINT UNSIGNED NOT NULL,
    `kdlsp_skema_unitkompetensi` BIGINT UNSIGNED NOT NULL,
    `kdlsp_skema_unitkompetensi_elemen` BIGINT UNSIGNED NULL,
    `kdlsp_skema_unitkompetensi_elemen_kriteria` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `assessment_question_unit_scope_unique` (
        `question_id`, `kdlsp_skema_unitkompetensi`,
        `kdlsp_skema_unitkompetensi_elemen`, `kdlsp_skema_unitkompetensi_elemen_kriteria`
    ),
    KEY `assessment_question_units_unit_index` (`kdlsp_skema_unitkompetensi`),
    KEY `assessment_question_units_element_index` (`kdlsp_skema_unitkompetensi_elemen`),
    KEY `assessment_question_units_criteria_index` (`kdlsp_skema_unitkompetensi_elemen_kriteria`),
    CONSTRAINT `assessment_question_units_question_fk`
        FOREIGN KEY (`question_id`) REFERENCES `lsp_assessment_questions` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lsp_assessment_processes` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `kdlsp_apl01_pengajuan` BIGINT UNSIGNED NULL,
    `kdlsp_periode_skema` BIGINT UNSIGNED NOT NULL,
    `asesi_id` BIGINT UNSIGNED NOT NULL,
    `assessor_id` BIGINT UNSIGNED NULL,
    `current_stage` VARCHAR(40) NOT NULL DEFAULT 'pra_asesmen',
    `status` ENUM('active', 'completed', 'cancelled') NOT NULL DEFAULT 'active',
    `final_result` ENUM('competent', 'not_competent') NULL,
    `decision_notes` TEXT NULL,
    `decided_by` BIGINT UNSIGNED NULL,
    `decided_at` TIMESTAMP NULL DEFAULT NULL,
    `result_published_at` TIMESTAMP NULL DEFAULT NULL,
    `started_at` TIMESTAMP NULL DEFAULT NULL,
    `completed_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `assessment_process_apl01_unique` (`kdlsp_apl01_pengajuan`),
    KEY `assessment_process_period_scheme_index` (`kdlsp_periode_skema`),
    KEY `assessment_process_asesi_index` (`asesi_id`),
    KEY `assessment_process_assessor_index` (`assessor_id`),
    KEY `assessment_process_decided_by_index` (`decided_by`),
    KEY `assessment_process_stage_status_index` (`current_stage`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lsp_assessment_assignments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `process_id` BIGINT UNSIGNED NOT NULL,
    `form_version_id` BIGINT UNSIGNED NOT NULL,
    `assigned_to` BIGINT UNSIGNED NOT NULL,
    `assignee_role` ENUM('asesi', 'asesor', 'admin') NOT NULL,
    `status` ENUM(
        'assigned',
        'draft',
        'submitted',
        'under_review',
        'revision_required',
        'assessed',
        'result_published',
        'completed'
    ) NOT NULL DEFAULT 'assigned',
    `revision_notes` TEXT NULL,
    `due_at` TIMESTAMP NULL DEFAULT NULL,
    `submitted_at` TIMESTAMP NULL DEFAULT NULL,
    `reviewed_at` TIMESTAMP NULL DEFAULT NULL,
    `revision_requested_at` TIMESTAMP NULL DEFAULT NULL,
    `completed_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `assessment_assignment_unique` (`process_id`, `form_version_id`, `assigned_to`),
    KEY `assessment_assignments_assigned_to_index` (`assigned_to`),
    KEY `assessment_assignments_status_index` (`status`),
    KEY `assessment_assignments_due_at_index` (`due_at`),
    KEY `assessment_assignments_form_version_index` (`form_version_id`),
    CONSTRAINT `assessment_assignments_process_fk`
        FOREIGN KEY (`process_id`) REFERENCES `lsp_assessment_processes` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `assessment_assignments_form_version_fk`
        FOREIGN KEY (`form_version_id`) REFERENCES `lsp_assessment_form_versions` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lsp_assessment_answers` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `assignment_id` BIGINT UNSIGNED NOT NULL,
    `question_id` BIGINT UNSIGNED NOT NULL,
    `answer_text` LONGTEXT NULL,
    `answer_json` JSON NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `assessment_answers_assignment_question_unique` (`assignment_id`, `question_id`),
    KEY `assessment_answers_question_index` (`question_id`),
    CONSTRAINT `assessment_answers_assignment_fk`
        FOREIGN KEY (`assignment_id`) REFERENCES `lsp_assessment_assignments` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `assessment_answers_question_fk`
        FOREIGN KEY (`question_id`) REFERENCES `lsp_assessment_questions` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lsp_assessment_evidences` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `answer_id` BIGINT UNSIGNED NOT NULL,
    `disk` VARCHAR(30) NOT NULL DEFAULT 'public',
    `path` VARCHAR(255) NOT NULL,
    `original_name` VARCHAR(255) NOT NULL,
    `mime_type` VARCHAR(100) NULL,
    `size` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `assessment_evidences_answer_index` (`answer_id`),
    CONSTRAINT `assessment_evidences_answer_fk`
        FOREIGN KEY (`answer_id`) REFERENCES `lsp_assessment_answers` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lsp_assessment_reviews` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `assignment_id` BIGINT UNSIGNED NOT NULL,
    `question_id` BIGINT UNSIGNED NOT NULL,
    `assessor_id` BIGINT UNSIGNED NOT NULL,
    `result` ENUM('achieved', 'not_achieved', 'needs_follow_up', 'not_assessed') NOT NULL,
    `notes` TEXT NULL,
    `reviewed_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `assessment_reviews_unique` (`assignment_id`, `question_id`, `assessor_id`),
    KEY `assessment_reviews_question_index` (`question_id`),
    KEY `assessment_reviews_assessor_index` (`assessor_id`),
    KEY `assessment_reviews_result_index` (`result`),
    CONSTRAINT `assessment_reviews_assignment_fk`
        FOREIGN KEY (`assignment_id`) REFERENCES `lsp_assessment_assignments` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `assessment_reviews_question_fk`
        FOREIGN KEY (`question_id`) REFERENCES `lsp_assessment_questions` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lsp_assessment_decisions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `assignment_id` BIGINT UNSIGNED NOT NULL,
    `assessor_id` BIGINT UNSIGNED NOT NULL,
    `result` ENUM('competent', 'not_competent') NOT NULL,
    `notes` TEXT NULL,
    `is_published` TINYINT(1) NOT NULL DEFAULT 0,
    `decided_at` TIMESTAMP NOT NULL,
    `published_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `assessment_decisions_assignment_unique` (`assignment_id`),
    KEY `assessment_decisions_assessor_index` (`assessor_id`),
    KEY `assessment_decisions_result_index` (`result`),
    CONSTRAINT `assessment_decisions_assignment_fk`
        FOREIGN KEY (`assignment_id`) REFERENCES `lsp_assessment_assignments` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Upgrade idempotent untuk server yang sudah pernah menjalankan versi sebelumnya.
-- Stored procedure sementara dipakai karena MySQL 5.7 belum mendukung
-- ADD COLUMN/INDEX IF NOT EXISTS.
DROP PROCEDURE IF EXISTS `lsp_add_column_if_missing`;
DELIMITER $$
CREATE PROCEDURE `lsp_add_column_if_missing`(
    IN p_table VARCHAR(64),
    IN p_column VARCHAR(64),
    IN p_definition TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND COLUMN_NAME = p_column
    ) THEN
        SET @ddl = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `', p_column, '` ', p_definition);
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

CALL `lsp_add_column_if_missing`('lsp_assessment_processes', 'final_result', 'ENUM(''competent'',''not_competent'') NULL AFTER `status`');
CALL `lsp_add_column_if_missing`('lsp_assessment_processes', 'decision_notes', 'TEXT NULL AFTER `final_result`');
CALL `lsp_add_column_if_missing`('lsp_assessment_processes', 'decided_by', 'BIGINT UNSIGNED NULL AFTER `decision_notes`');
CALL `lsp_add_column_if_missing`('lsp_assessment_processes', 'decided_at', 'TIMESTAMP NULL DEFAULT NULL AFTER `decided_by`');
CALL `lsp_add_column_if_missing`('lsp_assessment_processes', 'result_published_at', 'TIMESTAMP NULL DEFAULT NULL AFTER `decided_at`');
CALL `lsp_add_column_if_missing`('lsp_assessment_forms', 'kdlsp_skema', 'BIGINT UNSIGNED NULL AFTER `id`');
DROP PROCEDURE IF EXISTS `lsp_add_column_if_missing`;

DROP PROCEDURE IF EXISTS `lsp_add_index_if_missing`;
DELIMITER $$
CREATE PROCEDURE `lsp_add_index_if_missing`(
    IN p_table VARCHAR(64),
    IN p_index VARCHAR(64),
    IN p_columns VARCHAR(255)
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND INDEX_NAME = p_index
    ) THEN
        SET @ddl = CONCAT('ALTER TABLE `', p_table, '` ADD INDEX `', p_index, '` (', p_columns, ')');
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

CALL `lsp_add_index_if_missing`('lsp_assessment_processes', 'assessment_process_decided_by_index', '`decided_by`');
CALL `lsp_add_index_if_missing`('lsp_assessment_forms', 'lsp_assessment_forms_kdlsp_skema_index', '`kdlsp_skema`');
DROP PROCEDURE IF EXISTS `lsp_add_index_if_missing`;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- Verifikasi setelah eksekusi
-- ============================================================================
SELECT
    TABLE_NAME,
    TABLE_ROWS,
    ENGINE,
    TABLE_COLLATION
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
      'lsp_assessment_forms',
      'lsp_assessment_form_versions',
      'lsp_assessment_form_prodi',
      'lsp_assessment_sections',
      'lsp_assessment_questions',
      'lsp_assessment_question_units',
      'lsp_assessment_processes',
      'lsp_assessment_assignments',
      'lsp_assessment_answers',
      'lsp_assessment_evidences',
      'lsp_assessment_reviews',
      'lsp_assessment_decisions'
  )
ORDER BY TABLE_NAME;
