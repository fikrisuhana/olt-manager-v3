-- Add use_acs column to olts table
ALTER TABLE `olts` ADD COLUMN `use_acs` TINYINT(1) NOT NULL DEFAULT 1 AFTER `acs_url`;
