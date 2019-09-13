<?php

class LanguageStatsAddSigma extends AbstractMatecatMigration
{
    public $sql_up = <<<EOF
ALTER TABLE language_stats
ADD COLUMN pee_sigma INTEGER default 0;
EOF;

    public $sql_down = 'ALTER TABLE language_stats DROP column pee_sigma';
}
