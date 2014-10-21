<?php
/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 14/10/14
 * Time: 19.17
 */

include_once( "AbstractTest.php" );

class TmKeyManagementTest extends Tests_AbstractTest {

    private static $dummyTmKey_key;
    private static $dummyTmKey_owner;
    private static $dummyTmKey_r;
    private static $dummyTmKey_w;
    private static $dummyTmKey_uid_transl;
    private static $dummyTmKey_r_transl;
    private static $dummyTmKey_w_transl;
    private static $dummyTmKey_edit;
    private static $uid_translator;

    private static $validJsonTmKeyArr;
    private static $invalidJsonTmKeyArr;
    private static $validJsonTmKeyArrWithUidTranslator;
    private static $invalidClientJson;
    private static $validClientJson;
    private static $invalidServerJson;
    private static $validServerJson;

    private static $invalidGrantString;
    private static $invalidTypeString;
    private static $invalidRoleString;

    private static $validTmKeyStructArr;
    private static $invalidTmKeyStructArr;

    public function setUp() {
        parent::setUp();
//        MemcacheHandler::getInstance( array( '127.0.0.1:11211' => 1 ) )->flush();

        self::$dummyTmKey_key        = "fookey";
        self::$dummyTmKey_owner      = 1;
        self::$dummyTmKey_r          = 1;
        self::$dummyTmKey_w          = 0;
        self::$dummyTmKey_uid_transl = 123;
        self::$dummyTmKey_r_transl   = 0;
        self::$dummyTmKey_w_transl   = 1;
        self::$dummyTmKey_edit       = true;
        self::$uid_translator        = 123;

        self::$validJsonTmKeyArr                  = '[{"tm":true,"glos":false,"owner":true,"uid_transl":null,"uid_rev":null,"name":"My personal Key","key":"993dddb1c603b4e57f69","r":"1","w":"0","r_transl":null,"w_transl":null,"r_rev":null,"w_rev":null,"source":null,"target":null}]';
        self::$invalidJsonTmKeyArr                = '[{tm":true,"glos":true"owner":true,"uid_transl":null,"uid_rev":null,"name":"My personal Key","key":"993dddb1c603b4e57f69","r":"1","w":"0","r_transl":null,"w_transl":null,"r_rev":null,"w_rev":null,"source":null,"target":null}]';
        self::$validJsonTmKeyArrWithUidTranslator = '[{"tm":true,"glos":false,"owner":false,"uid_transl":123,"uid_rev":null,"name":"My personal Key","key":"993dddb1c603b4e57f69","r":"1","w":"0","r_transl":"1","w_transl":"1","r_rev":null,"w_rev":null,"source":null,"target":null}]';
        self::$invalidClientJson                  = '[{name""My personal Key","key":"993dddb1c603b4e57f69","r":"1","w":"0"}]';
        self::$validClientJson                  = '[{"name":"My personal Key","key":"993dddb1c603b4e57f69","r":"1","w":"0"},{"name":"My second Key","key":"testclientKey","r":"1","w":"1"}]';

        self::$invalidServerJson  = '[{tm":true,"glos":true"owner":true,"uid_transl":null,"uid_rev":null,"name":"My personal Key","key":"993dddb1c603b4e57f69","r":"1","w":"0","r_transl":null,"w_transl":null,"r_rev":null,"w_rev":null,"source":null,"target":null}]';
        self::$validServerJson    = '[{"tm":true,"glos":true"owner":true,"uid_transl":null,"uid_rev":null,"name":"My personal Key","key":"993dddb1c603b4e57f69","r":"1","w":"0","r_transl":null,"w_transl":null,"r_rev":null,"w_rev":null,"source":null,"target":null}]';

        self::$invalidGrantString = "invalidGrantString";
        self::$invalidTypeString  = "invalidTypeString";
        self::$invalidRoleString  = "invalidRoleString";

        $newTm                     = array();
        $newTm[ 'key' ]            = sha1( chr( rand( 97, 122 ) ) );
        $newTm[ 'owner' ]          = $newTm[ 'key' ] & 1;
        $newTm[ 'r' ]              = $newTm[ 'key' ][ 5 ] & 1;
        $newTm[ 'w' ]              = $newTm[ 'key' ][ 7 ] & 1;
        $newTm[ 'r_transl' ]       = $newTm[ 'key' ][ 2 ] & 1;
        $newTm[ 'w_transl' ]       = $newTm[ 'key' ][ 4 ] & 1;
        $newTm[ 'uid_transl' ]     = rand( 1, 1024 );
        self::$validTmKeyStructArr = $newTm;

        self::$invalidTmKeyStructArr                   = self::$validTmKeyStructArr;
        self::$invalidTmKeyStructArr[ 'invalidField' ] = 'invalidField';
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

    public function testGetJobTmKeys_validInput() {
        $tmKeys = null;

        try {
            $tmKeys = TmKeyManagement_TmKeyManagement::getJobTmKeys( self::$validJsonTmKeyArr );
        } catch ( Exception $e ) {
            //An error occurred: test failed
            $this->assertTrue( false );
        }

        $this->assertNotNull( $tmKeys );
        $this->assertTrue( is_array( $tmKeys ) );

        foreach ( $tmKeys as $tm_key ) {
            $this->assertInstanceOf( "TmKeyManagement_TmKeyStruct", $tm_key );
        }

    }

    public function testGetJobTmKeys_invalidJson() {
        try {
            $tmKeys = TmKeyManagement_TmKeyManagement::getJobTmKeys( self::$invalidJsonTmKeyArr );
        } catch ( Exception $e ) {
            $invalidJSONposition = strpos( $e->getMessage(), "Invalid JSON" );

            $this->assertTrue( $invalidJSONposition > -1 );
        }
    }

    public function testGetJobTmKeys_wGrant() {

        $tmKeys = TmKeyManagement_TmKeyManagement::getJobTmKeys( self::$validJsonTmKeyArr, 'w' );

        $this->assertNotNull( $tmKeys );
        $this->assertTrue( is_array( $tmKeys ) );

        $this->assertTrue( empty( $tmKeys ) );
    }

    public function testGetJobTmKeys_invalidGrant() {
        try {
            $tmKeys = TmKeyManagement_TmKeyManagement::getJobTmKeys( self::$validJsonTmKeyArr, self::$invalidGrantString );
        } catch ( Exception $e ) {
            $invalidGrantPosition = strpos( $e->getMessage(), "Invalid grant string." );

            $this->assertTrue( $invalidGrantPosition > -1 );
        }
    }

    public function testGetJobTmKeys_glosType() {
        $tmKeys = TmKeyManagement_TmKeyManagement::getJobTmKeys( self::$validJsonTmKeyArr, 'rw', 'glos', TmKeyManagement_Filter::ROLE_TRANSLATOR );

        $this->assertNotNull( $tmKeys );
        $this->assertTrue( is_array( $tmKeys ) );

        $this->assertTrue( empty( $tmKeys ) );
    }

    public function testGetJobTmKeys_revisorRole() {
        $tmKeys = TmKeyManagement_TmKeyManagement::getJobTmKeys( self::$validJsonTmKeyArr, 'rw', 'glos', TmKeyManagement_Filter::ROLE_REVISOR );

        $this->assertNotNull( $tmKeys );
        $this->assertTrue( is_array( $tmKeys ) );

        $this->assertTrue( empty( $tmKeys ) );
    }

    public function testGetJobTmKeys_invalidType() {
        try {
            $tmKeys = TmKeyManagement_TmKeyManagement::getJobTmKeys( self::$validJsonTmKeyArr, 'rw', self::$invalidTypeString );
        } catch ( Exception $e ) {
            $invalidGrantPosition = strpos( $e->getMessage(), "Invalid type string." );

            $this->assertTrue( $invalidGrantPosition > -1 );
        }
    }

    public function testGetJobTmKeys_invalidRole() {
        try {
            $tmKeys = TmKeyManagement_TmKeyManagement::getJobTmKeys( self::$validJsonTmKeyArr, 'rw', 'tm', self::$invalidRoleString );
        } catch ( Exception $e ) {
            $invalidFilterPosition = strpos( $e->getMessage(), "Filter type" );

            $this->assertTrue( $invalidFilterPosition > -1 );
        }
    }

    public function testGetJobTmKeys_uidNotNull() {
        $tmKeys = TmKeyManagement_TmKeyManagement::getJobTmKeys(
                self::$validJsonTmKeyArrWithUidTranslator,
                'rw',
                'tm',
                TmKeyManagement_Filter::ROLE_TRANSLATOR,
                self::$uid_translator );

        $this->assertNotNull( $tmKeys );
        $this->assertTrue( is_array( $tmKeys ) );

        $this->assertFalse( empty( $tmKeys ) );
        $this->assertInstanceOf( "TmKeyManagement_TmKeyStruct", $tmKeys[ 0 ] );
    }

    /** TEST isValidStructure */
    public function testIsValidStructure_validStructure() {
        $validObj = TmKeyManagement_TmKeyManagement::isValidStructure( self::$validTmKeyStructArr );

        $this->assertInstanceOf( 'TmKeyManagement_TmKeyStruct', $validObj );
        $this->assertEquals( self::$validTmKeyStructArr[ 'key' ], $validObj->key );
        $this->assertEquals( self::$validTmKeyStructArr[ 'owner' ], $validObj->owner );
        $this->assertEquals( self::$validTmKeyStructArr[ 'r' ], $validObj->r );
        $this->assertEquals( self::$validTmKeyStructArr[ 'w' ], $validObj->w );
        $this->assertEquals( self::$validTmKeyStructArr[ 'r_transl' ], $validObj->r_transl );
        $this->assertEquals( self::$validTmKeyStructArr[ 'w_transl' ], $validObj->w_transl );
        $this->assertEquals( self::$validTmKeyStructArr[ 'uid_transl' ], $validObj->uid_transl );
    }

    public function testIsValidStructure_invalidStructure() {
        $validObj = TmKeyManagement_TmKeyManagement::isValidStructure( self::$invalidTmKeyStructArr );

        $this->assertFalse( $validObj );
    }

    /** TEST mergeJsonKeys */
    public function testMergeJsonKeys_invalidClientJson() {
        try{
            $resultMerge = TmKeyManagement_TmKeyManagement::mergeJsonKeys(self::$invalidClientJson, self::$validServerJson);
        }
        catch(Exception $e){
            $this->assertTrue( $e->getCode() > 0 );
        }
    }

    public function testMergeJsonKeys_invalidServerJson() {
        try{
            $resultMerge = TmKeyManagement_TmKeyManagement::mergeJsonKeys(self::$validClientJson, self::$invalidServerJson);
        }
        catch(Exception $e){
            $this->assertTrue( $e->getCode() > 0 );
        }
    }

    public function testMergeJsonKeys_invalidUserRole() {
        try{
            $resultMerge = TmKeyManagement_TmKeyManagement::mergeJsonKeys(
                    self::$validClientJson,
                    self::$validServerJson,
                    self::$invalidRoleString
            );
        }
        catch(Exception $e){
            $this->assertEquals( 1, $e->getCode() );
            $this->assertEquals( "Invalid grant string.", $e->getMessage());
        }
    }

    public function testMergeJsonKeys_validInput() {

    }
}
 