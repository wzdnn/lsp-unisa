-- HOTFIX langsung untuk database Laravel: simptt (MySQL 5.7)
-- Jalankan seluruh file ini pada server 157.66.9.185.

USE `simptt`;

-- Pemeriksaan sebelum perubahan. Nilainya saat ini diperkirakan 0.
SELECT COUNT(*) AS `sebelum`
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'simptt'
  AND TABLE_NAME = 'lsp_assessment_forms'
  AND COLUMN_NAME = 'kdlsp_skema';

DROP PROCEDURE IF EXISTS `simptt`.`lsp_hotfix_assessment_form_scheme`;
DELIMITER $$
CREATE PROCEDURE `simptt`.`lsp_hotfix_assessment_form_scheme`()
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = 'simptt'
          AND TABLE_NAME = 'lsp_assessment_forms'
          AND COLUMN_NAME = 'kdlsp_skema'
    ) THEN
        ALTER TABLE `simptt`.`lsp_assessment_forms`
            ADD COLUMN `kdlsp_skema` BIGINT UNSIGNED NULL AFTER `id`;
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = 'simptt'
          AND TABLE_NAME = 'lsp_assessment_forms'
          AND INDEX_NAME = 'lsp_assessment_forms_kdlsp_skema_index'
    ) THEN
        ALTER TABLE `simptt`.`lsp_assessment_forms`
            ADD INDEX `lsp_assessment_forms_kdlsp_skema_index` (`kdlsp_skema`);
    END IF;
END$$
DELIMITER ;

CALL `simptt`.`lsp_hotfix_assessment_form_scheme`();
DROP PROCEDURE IF EXISTS `simptt`.`lsp_hotfix_assessment_form_scheme`;

-- Verifikasi akhir: harus mengembalikan tepat satu baris bernama kdlsp_skema.
SHOW COLUMNS FROM `simptt`.`lsp_assessment_forms` LIKE 'kdlsp_skema';

-- Verifikasi koneksi/schema untuk dibandingkan dengan konfigurasi Laravel.
SELECT DATABASE() AS `database_aktif`, @@hostname AS `server_mysql`;
