<?php

use TestHelpers\AbstractTest;


/**
 * @group regression
 * @covers Users_UserDao::sanitize
 * User: dinies
 * Date: 27/05/16
 * Time: 18.00
 */
class SanitizeUserTest extends AbstractTest
{

    /**
     * @var Users_UserDao
     */
    protected $user_Dao;
    protected $user_struct_input;
    protected $user_struct_expected;
    protected $database_instance;


    public function setUp()
    {
        parent::setUp();
        $this->user_struct_input = new Users_UserStruct();
        $this->user_struct_expected = new Users_UserStruct();
        $this->database_instance = Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE);
        $this->user_Dao = new Users_UserDao($this->database_instance);
    }

    /**
     * It sanitizes the field 'uid'.
     * @group regression
     * @covers Users_UserDao::sanitize
     */
    public function test_sanitize_uid_field()
    {
        $this->user_struct_input->uid = <<<LABEL
23999
LABEL;
        $this->user_struct_expected->uid = 23999;
        $this->user_Dao->sanitize($this->user_struct_input);
        $this->assertEquals($this->user_struct_expected, $this->user_struct_input);
    }


    /**
     * It sanitizes the field 'email'.
     * @group regression
     * @covers Users_UserDao::sanitize
     */
    public
    function test_sanitize_email_field()
    {
        $this->user_struct_input->email = <<<'LABEL'
ba\r@\fo\o"\.net
LABEL;
        $this->user_struct_expected->email = <<<'LABEL'
ba\\r@\\fo\\o\"\\.net
LABEL;
        $this->user_Dao->sanitize($this->user_struct_input);
        $this->assertEquals($this->user_struct_expected, $this->user_struct_input);
    }


    /**
     * It sanitizes the field 'create_date'.
     * @group regression
     * @covers Users_UserDao::sanitize
     */
    public
    function test_sanitize_create_date_field()
    {
        $this->user_struct_input->create_date = <<<'LABEL'
\2"016-042"-29 18:0\'26:1142
LABEL;
        $this->user_struct_expected->create_date = <<<'LABEL'
\\2\"016-042\"-29 18:0\\\'26:1142
LABEL;
        $this->user_Dao->sanitize($this->user_struct_input);
        $this->assertEquals($this->user_struct_expected, $this->user_struct_input);
    }

    /**
     * It sanitizes the field 'first_name'.
     * @group regression
     * @covers Users_UserDao::sanitize
     */
    public
    function test_sanitize_first_name_field()
    {
        $this->user_struct_input->first_name = <<<LABEL
j\on\h""\
LABEL;
        $this->user_struct_expected->first_name = <<<'LABEL'
j\\on\\h\"\"\\
LABEL;
        $this->user_Dao->sanitize($this->user_struct_input);
        $this->assertEquals($this->user_struct_expected, $this->user_struct_input);
    }

    /**
     * It sanitizes the field 'last_name'.
     * @group regression
     * @covers Users_UserDao::sanitize
     */
    public
    function test_sanitize_last_name_field()
    {
        $this->user_struct_input->last_name = <<<'LABEL'
gyga|gym\\leon"".-
LABEL;
        $this->user_struct_expected->last_name = <<<'LABEL'
gyga|gym\\\\leon\"\".-
LABEL;
        $this->user_Dao->sanitize($this->user_struct_input);
        $this->assertEquals($this->user_struct_expected, $this->user_struct_input);
    }
}