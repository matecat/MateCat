<?php

class MyMemoryUpdate extends AbstractMatecatMigration
{
    public $sql_up = 'UPDATE `engines` SET others = "{\"gloss_get_relative_url\":\"glossary\/get\",\"gloss_set_relative_url\":\"glossary\/set\",\"gloss_update_relative_url\":\"glossary\/update\",\"glossary_import_relative_url\":\"glossary\/import\",\"glossary_export_relative_url\":\"glossary\/export\",\"gloss_delete_relative_url\":\"glossary\/delete\",\"tmx_import_relative_url\":\"tmx\/import\",\"tmx_status_relative_url\":\"tmx\/status\",\"tmx_export_create_url\":\"tmx\/export\/create\",\"tmx_export_check_url\":\"tmx\/export\/check\",\"tmx_export_download_url\":\"tmx\/export\/download\",\"tmx_export_list_url\":\"tmx\/export\/list\",\"tmx_export_email_url\":\"tmx\/export\/create\",\"api_key_create_user_url\":\"createranduser\",\"api_key_check_auth_url\":\"authkey\",\"analyze_url\":\"analyze\",\"detect_language_url\":\"langdetect.php\"}"';

    public $sql_down = 'UPDATE `engines` SET others = "{\"gloss_get_relative_url\":\"glossary\/get\",\"gloss_set_relative_url\":\"glossary\/set\",\"gloss_update_relative_url\":\"glossary\/update\",\"glossary_import_relative_url\":\"glossary\/import\",\"gloss_delete_relative_url\":\"glossary\/delete\",\"tmx_import_relative_url\":\"tmx\/import\",\"tmx_status_relative_url\":\"tmx\/status\",\"tmx_export_create_url\":\"tmx\/export\/create\",\"tmx_export_check_url\":\"tmx\/export\/check\",\"tmx_export_download_url\":\"tmx\/export\/download\",\"tmx_export_list_url\":\"tmx\/export\/list\",\"api_key_create_user_url\":\"createranduser\",\"api_key_check_auth_url\":\"authkey\",\"analyze_url\":\"analyze\",\"detect_language_url\":\"langdetect.php\"}"';

}
