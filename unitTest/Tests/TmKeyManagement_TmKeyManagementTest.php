<?php
/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 14/10/14
 * Time: 19.17
 */

include_once( "AbstractTest.php" );

class TmKeyManagement_TmKeyManagementTest extends Tests_AbstractTest {

    private static $dummyTmKey_key = "fookey";
    private static $dummyTmKey_owner = 1;
    private static $dummyTmKey_r = 1;
    private static $dummyTmKey_w = 0;
    private static $dummyTmKey_uid_transl = 123;
    private static $dummyTmKey_r_transl = 0;
    private static $dummyTmKey_w_transl = 1;
    private static $dummyTmKey_edit = true;
    private static $uid_translator = 123;

    private static $validJsonTmKeyArr = '[{"tm":true,"glos":false,"owner":true,"uid_transl":null,"uid_rev":null,"name":"My personal Key","key":"993dddb1c603b4e57f69","r":"1","w":"0","r_transl":null,"w_transl":null,"r_rev":null,"w_rev":null,"source":null,"target":null}]';
    private static $invalidJsonTmKeyArr = '[{tm":true,"glos":true"owner":true,"uid_transl":null,"uid_rev":null,"name":"My personal Key","key":"993dddb1c603b4e57f69","r":"1","w":"0","r_transl":null,"w_transl":null,"r_rev":null,"w_rev":null,"source":null,"target":null}]';
    private static $validJsonTmKeyArrWithUidTranslator = '[{"tm":true,"glos":false,"owner":false,"uid_transl":123,"uid_rev":null,"name":"My personal Key","key":"993dddb1c603b4e57f69","r":"1","w":"0","r_transl":"1","w_transl":"1","r_rev":null,"w_rev":null,"source":null,"target":null}]';

    private static $invalidGrantString = "invalidGrantString";
    private static $invalidTypeString = "invalidTypeString";
    private static $invalidRoleString = "invalidRoleString";

    public function setUp() {
        parent::setUp();
//        MemcacheHandler::getInstance( array( '127.0.0.1:11211' => 1 ) )->flush();
    }

    public function tearDown() {
        parent::tearDown();
//        MemcacheHandler::close();
    }

    public function testGetTmKeyStructure_noArgs() {
        $structure = TmKeyManagement_TmKeyManagement::getTmKeyStructure();

        $this->assertInstanceOf( "TmKeyManagement_TmKeyStruct", $structure );
        $this->assertObjectHasAttribute( "tm", $structure );
        $this->assertObjectHasAttribute( "glos", $structure );
        $this->assertObjectHasAttribute( "owner", $structure );
        $this->assertObjectHasAttribute( "uid_transl", $structure );
        $this->assertObjectHasAttribute( "uid_rev", $structure );
        $this->assertObjectHasAttribute( "name", $structure );
        $this->assertObjectHasAttribute( "key", $structure );
        $this->assertObjectHasAttribute( "r", $structure );
        $this->assertObjectHasAttribute( "w", $structure );
        $this->assertObjectHasAttribute( "r_transl", $structure );
        $this->assertObjectHasAttribute( "w_transl", $structure );
        $this->assertObjectHasAttribute( "r_rev", $structure );
        $this->assertObjectHasAttribute( "w_rev", $structure );
        $this->assertObjectHasAttribute( "source", $structure );
        $this->assertObjectHasAttribute( "target", $structure );

        $this->assertNull( $structure->tm );
        $this->assertNull( $structure->glos );
        $this->assertNull( $structure->owner );
        $this->assertNull( $structure->uid_transl );
        $this->assertNull( $structure->uid_rev );
        $this->assertNull( $structure->name );
        $this->assertNull( $structure->key );
        $this->assertNull( $structure->r );
        $this->assertNull( $structure->w );
        $this->assertNull( $structure->r_transl );
        $this->assertNull( $structure->w_transl );
        $this->assertNull( $structure->r_rev );
        $this->assertNull( $structure->w_rev );
        $this->assertNull( $structure->source );
        $this->assertNull( $structure->target );

    }

    public function testGetTmKeyStructure_withArgs() {
        $args = array(
                'key'        => self::$dummyTmKey_key,
                'uid_transl' => self::$dummyTmKey_uid_transl,
                'r_transl'   => self::$dummyTmKey_r_transl,
                'w_transl'   => self::$dummyTmKey_w_transl,
                'owner'      => self::$dummyTmKey_owner,
                'r'          => self::$dummyTmKey_r,
                'w'          => self::$dummyTmKey_w
        );

        $structure = TmKeyManagement_TmKeyManagement::getTmKeyStructure( $args );

        $this->assertInstanceOf( "TmKeyManagement_TmKeyStruct", $structure );
        $this->assertObjectHasAttribute( "tm", $structure );
        $this->assertObjectHasAttribute( "glos", $structure );
        $this->assertObjectHasAttribute( "owner", $structure );
        $this->assertObjectHasAttribute( "uid_transl", $structure );
        $this->assertObjectHasAttribute( "uid_rev", $structure );
        $this->assertObjectHasAttribute( "name", $structure );
        $this->assertObjectHasAttribute( "key", $structure );
        $this->assertObjectHasAttribute( "r", $structure );
        $this->assertObjectHasAttribute( "w", $structure );
        $this->assertObjectHasAttribute( "r_transl", $structure );
        $this->assertObjectHasAttribute( "w_transl", $structure );
        $this->assertObjectHasAttribute( "r_rev", $structure );
        $this->assertObjectHasAttribute( "w_rev", $structure );
        $this->assertObjectHasAttribute( "source", $structure );
        $this->assertObjectHasAttribute( "target", $structure );

        $this->assertNull( $structure->tm );
        $this->assertNull( $structure->glos );
        $this->assertEquals( self::$dummyTmKey_owner, $structure->owner );
        $this->assertEquals( self::$dummyTmKey_uid_transl, $structure->uid_transl );
        $this->assertNull( $structure->uid_rev );
        $this->assertNull( $structure->name );
        $this->assertEquals( self::$dummyTmKey_key, $structure->key );
        $this->assertEquals( self::$dummyTmKey_r, $structure->r );
        $this->assertEquals( self::$dummyTmKey_w, $structure->w );
        $this->assertEquals( self::$dummyTmKey_r_transl, $structure->r_transl );
        $this->assertEquals( self::$dummyTmKey_w_transl, $structure->w_transl );
        $this->assertNull( $structure->r_rev );
        $this->assertNull( $structure->w_rev );
        $this->assertNull( $structure->source );
        $this->assertNull( $structure->target );
    }

    /**
     * @depends testGetTmKeyStructure_noArgs
     */
    public function testGetClientTmKeyStructure_noArgs() {
        $structure = TmKeyManagement_TmKeyManagement::getClientTmKeyStructure();

        $this->assertInstanceOf( "TmKeyManagement_ClientTmKeyStruct", $structure );
        $this->assertObjectHasAttribute( "edit", $structure );

        $this->assertTrue( $structure->edit );
    }

    /**
     * @depends testGetTmKeyStructure_withArgs
     */
    public function testGetClientTmKeyStructure_withArgs() {

        $args = array(
                'key'        => self::$dummyTmKey_key,
                'uid_transl' => self::$dummyTmKey_uid_transl,
                'r_transl'   => self::$dummyTmKey_r_transl,
                'w_transl'   => self::$dummyTmKey_w_transl,
                'owner'      => self::$dummyTmKey_owner,
                'r'          => self::$dummyTmKey_r,
                'w'          => self::$dummyTmKey_w,
                'edit'       => self::$dummyTmKey_edit
        );

        $structure = TmKeyManagement_TmKeyManagement::getClientTmKeyStructure( $args );

        $this->assertInstanceOf( "TmKeyManagement_ClientTmKeyStruct", $structure );
        $this->assertObjectHasAttribute( "edit", $structure );

        $this->assertTrue( $structure->edit );
        $this->assertNull( $structure->tm );
        $this->assertNull( $structure->glos );
        $this->assertEquals( self::$dummyTmKey_owner, $structure->owner );
        $this->assertEquals( self::$dummyTmKey_uid_transl, $structure->uid_transl );
        $this->assertNull( $structure->uid_rev );
        $this->assertNull( $structure->name );
        $this->assertEquals( self::$dummyTmKey_key, $structure->key );
        $this->assertEquals( self::$dummyTmKey_r, $structure->r );
        $this->assertEquals( self::$dummyTmKey_w, $structure->w );
        $this->assertEquals( self::$dummyTmKey_r_transl, $structure->r_transl );
        $this->assertEquals( self::$dummyTmKey_w_transl, $structure->w_transl );
        $this->assertNull( $structure->r_rev );
        $this->assertNull( $structure->w_rev );
        $this->assertNull( $structure->source );
        $this->assertNull( $structure->target );
    }

    /** TEST getJobTmKeys */

    public function testGetJobTmKeys_validJson_rwGrant_tmType_defaultRole_uidNull() {

        try{
            $tmKeys = TmKeyManagement_TmKeyManagement::getJobTmKeys(self::$validJsonTmKeyArr);
        }
        catch(Exception $e){
            //An error occurred: test failed
            $this->assertTrue(false);
        }

        $this->assertNotNull($tmKeys);
        $this->assertTrue(is_array($tmKeys));

        foreach ( $tmKeys as $tm_key ) {
            $this->assertInstanceOf("TmKeyManagement_TmKeyStruct", $tm_key);
        }

    }

    public function testGetJobTmKeys_invalidJson_rwGrant_tmType_defaultRole_uidNull() {
        try {
            $tmKeys = TmKeyManagement_TmKeyManagement::getJobTmKeys( self::$invalidJsonTmKeyArr );
        }
        catch(Exception $e){
            $invalidJSONposition = strpos($e->getMessage(), "Invalid JSON");

            $this->assertTrue($invalidJSONposition > -1);
        }
    }

    public function testGetJobTmKeys_validJson_wGrant_tmType_defaultRole_uidNull() {

        $tmKeys = TmKeyManagement_TmKeyManagement::getJobTmKeys( self::$validJsonTmKeyArr, 'w' );

        $this->assertNotNull( $tmKeys );
        $this->assertTrue( is_array( $tmKeys ) );

        $this->assertTrue( empty($tmKeys) );
    }

    public function testGetJobTmKeys_validJson_invalidGrant_tmType_defaultRole_uidNull() {
        try {
            $tmKeys = TmKeyManagement_TmKeyManagement::getJobTmKeys( self::$validJsonTmKeyArr, self::$invalidGrantString );
        }
        catch(Exception $e){
            $invalidGrantPosition = strpos($e->getMessage(), "Invalid grant string.");

            $this->assertTrue($invalidGrantPosition > -1);
        }
    }

    public function testGetJobTmKeys_validJson_rwGrant_glosType_translatorRole_uidNull() {
        $tmKeys = TmKeyManagement_TmKeyManagement::getJobTmKeys( self::$validJsonTmKeyArr, 'rw', 'glos', TmKeyManagement_Filter::ROLE_TRANSLATOR );

        $this->assertNotNull( $tmKeys );
        $this->assertTrue( is_array( $tmKeys ) );

        $this->assertTrue( empty($tmKeys) );
    }

    public function testGetJobTmKeys_validJson_rwGrant_glosType_revisorRole_uidNull() {
        $tmKeys = TmKeyManagement_TmKeyManagement::getJobTmKeys( self::$validJsonTmKeyArr, 'rw', 'glos', TmKeyManagement_Filter::ROLE_REVISOR );

        $this->assertNotNull( $tmKeys );
        $this->assertTrue( is_array( $tmKeys ) );

        $this->assertTrue( empty($tmKeys) );
    }

    public function testGetJobTmKeys_validJson_rwGrant_invalidType_defaultRole_uidNull() {
        try {
            $tmKeys = TmKeyManagement_TmKeyManagement::getJobTmKeys( self::$validJsonTmKeyArr, 'rw', self::$invalidTypeString );
        }
        catch(Exception $e){
            $invalidGrantPosition = strpos($e->getMessage(), "Invalid type string.");

            $this->assertTrue($invalidGrantPosition > -1);
        }
    }

    public function testGetJobTmKeys_validJson_rwGrant_tmType_invalidRole_uidNull() {
        try {
            $tmKeys = TmKeyManagement_TmKeyManagement::getJobTmKeys( self::$validJsonTmKeyArr, 'rw', 'tm', self::$invalidRoleString );
        }
        catch(Exception $e){
            $invalidFilterPosition = strpos($e->getMessage(), "Filter type");

            $this->assertTrue($invalidFilterPosition > -1);
        }
    }

    public function testGetJobTmKeys_validJson_rwGrant_tmType_defaultRole_uidNotNull() {
        $tmKeys = TmKeyManagement_TmKeyManagement::getJobTmKeys(
                self::$validJsonTmKeyArrWithUidTranslator,
                'rw',
                'tm',
                TmKeyManagement_Filter::ROLE_TRANSLATOR,
                self::$uid_translator );

        $this->assertNotNull( $tmKeys );
        $this->assertTrue( is_array( $tmKeys ) );

        $this->assertFalse( empty($tmKeys) );
        $this->assertInstanceOf( "TmKeyManagement_TmKeyStruct", $tmKeys[0] );
    }

    /** TEST getOwnerKeys */
    

}
 