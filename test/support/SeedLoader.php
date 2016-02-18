<?php

// This class is a quick hack, to be improved and expanded
//
class SeedLoader {
    public $database;

    function __construct( $database ) {
        $this->database = $database;
    }

    public function getSeedSql() {
        $out = $this->getEnginesSql();

        return $out;
    }

    public function getEnginesSql() {
        return <<<EOF

        INSERT INTO `engines` (
  `name` ,
  `type` ,
  `description` ,
  `base_url` ,
  `translate_relative_url` ,
  `contribute_relative_url`,
  `delete_relative_url` ,
  `others` ,
  `class_load`,
  `extra_parameters` ,
  `google_api_compliant_version` ,
  `penalty` ,
  `active` ,
  `uid`
)
VALUES
('NONE','NONE','No MT','','',NULL,NULL,'{}','NONE','',NULL,100,0,NULL),
(
'MyMemory (All Pairs)',
'TM',
'Machine translation from Google Translate and Microsoft Translator.',
'http://api.mymemory.translated.net',
'get',
'set',
'delete',
'{\"gloss_get_relative_url\":\"glossary\/get\",\"gloss_set_relative_url\":\"glossary\/set\",\"gloss_update_relative_url\":\"glossary\/update\",\"gloss_delete_relative_url\":\"glossary\/delete\",\"tmx_import_relative_url\":\"tmx\/import\",\"tmx_status_relative_url\":\"tmx\/status\",\"tmx_export_create_url\":\"tmx\/export\/create\",\"tmx_export_check_url\":\"tmx\/export\/check\",\"tmx_export_download_url\":\"tmx\/export\/download\",\"tmx_export_list_url\":\"tmx\/export\/list\",\"api_key_create_user_url\":\"createranduser\",\"api_key_check_auth_url\":\"authkey\",\"analyze_url\":\"analyze\",\"detect_language_url\":\"langdetect.php\"}',
'MyMemory',
'{}',
'1',
0,
1,
NULL);

UPDATE engines SET id = 0 WHERE name = 'NONE' ;
UPDATE engines SET id = 1 WHERE name = 'MyMemory (All Pairs)' ;

EOF;

    }

    function loadEngines() {
        $this->database->execSql( $this->getSeedSql() );
    }

}
