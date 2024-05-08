<?php

class MigrateEngines extends AbstractMatecatMigration {

    public $sql_up = [ "
        UPDATE engines SET `name` = 'ModernMT Lite', `description` = 'Smart machine translation that learns from your corrections for enhanced quality and productivity thanks to ModernMT’s basic features. To unlock all features, <a href=\"https://www.modernmt.com/pricing#translators\">click here</a>.'  WHERE id = 1;
        UPDATE engines SET `name` = 'ModernMT Full', `description` = 'ModernMT for subscribers, includes adaptive suggestions for entire documents, integrated glossary support and TM usage optimization.'  WHERE id > 1 and `class_load` = 'MMT';
    " ];

    public $sql_down = [ "
        UPDATE engines SET `name` = 'MyMemory', `description` = 'Machine translation by the MT engine best suited to your project'  WHERE id = 1;
        UPDATE engines SET `name` = 'ModernMT', `description` = 'ModernMT - Adaptive Neural Machine Translation.'  WHERE id > 1 and class_load = 'MMT';
    "];
}