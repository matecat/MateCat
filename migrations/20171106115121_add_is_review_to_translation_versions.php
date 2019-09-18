<?php

class AddIsReviewToTranslationVersions extends AbstractMatecatMigration {

    public $sql_up = [
            "ALTER TABLE `segment_translation_versions` ADD COLUMN is_review tinyint(4) not null default 0 ",
            "ALTER TABLE `segment_translation_versions` ADD COLUMN raw_diff TEXT ",
    ] ;

    public $sql_down = [
            "ALTER TABLE `segment_translation_versions` DROP COLUMN is_review",
            "ALTER TABLE `segment_translation_versions` DROP COLUMN raw_diff"
    ];
}
