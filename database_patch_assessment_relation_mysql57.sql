-- Patch relasi template assessment untuk database lama (MySQL 5.7.8+)
-- Aman dijalankan ulang. Jalankan pada database `simptt` sebelum menjalankan seeder.

SET NAMES utf8mb4;
USE `simptt`;

DROP PROCEDURE IF EXISTS `lsp_patch_add_column`;
DELIMITER $$
CREATE PROCEDURE `lsp_patch_add_column`()
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'lsp_assessment_forms'
          AND COLUMN_NAME = 'kdlsp_skema'
    ) THEN
        ALTER TABLE `lsp_assessment_forms`
            ADD COLUMN `kdlsp_skema` BIGINT UNSIGNED NULL AFTER `id`;
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'lsp_assessment_forms'
          AND INDEX_NAME = 'lsp_assessment_forms_kdlsp_skema_index'
    ) THEN
        ALTER TABLE `lsp_assessment_forms`
            ADD INDEX `lsp_assessment_forms_kdlsp_skema_index` (`kdlsp_skema`);
    END IF;
END$$
DELIMITER ;

CALL `lsp_patch_add_column`();
DROP PROCEDURE IF EXISTS `lsp_patch_add_column`;

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
        `kdlsp_skema_unitkompetensi_elemen`,
        `kdlsp_skema_unitkompetensi_elemen_kriteria`
    ),
    KEY `assessment_question_units_unit_index` (`kdlsp_skema_unitkompetensi`),
    KEY `assessment_question_units_element_index` (`kdlsp_skema_unitkompetensi_elemen`),
    KEY `assessment_question_units_criteria_index` (`kdlsp_skema_unitkompetensi_elemen_kriteria`),
    CONSTRAINT `assessment_question_units_question_fk`
        FOREIGN KEY (`question_id`) REFERENCES `lsp_assessment_questions` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ketiga hasil harus bernilai 1.
SELECT
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'lsp_assessment_forms'
          AND COLUMN_NAME = 'kdlsp_skema'
    ) AS `kolom_kdlsp_skema_tersedia`,
    EXISTS(
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'lsp_assessment_form_prodi'
    ) AS `tabel_form_prodi_tersedia`,
    EXISTS(
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'lsp_assessment_question_units'
    ) AS `tabel_question_units_tersedia`;
