ALTER TABLE `matecat_local`.`jobs`
ADD COLUMN `new_words` FLOAT(10,2) NOT NULL DEFAULT 0 AFTER `completed`,
ADD COLUMN `draft_words` FLOAT(10,2) NOT NULL DEFAULT 0 AFTER `new_words`,
ADD COLUMN `translated_words` FLOAT(10,2) NOT NULL DEFAULT 0 AFTER `draft_words`,
ADD COLUMN `approved_words` FLOAT(10,2) NOT NULL DEFAULT 0 AFTER `translated_words`,
ADD COLUMN `rejected_words` FLOAT(10,2) NOT NULL DEFAULT 0 AFTER `approved_words`;




