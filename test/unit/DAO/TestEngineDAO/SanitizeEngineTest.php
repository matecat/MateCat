<?php

/**
 * @group  regression
 * @covers EnginesModel_EngineDAO::sanitize
 * User: dinies
 * Date: 14/04/16
 * Time: 20.28
 */
class SanitizeEngineTest extends AbstractTest {
    /**
     * @var EnginesModel_EngineDAO
     */
    protected $engine_Dao;
    protected $engine_struct_input;
    protected $engine_struct_expected;

    public function setUp() {
        parent::setUp();
        $this->engine_Dao             = new EnginesModel_EngineDAO( Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE ) );
        $this->engine_struct_input    = new EnginesModel_EngineStruct();
        $this->engine_struct_expected = new EnginesModel_EngineStruct();

    }

    /**
     * It sanitizes the field 'name'.
     * @group  regression
     * @covers EnginesModel_EngineDAO::sanitize
     */
    public function test_sanitize_name_field() {
        $this->engine_struct_input->name    = <<<LABEL
ba""r/foo'
LABEL;
        $this->engine_struct_expected->name = <<<LABEL
ba""r/foo'
LABEL;

        $this->engine_struct_expected->others           = "{}";
        $this->engine_struct_expected->extra_parameters = "{}";
        $this->engine_Dao->sanitize( $this->engine_struct_input );
        $this->assertEquals( $this->engine_struct_expected, $this->engine_struct_input );
    }

    /**
     * It sanitizes the field 'description'.
     * @group  regression
     * @covers EnginesModel_EngineDAO::sanitize
     */
    public function test_sanitize_description_field() {
        $this->engine_struct_input->description         = <<<LABEL
ba""r/foo'
LABEL;
        $this->engine_struct_expected->description      = <<<LABEL
ba""r/foo'
LABEL;
        $this->engine_struct_expected->others           = "{}";
        $this->engine_struct_expected->extra_parameters = "{}";
        $this->engine_Dao->sanitize( $this->engine_struct_input );
        $this->assertEquals( $this->engine_struct_expected, $this->engine_struct_input );
    }


    /**
     * It sanitizes the field 'base_url'.
     * @group  regression
     * @covers EnginesModel_EngineDAO::sanitize
     */
    public function test_sanitize_base_url_field() {
        $this->engine_struct_input->base_url            = <<<LABEL
www.ba""r/foo'.com
LABEL;
        $this->engine_struct_expected->base_url         = <<<LABEL
www.ba""r/foo'.com
LABEL;
        $this->engine_struct_expected->others           = "{}";
        $this->engine_struct_expected->extra_parameters = "{}";
        $this->engine_Dao->sanitize( $this->engine_struct_input );
        $this->assertEquals( $this->engine_struct_expected, $this->engine_struct_input );
    }


    /**
     * It sanitizes the field 'translate_relative_url'.
     * @group  regression
     * @covers EnginesModel_EngineDAO::sanitize
     */
    public function test_sanitize_translate_relative_url_field() {
        $this->engine_struct_input->translate_relative_url    = <<<LABEL
www.ba""r/foo'.com
LABEL;
        $this->engine_struct_expected->translate_relative_url = <<<LABEL
www.ba""r/foo'.com
LABEL;
        $this->engine_struct_expected->others                 = "{}";
        $this->engine_struct_expected->extra_parameters       = "{}";
        $this->engine_Dao->sanitize( $this->engine_struct_input );
        $this->assertEquals( $this->engine_struct_expected, $this->engine_struct_input );
    }


    /**
     * It sanitizes the field 'contribute_relative_url_field'.
     * @group  regression
     * @covers EnginesModel_EngineDAO::sanitize
     */
    public function test_sanitize_contribute_relative_url_field() {
        $this->engine_struct_input->contribute_relative_url    = <<<LABEL
www.ba""r/foo'.com
LABEL;
        $this->engine_struct_expected->contribute_relative_url = <<<LABEL
www.ba""r/foo'.com
LABEL;
        $this->engine_struct_expected->others                  = "{}";
        $this->engine_struct_expected->extra_parameters        = "{}";
        $this->engine_Dao->sanitize( $this->engine_struct_input );
        $this->assertEquals( $this->engine_struct_expected, $this->engine_struct_input );
    }


    /**
     * It sanitizes the field 'delete_relative_url'.
     * @group  regression
     * @covers EnginesModel_EngineDAO::sanitize
     */
    public function test_sanitize_delete_relative_url_field() {
        $this->engine_struct_input->delete_relative_url    = <<<LABEL
www.ba""r/foo'.com
LABEL;
        $this->engine_struct_expected->delete_relative_url = <<<LABEL
www.ba""r/foo'.com
LABEL;
        $this->engine_struct_expected->others              = "{}";
        $this->engine_struct_expected->extra_parameters    = "{}";
        $this->engine_Dao->sanitize( $this->engine_struct_input );
        $this->assertEquals( $this->engine_struct_expected, $this->engine_struct_input );
    }


    /**
     * It sanitizes the field 'others'.
     * @group  regression
     * @covers EnginesModel_EngineDAO::sanitize
     */
    public function test_sanitize_others_field() {
        $this->engine_struct_input->others              = <<<LABEL
ba""r/foo'
LABEL;
        $this->engine_struct_expected->others           = <<<LABEL
"ba\"\"r\/foo'"
LABEL;
        $this->engine_struct_expected->extra_parameters = "{}";
        $this->engine_Dao->sanitize( $this->engine_struct_input );
        $this->assertEquals( $this->engine_struct_expected, $this->engine_struct_input );
    }


    /**
     * It sanitizes the field 'class_load'.
     * @group  regression
     * @covers EnginesModel_EngineDAO::sanitize
     */
    public function test_sanitize_class_load_field() {
        $this->engine_struct_input->class_load          = <<<LABEL
ba""r/foo'
LABEL;
        $this->engine_struct_expected->class_load       = <<<LABEL
ba""r/foo'
LABEL;
        $this->engine_struct_expected->others           = "{}";
        $this->engine_struct_expected->extra_parameters = "{}";
        $this->engine_Dao->sanitize( $this->engine_struct_input );
        $this->assertEquals( $this->engine_struct_expected, $this->engine_struct_input );
    }


    /**
     * * It sanitizes the field 'extra_parameters'.
     * @group  regression
     * @covers EnginesModel_EngineDAO::sanitize
     */
    public function test_sanitize_extra_parameters_field() {
        $this->engine_struct_input->extra_parameters    = <<<LABEL
ba""r/foo'
LABEL;
        $this->engine_struct_expected->extra_parameters = <<<LABEL
"ba\"\"r\/foo'"
LABEL;
        $this->engine_struct_expected->others           = "{}";
        $this->engine_Dao->sanitize( $this->engine_struct_input );
        $this->assertEquals( $this->engine_struct_expected, $this->engine_struct_input );
    }


    /**
     * It sanitizes the field 'penalty'.
     * @group  regression
     * @covers EnginesModel_EngineDAO::sanitize
     */
    public function test_sanitize_penalty_field() {
        $this->engine_struct_input->penalty             = <<<LABEL
56""2/000'
LABEL;
        $this->engine_struct_expected->penalty          = <<<LABEL
56""2/000'
LABEL;
        $this->engine_struct_expected->others           = "{}";
        $this->engine_struct_expected->extra_parameters = "{}";
        $this->engine_Dao->sanitize( $this->engine_struct_input );
        $this->assertEquals( $this->engine_struct_expected, $this->engine_struct_input );
    }


    /**
     * It sanitizes the field 'active'.
     * @group  regression
     * @covers EnginesModel_EngineDAO::sanitize
     */
    public function test_sanitize_active_field() {
        $this->engine_struct_input->active              = <<<LABEL
56""2/000'
LABEL;
        $this->engine_struct_expected->active           = <<<LABEL
56""2/000'
LABEL;
        $this->engine_struct_expected->others           = "{}";
        $this->engine_struct_expected->extra_parameters = "{}";
        $this->engine_Dao->sanitize( $this->engine_struct_input );
        $this->assertEquals( $this->engine_struct_expected, $this->engine_struct_input );
    }


    /**
     * It sanitizes the field 'uid'.
     * @group  regression
     * @covers EnginesModel_EngineDAO::sanitize
     */
    public function test_sanitize_uid_field() {
        $this->engine_struct_input->uid                 = <<<LABEL
56""2/000'
LABEL;
        $this->engine_struct_expected->uid              = <<<LABEL
56""2/000'
LABEL;
        $this->engine_struct_expected->others           = "{}";
        $this->engine_struct_expected->extra_parameters = "{}";
        $this->engine_Dao->sanitize( $this->engine_struct_input );
        $this->assertEquals( $this->engine_struct_expected, $this->engine_struct_input );
    }

    /**
     * It sanitizes the field 'others' filled with a realistic value.
     * @group  regression
     * @covers EnginesModel_EngineDAO::sanitize
     */
    public function test_sanitize_realistic_others_field() {
        $this->engine_struct_input->others              = <<<'LAB'
{"gloss_get_relative_url":"glossary/get","gloss_set_relative_url":"glossary/set","gloss_update_relative_url":"glossary/update","gloss_delete_relative_url":"glossary/delete","tmx_import_relative_url":"tmx/import","tmx_status_relative_url":"tmx/status","tmx_export_create_url":"tmx/export/create","tmx_export_check_url":"tmx/export/check","tmx_export_download_url":"tmx/export/download","tmx_export_list_url":"tmx/export/list","api_key_create_user_url":"createranduser","api_key_check_auth_url":"authkey","analyze_url":"analyze","detect_language_url":"langdetect.php"}
LAB;
        $this->engine_struct_expected->others           = <<<'LAB'
"{\"gloss_get_relative_url\":\"glossary\/get\",\"gloss_set_relative_url\":\"glossary\/set\",\"gloss_update_relative_url\":\"glossary\/update\",\"gloss_delete_relative_url\":\"glossary\/delete\",\"tmx_import_relative_url\":\"tmx\/import\",\"tmx_status_relative_url\":\"tmx\/status\",\"tmx_export_create_url\":\"tmx\/export\/create\",\"tmx_export_check_url\":\"tmx\/export\/check\",\"tmx_export_download_url\":\"tmx\/export\/download\",\"tmx_export_list_url\":\"tmx\/export\/list\",\"api_key_create_user_url\":\"createranduser\",\"api_key_check_auth_url\":\"authkey\",\"analyze_url\":\"analyze\",\"detect_language_url\":\"langdetect.php\"}"
LAB;
        $this->engine_struct_expected->extra_parameters = "{}";
        $this->engine_Dao->sanitize( $this->engine_struct_input );
        $this->assertEquals( $this->engine_struct_expected, $this->engine_struct_input );
    }

}