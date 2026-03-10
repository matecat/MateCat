<?php

namespace unit\Model\TmKeyManagement;

use Exception;
use Model\DataAccess\Database;
use Model\TmKeyManagement\MemoryKeyDao;
use Model\TmKeyManagement\MemoryKeyStruct;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\Test;
use ReflectionException;
use TestHelpers\AbstractTest;
use TestHelpers\Utils;
use Utils\TmKeyManagement\Filter;
use Utils\TmKeyManagement\TmKeyManager;
use Utils\TmKeyManagement\TmKeyStruct;

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 14/10/14
 * Time: 19.17
 */
class TmKeyManagementTest extends AbstractTest
{

    private static string $dummyTmKey_key;
    private static string $dummyTmKey_owner;
    private static int $dummyTmKey_r;
    private static int $dummyTmKey_w;
    private static int $dummyTmKey_uid_transl;
    private static int $dummyTmKey_r_transl;
    private static int $dummyTmKey_w_transl;
    private static bool $dummyTmKey_edit;
    private static ?int $uid_translator = null;

    private static string $validJsonStringTmKeyList;
    private static string $invalidJsonStringTmKeyList;
    private static string $validJsonStringTmKeyListWithUidTranslator;
    private static string $invalidClientJson;
    private static string $validClientJson;
    private static string $invalidServerJson;
    private static string $validServerJson;

    private static string $invalidGrantString;
    private static string $invalidTypeString;
    private static string $invalidRoleString;

    private static array $validTmKeyStructArr;
    private static array $invalidTmKeyStructArr;

    //test set for mergeKeys
    private static string $srv_json_ABC;
    private static string $srv_json_GHI;
    private static string $srv_json_ABC_GHI_DEF;
    private static string $client_json_ABC_DEF;
    private static string $client_json_GHI_DEF;
    private static string $client_json_ABC;
    private static string $client_json_DEF;
    private static string $client_json_ABC_GHI_JKL;
    private static string $client_json_GHI;
    private static string $client_json_INVALID_GHI;

    public function setUp(): void
    {
        parent::setUp();

//        MemcacheHandler::getInstance( array( '127.0.0.1:11211' => 1 ) )->flush();

        self::$dummyTmKey_key = "fookey";
        self::$dummyTmKey_owner = 1;
        self::$dummyTmKey_r = 1;
        self::$dummyTmKey_w = 0;
        self::$dummyTmKey_uid_transl = 123;
        self::$dummyTmKey_r_transl = 0;
        self::$dummyTmKey_w_transl = 1;
        self::$dummyTmKey_edit = true;
        self::$uid_translator = 123;

        self::$validJsonStringTmKeyList = '[{"tm":true,"glos":false,"owner":true,"uid_transl":null,"uid_rev":null,"name":"My personal Key","key":"993dddb1c603b4e57f69","r":"1","w":"0","r_transl":null,"w_transl":null,"r_rev":null,"w_rev":null,"source":null,"target":null}]';

        /**
         * Invalid Json because of "glos":true"owner":true,
         */
        self::$invalidJsonStringTmKeyList = '[{tm":true,"glos":true"owner":true,"uid_transl":null,"uid_rev":null,"name":"My personal Key","key":"993dddb1c603b4e57f69","r":"1","w":"0","r_transl":null,"w_transl":null,"r_rev":null,"w_rev":null,"source":null,"target":null}]';
        self::$validJsonStringTmKeyListWithUidTranslator = '[{"tm":true,"glos":false,"owner":false,"uid_transl":123,"uid_rev":null,"name":"My personal Key","key":"993dddb1c603b4e57f69","r":"1","w":"0","r_transl":"1","w_transl":"1","r_rev":null,"w_rev":null,"source":null,"target":null}]';
        self::$invalidClientJson = '[{name""My personal Key","key":"993dddb1c603b4e57f69","r":"1","w":"0"}]';
        self::$validClientJson = '[{"name":"My personal Key","key":"993dddb1c603b4e57f69","r":"1","w":"0"},{"name":"My second Key","key":"testclientKey","r":"1","w":"1"}]';

        self::$invalidServerJson = '[{tm":true,"glos":true"owner":true,"uid_transl":null,"uid_rev":null,"name":"My personal Key","key":"993dddb1c603b4e57f69","r":"1","w":"0","r_transl":null,"w_transl":null,"r_rev":null,"w_rev":null,"source":null,"target":null}]';
        self::$validServerJson = '[{"tm":true,"glos":true,"owner":true,"uid_transl":null,"uid_rev":null,"name":"My personal Key","key":"993dddb1c603b4e57f69","r":"1","w":"0","r_transl":null,"w_transl":null,"r_rev":null,"w_rev":null,"source":null,"target":null}]';

        self::$invalidGrantString = "invalidGrantString";
        self::$invalidTypeString = "invalidTypeString";
        self::$invalidRoleString = "invalidRoleString";

        $newTm = [];
        $newTm['key'] = sha1(chr(rand(97, 122)));
        $newTm['owner'] = ord($newTm['key']) & 1;
        $newTm['r'] = ord($newTm['key'][5]) & 1;
        $newTm['w'] = ord($newTm['key'][12]) & 1;
        $newTm['r_transl'] = ord($newTm['key'][2]) & 1;
        $newTm['w_transl'] = ord($newTm['key'][4]) & 1;
        $newTm['uid_transl'] = rand(1, 1024);
        self::$validTmKeyStructArr = $newTm;

        self::$invalidTmKeyStructArr = self::$validTmKeyStructArr;
        self::$invalidTmKeyStructArr['invalidField'] = 'invalidField';

        self::$srv_json_ABC = '[{"tm":true,"glos":false,"owner":true,"key":"0000123ABC","name":"","r":"1","w":"1","uid_transl":123,"uid_rev":null,"r_transl":1,"w_transl":0,"r_rev":null,"w_rev":null,"source":null,"target":null}]';
        self::$srv_json_GHI = '[{"tm":true,"glos":false,"owner":true,"key":"0000123GHI","name":"My GHI","r":"1","w":"1","uid_transl":null,"uid_rev":null,"r_transl":null,"w_transl":null,"r_rev":null,"w_rev":null,"source":null,"target":null}]';
        self::$srv_json_ABC_GHI_DEF = '[{"tm":true,"glos":false,"owner":true,"key":"0000123ABC","name":"My ABC","r":"1","w":"1","uid_transl":123,"uid_rev":null,"r_transl":1,"w_transl":0,"r_rev":null,"w_rev":null,"source":null,"target":null},' .
            '{"tm":true,"glos":false,"owner":true,"key":"0000123GHI","name":"My GHI","r":"1","w":"1","uid_transl":null,"uid_rev":null,"r_transl":null,"w_transl":null,"r_rev":null,"w_rev":null,"source":null,"target":null},' .
            '{"tm":true,"glos":false,"owner":false,"key":"0000123DEF","name":"My DEF","r":null,"w":null,"uid_transl":123,"uid_rev":null,"r_transl":1,"w_transl":1,"r_rev":null,"w_rev":null,"source":null,"target":null}]';
        self::$client_json_ABC = '[{"key":"0000123ABC","name":"My DEF","r":1,"w":1}]';
        self::$client_json_ABC_DEF = '[{"key":"0000123ABC","name":"My ABC","r":1,"w":1},' .
            '{"key":"0000123DEF","name":"My DEF","r":1,"w":0}]';
        self::$client_json_DEF = '[{"key":"0000123DEF","name":"My DEF","r":1,"w":0}]';
        self::$client_json_GHI_DEF = '[{"key":"*****23GHI","name":"My GHI","r":1,"w":1},' .
            '{"key":"0000123DEF","name":"My DEF","r":1,"w":0}]';

        //the second key is invalid, we expect unchanged in server side
        self::$client_json_ABC_GHI_JKL = '[{"key":"0000123ABC","name":"My ABC","r":1,"w":1},' .
            '{"key":"*****23GHI","name":"My GHI","r":1,"w":0},' .
            '{"key":"0000123JKL","name":"My JKL","r":1,"w":0}]';

        self::$client_json_GHI = '[{"key":"0000123GHI","name":"My GHI","r":0,"w":1}]';

        self::$client_json_INVALID_GHI = '[{"key":"0000123GHI","name":"My GHI","r":0,"w":0}]';
    }

    #[Test]
    public function testGetTmKeyStructure_noArgs()
    {
        $structure = TmKeyManager::getTmKeyStructure();

        $structure->r = true;
        $structure->w = true;

        $this->assertObjectHasProperty("tm", $structure);
        $this->assertObjectHasProperty("glos", $structure);
        $this->assertObjectHasProperty("owner", $structure);
        $this->assertObjectHasProperty("uid_transl", $structure);
        $this->assertObjectHasProperty("uid_rev", $structure);
        $this->assertObjectHasProperty("name", $structure);
        $this->assertObjectHasProperty("key", $structure);
        $this->assertObjectHasProperty("r", $structure);
        $this->assertObjectHasProperty("w", $structure);
        $this->assertObjectHasProperty("r_transl", $structure);
        $this->assertObjectHasProperty("w_transl", $structure);
        $this->assertObjectHasProperty("r_rev", $structure);
        $this->assertObjectHasProperty("w_rev", $structure);
        $this->assertObjectHasProperty("source", $structure);
        $this->assertObjectHasProperty("target", $structure);

        $this->assertTrue($structure->tm);
        $this->assertTrue($structure->glos);
        $this->assertNull($structure->owner);
        $this->assertNull($structure->uid_transl);
        $this->assertNull($structure->uid_rev);
        $this->assertNull($structure->name);
        $this->assertNull($structure->key);
        $this->assertTrue($structure->r);
        $this->assertTrue($structure->w);
        $this->assertNull($structure->r_transl);
        $this->assertNull($structure->w_transl);
        $this->assertNull($structure->r_rev);
        $this->assertNull($structure->w_rev);
        $this->assertNull($structure->source);
        $this->assertNull($structure->target);
    }

    #[Test]
    public function testGetTmKeyStructure_withArgs()
    {
        $args = [
            'key' => self::$dummyTmKey_key,
            'uid_transl' => self::$dummyTmKey_uid_transl,
            'r_transl' => self::$dummyTmKey_r_transl,
            'w_transl' => self::$dummyTmKey_w_transl,
            'owner' => self::$dummyTmKey_owner,
            'r' => self::$dummyTmKey_r,
            'w' => self::$dummyTmKey_w
        ];

        $structure = TmKeyManager::getTmKeyStructure($args);

        $this->assertObjectHasProperty("tm", $structure);
        $this->assertObjectHasProperty("glos", $structure);
        $this->assertObjectHasProperty("owner", $structure);
        $this->assertObjectHasProperty("uid_transl", $structure);
        $this->assertObjectHasProperty("uid_rev", $structure);
        $this->assertObjectHasProperty("name", $structure);
        $this->assertObjectHasProperty("key", $structure);
        $this->assertObjectHasProperty("r", $structure);
        $this->assertObjectHasProperty("w", $structure);
        $this->assertObjectHasProperty("r_transl", $structure);
        $this->assertObjectHasProperty("w_transl", $structure);
        $this->assertObjectHasProperty("r_rev", $structure);
        $this->assertObjectHasProperty("w_rev", $structure);
        $this->assertObjectHasProperty("source", $structure);
        $this->assertObjectHasProperty("target", $structure);

        $this->assertTrue($structure->tm);
        $this->assertTrue($structure->glos);
        $this->assertEquals(self::$dummyTmKey_owner, $structure->owner);
        $this->assertEquals(self::$dummyTmKey_uid_transl, $structure->uid_transl);
        $this->assertNull($structure->uid_rev);
        $this->assertNull($structure->name);
        $this->assertEquals(self::$dummyTmKey_key, $structure->key);
        $this->assertEquals(self::$dummyTmKey_r, $structure->r);
        $this->assertEquals(self::$dummyTmKey_w, $structure->w);
        $this->assertEquals(self::$dummyTmKey_r_transl, $structure->r_transl);
        $this->assertEquals(self::$dummyTmKey_w_transl, $structure->w_transl);
        $this->assertNull($structure->r_rev);
        $this->assertNull($structure->w_rev);
        $this->assertNull($structure->source);
        $this->assertNull($structure->target);
    }

    /**
     * @depends testGetTmKeyStructure_noArgs
     */
    public function testGetClientTmKeyStructure_noArgs()
    {
        $structure = TmKeyManager::getClientTmKeyStructure();

        $this->assertObjectHasProperty("edit", $structure);

        $this->assertTrue($structure->edit);
    }

    /**
     * @depends testGetTmKeyStructure_withArgs
     */
    #[Test]
    #[Depends("testGetTmKeyStructure_withArgs")]
    public function testGetClientTmKeyStructure_withArgs()
    {
        $args = [
            'key' => self::$dummyTmKey_key,
            'uid_transl' => self::$dummyTmKey_uid_transl,
            'r_transl' => self::$dummyTmKey_r_transl,
            'w_transl' => self::$dummyTmKey_w_transl,
            'owner' => self::$dummyTmKey_owner,
            'r' => self::$dummyTmKey_r,
            'w' => self::$dummyTmKey_w,
            'edit' => self::$dummyTmKey_edit
        ];

        $structure = TmKeyManager::getClientTmKeyStructure($args);

        $this->assertObjectHasProperty("edit", $structure);

        $this->assertTrue($structure->edit);
        $this->assertTrue($structure->tm);
        $this->assertTrue($structure->glos);
        $this->assertEquals(self::$dummyTmKey_owner, $structure->owner);
        $this->assertEquals(self::$dummyTmKey_uid_transl, $structure->uid_transl);
        $this->assertNull($structure->uid_rev);
        $this->assertNull($structure->name);
        $this->assertEquals(self::$dummyTmKey_key, $structure->key);
        $this->assertEquals(self::$dummyTmKey_r, $structure->r);
        $this->assertEquals(self::$dummyTmKey_w, $structure->w);
        $this->assertEquals(self::$dummyTmKey_r_transl, $structure->r_transl);
        $this->assertEquals(self::$dummyTmKey_w_transl, $structure->w_transl);
        $this->assertNull($structure->r_rev);
        $this->assertNull($structure->w_rev);
        $this->assertNull($structure->source);
        $this->assertNull($structure->target);
    }

    /** TEST getJobTmKeys */
    #[Test]
    public function testGetJobTmKeys_validInput_noRole_noUid_readKeys()
    {
        $_jobTmKeys = json_encode(
            [
                [
                    'tm' => true,
                    'glos' => true,
                    'owner' => true,
                    'uid_transl' => null,
                    'uid_rev' => null,
                    'name' => 'My personal Key',
                    'key' => 'ABC',
                    'r' => '1',
                    'w' => '1',
                    'r_transl' => null,
                    'w_transl' => null,
                    'r_rev' => null,
                    'w_rev' => null,
                    'source' => null,
                    'target' => null
                ],
                [
                    'tm' => true,
                    'glos' => true,
                    'owner' => false,
                    'uid_transl' => 560,
                    'uid_rev' => null,
                    'name' => 'My personal Key',
                    'key' => 'DEF',
                    'r' => null,
                    'w' => null,
                    'r_transl' => '1',
                    'w_transl' => '0',
                    'r_rev' => null,
                    'w_rev' => null,
                    'source' => null,
                    'target' => null
                ],
                [
                    'tm' => true,
                    'glos' => true,
                    'owner' => false,
                    'uid_transl' => null,
                    'uid_rev' => null,
                    'name' => 'My personal Key',
                    'key' => 'GHI',
                    'r' => null,
                    'w' => null,
                    'r_transl' => '1',
                    'w_transl' => '1',
                    'r_rev' => null,
                    'w_rev' => null,
                    'source' => null,
                    'target' => null
                ]
            ]
        );

        /**
         * EXPECTED:
         * An array containing two TmKeyStruct object representing:
         * the key 'ABC'
         * the key 'GHI'
         */
        $tmKeys = null;

        try {
            $tmKeys = TmKeyManager::getJobTmKeys(
                $_jobTmKeys,
                'r',
                'tm',
                560
            );
        } catch (Exception $e) {
            //An error occurred: test failed
            $this->fail();
        }

        $this->assertCount(2, $tmKeys);

        /**
         * @var $fstKey TmKeyStruct
         */
        $fstKey = $tmKeys[0];

        /**
         * @var $sndKey TmKeyStruct
         */
        $sndKey = $tmKeys[1];

        $this->assertTrue($fstKey->owner);
        $this->assertEquals('ABC', $fstKey->key);
        $this->assertEquals(1, $fstKey->r);
        $this->assertEquals(1, $fstKey->w);
        $this->assertNull($fstKey->uid_transl);
        $this->assertNull($fstKey->r_transl);
        $this->assertNull($fstKey->w_transl);

        $this->assertFalse($sndKey->owner);
        $this->assertEquals('DEF', $sndKey->key);
        $this->assertNull($sndKey->r);
        $this->assertNull($sndKey->w);
        $this->assertEquals(560, $sndKey->uid_transl);
        $this->assertEquals(1, $sndKey->r_transl);
        $this->assertEquals(0, $sndKey->w_transl);
    }

    #[Test]
    public function testGetJobTmKeys_validInput_noRole_Uid_writeKeys()
    {
        $_jobTmKeys = json_encode(
            [
                [
                    'tm' => true,
                    'glos' => true,
                    'owner' => true,
                    'uid_transl' => null,
                    'uid_rev' => null,
                    'name' => 'My personal Key',
                    'key' => 'ABC',
                    'r' => '1',
                    'w' => '1',
                    'r_transl' => null,
                    'w_transl' => null,
                    'r_rev' => null,
                    'w_rev' => null,
                    'source' => null,
                    'target' => null
                ],
                [
                    'tm' => true,
                    'glos' => true,
                    'owner' => false,
                    'uid_transl' => 560,
                    'uid_rev' => null,
                    'name' => 'My personal Key',
                    'key' => 'DEF',
                    'r' => null,
                    'w' => null,
                    'r_transl' => '1',
                    'w_transl' => '0',
                    'r_rev' => null,
                    'w_rev' => null,
                    'source' => null,
                    'target' => null
                ],
                [
                    'tm' => true,
                    'glos' => true,
                    'owner' => false,
                    'uid_transl' => null,
                    'uid_rev' => null,
                    'name' => 'My personal Key',
                    'key' => 'GHI',
                    'r' => null,
                    'w' => null,
                    'r_transl' => '1',
                    'w_transl' => '1',
                    'r_rev' => null,
                    'w_rev' => null,
                    'source' => null,
                    'target' => null
                ]
            ]
        );

        /**
         * EXPECTED:
         * An array containing one TmKeyStruct object representing:
         * the key 'ABC'
         * DEF key should not be kept because it is not writable even if mine (user 560)
         */
        $tmKeys = null;

        try {
            $tmKeys = TmKeyManager::getJobTmKeys(
                $_jobTmKeys,
                'w',
                'tm',
                560
            );
        } catch (Exception $e) {
            //An error occurred: test failed
            $this->fail();
        }


        // DEF key should not be kept because it is not writable even if mine
        $this->assertCount(1, $tmKeys);

        $fstKey = $tmKeys[0];

        $this->assertTrue($fstKey->owner);
        $this->assertEquals('ABC', $fstKey->key);
        $this->assertEquals(1, $fstKey->r);
        $this->assertEquals(1, $fstKey->w);
        $this->assertNull($fstKey->uid_transl);
        $this->assertNull($fstKey->r_transl);
        $this->assertNull($fstKey->w_transl);
    }

    #[Test]
    public function testGetJobTmKeys_validInput_noRole_Uid_readwriteKeys()
    {
        $_jobTmKeys = json_encode(
            [
                [
                    'tm' => true,
                    'glos' => true,
                    'owner' => true,
                    'uid_transl' => null,
                    'uid_rev' => null,
                    'name' => 'My personal Key',
                    'key' => 'ABC',
                    'r' => '1',
                    'w' => '1',
                    'r_transl' => null,
                    'w_transl' => null,
                    'r_rev' => null,
                    'w_rev' => null,
                    'source' => null,
                    'target' => null
                ],
                [
                    'tm' => true,
                    'glos' => true,
                    'owner' => false,
                    'uid_transl' => 560,
                    'uid_rev' => null,
                    'name' => 'My personal Key',
                    'key' => 'DEF',
                    'r' => null,
                    'w' => null,
                    'r_transl' => '1',
                    'w_transl' => '0',
                    'r_rev' => null,
                    'w_rev' => null,
                    'source' => null,
                    'target' => null
                ],
                [
                    'tm' => true,
                    'glos' => true,
                    'owner' => false,
                    'uid_transl' => null,
                    'uid_rev' => null,
                    'name' => 'My personal Key',
                    'key' => 'GHI',
                    'r' => null,
                    'w' => null,
                    'r_transl' => '1',
                    'w_transl' => '1',
                    'r_rev' => null,
                    'w_rev' => null,
                    'source' => null,
                    'target' => null
                ]
            ]
        );

        /**
         * EXPECTED:
         * An array containing one TmKeyStruct object representing:
         * the key 'ABC'
         * DEF key should not be kept because it is not writable even if mine (user 560)
         */
        $tmKeys = null;

        try {
            $tmKeys = TmKeyManager::getJobTmKeys(
                $_jobTmKeys,
                'rw',
                'tm',
                560
            );
        } catch (Exception $e) {
            //An error occurred: test failed
            $this->fail();
        }

        $this->assertCount(1, $tmKeys);

        /**
         * @var $fstKey TmKeyStruct
         */
        $fstKey = $tmKeys[0];

        $this->assertTrue($fstKey->owner);
        $this->assertEquals('ABC', $fstKey->key);
        $this->assertEquals(1, $fstKey->r);
        $this->assertEquals(1, $fstKey->w);
        $this->assertNull($fstKey->uid_transl);
        $this->assertNull($fstKey->r_transl);
        $this->assertNull($fstKey->w_transl);
    }

    #[Test]
    public function testGetJobTmKeys_validInput_roleTranslator_Uid_readKeys_1()
    {
        $_jobTmKeys = json_encode(
            [
                [
                    'tm' => true,
                    'glos' => true,
                    'owner' => true,
                    'uid_transl' => null,
                    'uid_rev' => null,
                    'name' => 'My personal Key',
                    'key' => 'ABC',
                    'r' => '1',
                    'w' => '1',
                    'r_transl' => null,
                    'w_transl' => null,
                    'r_rev' => null,
                    'w_rev' => null,
                    'source' => null,
                    'target' => null
                ],
                [
                    'tm' => true,
                    'glos' => true,
                    'owner' => false,
                    'uid_transl' => 560,
                    'uid_rev' => null,
                    'name' => 'My personal Key',
                    'key' => 'DEF',
                    'r' => null,
                    'w' => null,
                    'r_transl' => '1',
                    'w_transl' => '0',
                    'r_rev' => null,
                    'w_rev' => null,
                    'source' => null,
                    'target' => null
                ],
                [
                    'tm' => true,
                    'glos' => true,
                    'owner' => false,
                    'uid_transl' => 560,
                    'uid_rev' => null,
                    'name' => 'My personal Key 2',
                    'key' => 'DEF2',
                    'r' => null,
                    'w' => null,
                    'r_transl' => '0',
                    'w_transl' => '1',
                    'r_rev' => null,
                    'w_rev' => null,
                    'source' => null,
                    'target' => null
                ],
                [
                    'tm' => true,
                    'glos' => true,
                    'owner' => false,
                    'uid_transl' => null,
                    'uid_rev' => null,
                    'name' => 'My personal Key',
                    'key' => 'GHI',
                    'r' => null,
                    'w' => null,
                    'r_transl' => '1',
                    'w_transl' => '1',
                    'r_rev' => null,
                    'w_rev' => null,
                    'source' => null,
                    'target' => null
                ]
            ]
        );

        /**
         * EXPECTED:
         * An array containing two TmKeyStruct object representing:
         * the key 'ABC'
         * the key 'DEF'
         */
        $tmKeys = null;

        try {
            $tmKeys = TmKeyManager::getJobTmKeys(
                $_jobTmKeys,
                'r',
                'tm',
                560
            );
        } catch (Exception $e) {
            //An error occurred: test failed
            $this->fail();
        }

        $this->assertCount(2, $tmKeys);

        $fstKey = $tmKeys[0];
        $sndKey = $tmKeys[1];

        $this->assertTrue($fstKey->owner);
        $this->assertEquals('ABC', $fstKey->key);
        $this->assertEquals(1, $fstKey->r);
        $this->assertEquals(1, $fstKey->w);
        $this->assertNull($fstKey->uid_transl);
        $this->assertNull($fstKey->r_transl);
        $this->assertNull($fstKey->w_transl);

        $this->assertFalse($sndKey->owner);
        $this->assertEquals('DEF', $sndKey->key);
        $this->assertNull($sndKey->r);
        $this->assertNull($sndKey->w);
        $this->assertEquals(560, $sndKey->uid_transl);
        $this->assertEquals(1, $sndKey->r_transl);
        $this->assertEquals(0, $sndKey->w_transl);
    }

    #[Test]
    public function testGetJobTmKeys_validInput_roleTranslator_noUid_writeKeys()
    {
        $_jobTmKeys = json_encode(
            [
                [
                    'tm' => true,
                    'glos' => true,
                    'owner' => true,
                    'uid_transl' => null,
                    'uid_rev' => null,
                    'name' => 'My personal Key',
                    'key' => 'ABC',
                    'r' => '1',
                    'w' => '1',
                    'r_transl' => null,
                    'w_transl' => null,
                    'r_rev' => null,
                    'w_rev' => null,
                    'source' => null,
                    'target' => null
                ],
                [
                    'tm' => true,
                    'glos' => true,
                    'owner' => false,
                    'uid_transl' => 560,
                    'uid_rev' => null,
                    'name' => 'My personal Key',
                    'key' => 'DEF',
                    'r' => null,
                    'w' => null,
                    'r_transl' => '1',
                    'w_transl' => '0',
                    'r_rev' => null,
                    'w_rev' => null,
                    'source' => null,
                    'target' => null
                ],
                [
                    'tm' => true,
                    'glos' => true,
                    'owner' => false,
                    'uid_transl' => null,
                    'uid_rev' => null,
                    'name' => 'My personal Key',
                    'key' => 'GHI',
                    'r' => null,
                    'w' => null,
                    'r_transl' => '1',
                    'w_transl' => '1',
                    'r_rev' => null,
                    'w_rev' => null,
                    'source' => null,
                    'target' => null
                ]
            ]
        );

        /**
         * EXPECTED:
         * An array containing two TmKeyStruct object representing:
         * the key 'ABC'
         */
        $tmKeys = null;

        try {
            $tmKeys = TmKeyManager::getJobTmKeys(
                $_jobTmKeys,
                'w',
            );
        } catch (Exception $e) {
            //An error occurred: test failed
            $this->fail();
        }

        $this->assertCount(1, $tmKeys);

        $fstKey = $tmKeys[0];

        $this->assertTrue($fstKey->owner);
        $this->assertEquals('ABC', $fstKey->key);
        $this->assertEquals(1, $fstKey->r);
        $this->assertEquals(1, $fstKey->w);
        $this->assertNull($fstKey->uid_transl);
        $this->assertNull($fstKey->r_transl);
        $this->assertNull($fstKey->w_transl);
    }

    #[Test]
    public function testGetJobTmKeys_validInput_roleTranslator_uid_readKeys()
    {
        $_idTranslator = 560;
        $_jobTmKeys = json_encode(
            [
                [
                    'tm' => true,
                    'glos' => true,
                    'owner' => true,
                    'uid_transl' => null,
                    'uid_rev' => null,
                    'name' => 'My personal Key',
                    'key' => 'ABC',
                    'r' => '1',
                    'w' => '1',
                    'r_transl' => null,
                    'w_transl' => null,
                    'r_rev' => null,
                    'w_rev' => null,
                    'source' => null,
                    'target' => null
                ],
                [
                    'tm' => true,
                    'glos' => true,
                    'owner' => false,
                    'uid_transl' => 560,
                    'uid_rev' => null,
                    'name' => 'My personal Key',
                    'key' => 'DEF',
                    'r' => null,
                    'w' => null,
                    'r_transl' => '1',
                    'w_transl' => '0',
                    'r_rev' => null,
                    'w_rev' => null,
                    'source' => null,
                    'target' => null
                ],
                [
                    'tm' => true,
                    'glos' => true,
                    'owner' => false,
                    'uid_transl' => null,
                    'uid_rev' => null,
                    'name' => 'My personal Key',
                    'key' => 'GHI',
                    'r' => null,
                    'w' => null,
                    'r_transl' => '1',
                    'w_transl' => '1',
                    'r_rev' => null,
                    'w_rev' => null,
                    'source' => null,
                    'target' => null
                ]
            ]
        );

        /**
         * EXPECTED:
         * An array containing two TmKeyStruct object representing:
         * the key 'ABC'
         * the key 'DEF'
         * the key 'GHI'
         */
        $tmKeys = null;

        try {
            $tmKeys = TmKeyManager::getJobTmKeys(
                $_jobTmKeys,
                'r',
                'tm',
                $_idTranslator,
            );
        } catch (Exception $e) {
            //An error occurred: test failed
            $this->fail();
        }

        $this->assertCount(2, $tmKeys);

        $fstKey = $tmKeys[0];
        $sndKey = $tmKeys[1];


        $this->assertTrue($fstKey->owner);
        $this->assertEquals('ABC', $fstKey->key);
        $this->assertEquals(1, $fstKey->r);
        $this->assertEquals(1, $fstKey->w);
        $this->assertNull($fstKey->uid_transl);
        $this->assertNull($fstKey->r_transl);
        $this->assertNull($fstKey->w_transl);

        $this->assertFalse($sndKey->owner);
        $this->assertEquals('DEF', $sndKey->key);
        $this->assertNull($sndKey->r);
        $this->assertNull($sndKey->w);
        $this->assertEquals($_idTranslator, $sndKey->uid_transl);
        $this->assertEquals(1, $sndKey->r_transl);
        $this->assertEquals(0, $sndKey->w_transl);
    }

    #[Test]
    public function testGetJobTmKeys_validInput_roleTranslator_uid_writeKeys()
    {
        $_idTranslator = 560;
        $_jobTmKeys = json_encode(
            [
                [
                    'tm' => true,
                    'glos' => true,
                    'owner' => true,
                    'uid_transl' => null,
                    'uid_rev' => null,
                    'name' => 'My personal Key',
                    'key' => 'ABC',
                    'r' => '1',
                    'w' => '1',
                    'r_transl' => null,
                    'w_transl' => null,
                    'r_rev' => null,
                    'w_rev' => null,
                    'source' => null,
                    'target' => null
                ],
                [
                    'tm' => true,
                    'glos' => true,
                    'owner' => false,
                    'uid_transl' => 560,
                    'uid_rev' => null,
                    'name' => 'My personal Key',
                    'key' => 'DEF',
                    'r' => null,
                    'w' => null,
                    'r_transl' => '1',
                    'w_transl' => '0',
                    'r_rev' => null,
                    'w_rev' => null,
                    'source' => null,
                    'target' => null
                ],
                [
                    'tm' => true,
                    'glos' => true,
                    'owner' => false,
                    'uid_transl' => null,
                    'uid_rev' => null,
                    'name' => 'My personal Key',
                    'key' => 'GHI',
                    'r' => null,
                    'w' => null,
                    'r_transl' => '1',
                    'w_transl' => '1',
                    'r_rev' => null,
                    'w_rev' => null,
                    'source' => null,
                    'target' => null
                ]
            ]
        );

        /**
         * EXPECTED:
         * An array containing two TmKeyStruct object representing:
         * the key 'ABC'
         * the key 'GHI'
         */
        $tmKeys = null;

        try {
            $tmKeys = TmKeyManager::getJobTmKeys(
                $_jobTmKeys,
                'w',
                'tm',
                $_idTranslator,
            );
        } catch (Exception $e) {
            //An error occurred: test failed
            $this->fail();
        }

        $this->assertCount(1, $tmKeys);

        $fstKey = $tmKeys[0];

        $this->assertTrue($fstKey->owner);
        $this->assertEquals('ABC', $fstKey->key);
        $this->assertEquals(1, $fstKey->r);
        $this->assertEquals(1, $fstKey->w);
        $this->assertNull($fstKey->uid_transl);
        $this->assertNull($fstKey->r_transl);
        $this->assertNull($fstKey->w_transl);
    }

    #[Test]
    public function testGetJobTmKeys_validInput_roleTranslator_uid_readwriteKeys()
    {
        $_idTranslator = 560;
        $_jobTmKeys = json_encode(
            [
                [
                    'tm' => true,
                    'glos' => true,
                    'owner' => true,
                    'uid_transl' => null,
                    'uid_rev' => null,
                    'name' => 'My personal Key',
                    'key' => 'ABC',
                    'r' => '1',
                    'w' => '1',
                    'r_transl' => null,
                    'w_transl' => null,
                    'r_rev' => null,
                    'w_rev' => null,
                    'source' => null,
                    'target' => null
                ],
                [
                    'tm' => true,
                    'glos' => true,
                    'owner' => false,
                    'uid_transl' => 560,
                    'uid_rev' => null,
                    'name' => 'My personal Key',
                    'key' => 'DEF',
                    'r' => null,
                    'w' => null,
                    'r_transl' => '1',
                    'w_transl' => '0',
                    'r_rev' => null,
                    'w_rev' => null,
                    'source' => null,
                    'target' => null
                ],
                [
                    'tm' => true,
                    'glos' => true,
                    'owner' => false,
                    'uid_transl' => null,
                    'uid_rev' => null,
                    'name' => 'My personal Key',
                    'key' => 'GHI',
                    'r' => null,
                    'w' => null,
                    'r_transl' => '1',
                    'w_transl' => '1',
                    'r_rev' => null,
                    'w_rev' => null,
                    'source' => null,
                    'target' => null
                ]
            ]
        );

        /**
         * EXPECTED:
         * An array containing one TmKeyStruct object representing:
         * the key 'ABC'
         *
         * The key 'DEF' has no write permissions even if mine
         */
        $tmKeys = null;

        try {
            $tmKeys = TmKeyManager::getJobTmKeys(
                $_jobTmKeys,
                'rw',
                'tm',
                $_idTranslator
            );
        } catch (Exception $e) {
            //An error occurred: test failed
            $this->fail();
        }

        $this->assertCount(1, $tmKeys);

        $fstKey = $tmKeys[0];

        $this->assertTrue($fstKey->owner);
        $this->assertEquals('ABC', $fstKey->key);
        $this->assertEquals(1, $fstKey->r);
        $this->assertEquals(1, $fstKey->w);
        $this->assertNull($fstKey->uid_transl);
        $this->assertNull($fstKey->r_transl);
        $this->assertNull($fstKey->w_transl);
    }

    #[Test]
    public function testGetJobTmKeys_invalidJson()
    {
        try {
            TmKeyManager::getJobTmKeys(self::$invalidJsonStringTmKeyList);
        } catch (Exception $e) {
            $invalidJSON_position = strpos($e->getMessage(), "Syntax error");

            $this->assertTrue($invalidJSON_position > -1);
        }
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testGetJobTmKeys_wGrant()
    {
        $tmKeys = TmKeyManager::getJobTmKeys(self::$validJsonStringTmKeyList, 'w');

        $this->assertNotNull($tmKeys);
        $this->assertEmpty($tmKeys);
    }

    #[Test]
    public function testGetJobTmKeys_invalidGrant()
    {
        try {
            TmKeyManager::getJobTmKeys(self::$validJsonStringTmKeyList, self::$invalidGrantString);
        } catch (Exception $e) {
            $invalidGrantPosition = strpos($e->getMessage(), "Invalid grant string.");

            $this->assertTrue($invalidGrantPosition > -1);
        }
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testGetJobTmKeys_glosType()
    {
        $tmKeys = TmKeyManager::getJobTmKeys(
            self::$validJsonStringTmKeyList,
            'rw',
            'glos',
        );

        $this->assertNotNull($tmKeys);
        $this->assertEmpty($tmKeys);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testGetJobTmKeys_revisorRole()
    {
        $tmKeys = TmKeyManager::getJobTmKeys(
            self::$validJsonStringTmKeyList,
            'rw',
            'glos',
            null,
            Filter::ROLE_REVISOR
        );

        $this->assertNotNull($tmKeys);
        $this->assertEmpty($tmKeys);
    }

    #[Test]
    public function testGetJobTmKeys_invalidType()
    {
        try {
            TmKeyManager::getJobTmKeys(
                self::$validJsonStringTmKeyList,
                'rw',
                self::$invalidTypeString
            );
        } catch (Exception $e) {
            $invalidGrantPosition = strpos($e->getMessage(), "Invalid type string.");

            $this->assertTrue($invalidGrantPosition > -1);
        }
    }

    #[Test]
    public function testGetJobTmKeys_invalidRole()
    {
        try {
            TmKeyManager::getJobTmKeys(
                self::$validJsonStringTmKeyList,
                'rw',
                'tm',
                null,
                self::$invalidRoleString
            );
        } catch (Exception $e) {
            $invalidFilterPosition = strpos($e->getMessage(), "Filter type");

            $this->assertTrue($invalidFilterPosition > -1);
        }
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testGetJobTmKeys_uidNotNull()
    {
        $tmKeys = TmKeyManager::getJobTmKeys(
            self::$validJsonStringTmKeyListWithUidTranslator,
            'rw',
            'tm',
            self::$uid_translator,
        );

        $this->assertNotNull($tmKeys);

        $this->assertNotEmpty($tmKeys);
        $this->assertInstanceOf(TmKeyStruct::class, $tmKeys[0]);
    }

    /** TEST isValidStructure */
    #[Test]
    public function testIsValidStructure_validStructure()
    {
        $validObj = TmKeyManager::isValidStructure(self::$validTmKeyStructArr);

        $this->assertInstanceOf(TmKeyStruct::class, $validObj);
        $this->assertEquals(self::$validTmKeyStructArr['key'], $validObj->key);
        $this->assertEquals(self::$validTmKeyStructArr['owner'], $validObj->owner);
        $this->assertEquals(self::$validTmKeyStructArr['r'], $validObj->r);
        $this->assertEquals(self::$validTmKeyStructArr['w'], $validObj->w);
        $this->assertEquals(self::$validTmKeyStructArr['r_transl'], $validObj->r_transl);
        $this->assertEquals(self::$validTmKeyStructArr['w_transl'], $validObj->w_transl);
        $this->assertEquals(self::$validTmKeyStructArr['uid_transl'], $validObj->uid_transl);
    }

    #[Test]
    public function testIsValidStructure_invalidStructure()
    {
        $validObj = TmKeyManager::isValidStructure(self::$invalidTmKeyStructArr);

        $this->assertNotNull($validObj);
        $this->assertNull($validObj->invalidField ?? null);
    }

    /** TEST mergeJsonKeys */
    #[Test]
    public function testMergeJsonKeys_invalidClientJson()
    {
        try {
            TmKeyManager::mergeJsonKeys(self::$invalidClientJson, self::$validServerJson);
        } catch (Exception $e) {
            $this->assertTrue($e->getCode() > 0);
        }
    }

    #[Test]
    public function testMergeJsonKeys_invalidServerJson()
    {
        try {
            TmKeyManager::mergeJsonKeys(self::$validClientJson, self::$invalidServerJson);
        } catch (Exception $e) {
            $this->assertTrue($e->getCode() > 0);
        }
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testMergeJsonKeys_validInput_clientABCDEF_serverABC()
    {
        $resultMerge = TmKeyManager::mergeJsonKeys(
            self::$client_json_ABC_DEF,
            self::$srv_json_ABC,
            Filter::ROLE_TRANSLATOR,
            123
        );

        /*
         * We expect this result
         *
         *   [
         *
         *   {"tm":true,"glos":false,"owner":true,"key":"0000123ABC","name":"My ABC","r":"1","w":"1","uid_transl":123,"uid_rev":null,"r_transl":1,"w_transl":1,"r_rev":null,"w_rev":null,"source":null,"target":null},
         *
         *   {"tm":true,"glos":false,"owner":false,"key":"0000123DEF","name":"My DEF","r":null,"w":null,"uid_transl":123,"uid_rev":null,"r_transl":1,"w_transl":0,"r_rev":null,"w_rev":null,"source":null,"target":null}
         *
         *   ]
         *
         */

        $this->assertCount(2, $resultMerge);

        /**
         * @var $firstKey TmKeyStruct
         */
        $firstKey = $resultMerge[0];

        /**
         * @var $secondKey TmKeyStruct
         */
        $secondKey = $resultMerge[1];

        $this->assertEquals("0000123ABC", $firstKey->key);
        $this->assertEquals(123, $firstKey->uid_transl);
        $this->assertEquals(1, $firstKey->r_transl);
        $this->assertEquals(1, $firstKey->w_transl);
        $this->assertEquals(1, $firstKey->owner);
        $this->assertEquals(1, $firstKey->r);
        $this->assertEquals(1, $firstKey->w);

        $this->assertEquals("0000123DEF", $secondKey->key);
        $this->assertEquals(123, $secondKey->uid_transl);
        $this->assertEquals(1, $secondKey->r_transl);
        $this->assertEquals(0, $secondKey->w_transl);
        $this->assertEquals(0, $secondKey->owner);
        $this->assertNull($secondKey->r);
        $this->assertNull($secondKey->w);
    }

    /**
     * Same as preceding but anonymous
     *
     * @throws Exception
     */
    #[Test]
    public function testMergeJsonKeys_validInput_clientGHI_Anonymous_serverGHI()
    {
        $resultMerge = TmKeyManager::mergeJsonKeys(
            self::$client_json_GHI,
            self::$srv_json_GHI,
        );

        /*
         * We expect this result
         *
         *   [
         *
         *   {"tm":true,"glos":false,"owner":true,"key":"0000123GHI","name":"My GHI","r":"1","w":"1","uid_transl":null,"uid_rev":null,"r_transl":false,"w_transl":true,"r_rev":null,"w_rev":null,"source":null,"target":null}
         *
         *   ]
         *
         */

        $this->assertCount(1, $resultMerge);

        /**
         * @var $firstKey TmKeyStruct
         */
        $firstKey = $resultMerge[0];

        $this->assertEquals("0000123GHI", $firstKey->key);
        $this->assertNull($firstKey->uid_transl);
        $this->assertEquals(0, $firstKey->r_transl);
        $this->assertEquals(1, $firstKey->w_transl);
        $this->assertEquals(1, $firstKey->owner);
        $this->assertEquals(1, $firstKey->r);
        $this->assertEquals(1, $firstKey->w);
    }

    #[Test]
    public function testMergeJsonKeys_validInput_clientGHI_INVALID_serverGHI()
    {
        $this->expectException('Exception');
        $this->expectExceptionMessage("Please, select Lookup and/or Update to activate your TM in this project");
        TmKeyManager::mergeJsonKeys(
            self::$client_json_INVALID_GHI,
            self::$srv_json_GHI
        );
    }

    #[Test]
    public function testMergeJsonKeys_InvalidRole()
    {
        $this->expectException('Exception');
        $this->expectExceptionMessage("Invalid Role Type string.");
        TmKeyManager::mergeJsonKeys(
            self::$client_json_GHI,
            self::$srv_json_GHI,
            'invalid role!',
            123
        );
    }

    #[Test]
    public function testMergeJsonKeys_InvalidAnonymousOWNER()
    {
        $this->expectException('Exception');
        $this->expectExceptionMessage("Anonymous user can not be OWNER");
        TmKeyManager::mergeJsonKeys(
            self::$client_json_GHI,
            self::$srv_json_GHI,
            Filter::OWNER
        );
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testMergeJsonKeys_OWNER_DELETE_key()
    {
        $resultMerge = TmKeyManager::mergeJsonKeys(
            '[]',
            self::$srv_json_GHI,
            Filter::OWNER,
            123
        );

        $this->assertCount(0, $resultMerge);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testMergeJsonKeys_OWNER_clientGHI_serverGHI()
    {
        $resultMerge = TmKeyManager::mergeJsonKeys(
            self::$client_json_GHI,
            self::$srv_json_GHI,
            Filter::OWNER,
            123
        );

        /*
         * Expected results
         *   [
         *
         *   {"tm":true,"glos":false,"owner":true,"key":"0000123GHI","name":"My GHI","r":"0","w":"1","uid_transl":null,"uid_rev":null,"r_transl":null,"w_transl":null,"r_rev":null,"w_rev":null,"source":null,"target":null}
         *
         *   ]
         *
         */

        $this->assertCount(1, $resultMerge);

        /**
         * @var $firstKey TmKeyStruct
         */
        $firstKey = $resultMerge[0];


        $this->assertEquals("0000123GHI", $firstKey->key);
        $this->assertNull($firstKey->r_transl);
        $this->assertNull($firstKey->w_transl);
        $this->assertEquals(1, $firstKey->owner);
        $this->assertEquals(0, $firstKey->r);
        $this->assertEquals(1, $firstKey->w);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testMergeJsonKeys_validInput_clientGHIDEF_serverGHI()
    {
        $resultMerge = TmKeyManager::mergeJsonKeys(
            self::$client_json_GHI_DEF,
            self::$srv_json_GHI
        );

        $this->assertCount(2, $resultMerge);

        /**
         * @var $firstKey TmKeyStruct
         */
        $firstKey = $resultMerge[0];

        /**
         * @var $secondKey TmKeyStruct
         */
        $secondKey = $resultMerge[1];

        $this->assertEquals("0000123GHI", $firstKey->key);
        $this->assertNull($firstKey->r_transl);
        $this->assertNull($firstKey->w_transl);
        $this->assertEquals(1, $firstKey->owner);
        $this->assertEquals(1, $firstKey->r);
        $this->assertEquals(1, $firstKey->w);

        $this->assertEquals("0000123DEF", $secondKey->key);
        $this->assertEquals(1, $secondKey->r_transl);
        $this->assertEquals(0, $secondKey->w_transl);
        $this->assertEquals(0, $secondKey->owner);
        $this->assertNull($secondKey->r);
        $this->assertNull($secondKey->w);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testMergeJsonKeys_validInput_clientABC_serverABC()
    {
        $resultMerge = TmKeyManager::mergeJsonKeys(
            self::$client_json_ABC,
            self::$srv_json_ABC,
            Filter::ROLE_TRANSLATOR,
            123
        );

        $this->assertCount(1, $resultMerge);

        /**
         * @var $firstKey TmKeyStruct
         */
        $firstKey = $resultMerge[0];

        $this->assertEquals("0000123ABC", $firstKey->key);
        $this->assertEquals(1, $firstKey->r_transl);
        $this->assertEquals(1, $firstKey->w_transl);
        $this->assertEquals(1, $firstKey->owner);
        $this->assertEquals(1, $firstKey->r);
        $this->assertEquals(1, $firstKey->w);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testMergeJsonKeys_validInput_clientDEF_serverABC()
    {
        /*
         * client: '[{"key":"0000123DEF","name":"My DEF","r":1,"w":0}]'
         * server: '[{"tm":true,"glos":false,"owner":true,"key":"0000123ABC","name":"","r":"1","w":"1","uid_transl":123,"uid_rev":null,"r_transl":1,"w_transl":0,"r_rev":null,"w_rev":null,"source":null,"target":null}]'
         */

        /*
         * Expected Result because we can't change the owner key position
         *
         * [
         *      {"tm":true,"glos":false,"owner":true,"key":"0000123ABC","name":"","r":"1","w":"1","uid_transl":123,"uid_rev":null,"r_transl":1,"w_transl":0,"r_rev":null,"w_rev":null,"source":null,"target":null}
         *      {"tm":true,"glos":false,"owner":false,"key":"0000123DEF","name":"","r":null,"w":null,"uid_transl":123,"uid_rev":null,"r_transl":1,"w_transl":0,"r_rev":null,"w_rev":null,"source":null,"target":null}
         * ]
         *
         */

        $resultMerge = TmKeyManager::mergeJsonKeys(
            self::$client_json_DEF,
            self::$srv_json_ABC,
            Filter::ROLE_TRANSLATOR,
            123
        );

        $this->assertCount(2, $resultMerge);
    }

    #[Test]
    public function testMergeJsonKeys_validInput_clientABCGHIJKL_serverABCGHIDEF_anonymous()
    {
        //ABC Key and GHI key already present in job, they can not be modified by an anonymous user
        $this->expectException('Exception');
        $this->expectExceptionMessage("Anonymous user can not modify existent keys.");

        //this should not to be, because a client can not send not hashed keys
        //already present in job as an anonymous user
        TmKeyManager::mergeJsonKeys(
            self::$client_json_ABC_GHI_JKL,
            self::$srv_json_ABC_GHI_DEF
        );
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    #[Test]
    public function testMergeJsonKeys_validInput_clientABCGHIJKL_serverABCGHIDEF()
    {
        $db = Database::obtain();
        $db->getConnection()->query("TRUNCATE TABLE memory_keys");

        //this should not to be, because a client can not send not hashed keys
        //already present in job as an anonymous user
        $resultMerge = TmKeyManager::mergeJsonKeys(
            self::$client_json_ABC_GHI_JKL,
            self::$srv_json_ABC_GHI_DEF,
            Filter::ROLE_TRANSLATOR,
            123
        );

        $MemoryDao = new MemoryKeyDao(Database::obtain());
        $dh = new MemoryKeyStruct([
            'uid' => 123
        ]);

        $insertedKeys = $MemoryDao->read($dh);

        $this->assertCount(1, $insertedKeys);
        $this->assertEquals('0000123JKL', $insertedKeys[0]->tm_key->key);
        $this->assertEquals('123', $insertedKeys[0]->uid);

        /*
         * we expect this result
         *
         *   [
         *
         *   {"tm":true,"glos":false,"owner":true,"key":"0000123ABC","name":"My ABC","r":"1","w":"1","uid_transl":123,"uid_rev":null,"r_transl":1,"w_transl":1,"r_rev":null,"w_rev":null,"source":null,"target":null},
         *
         *   {"tm":true,"glos":false,"owner":true,"key":"0000123GHI","name":"My GHI","r":"1","w":"1","uid_transl":null,"uid_rev":null,"r_transl":null,"w_transl":null,"r_rev":null,"w_rev":null,"source":null,"target":null},
         *
         *   {"tm":true,"glos":false,"owner":false,"key":"0000123JKL","name":"My JKL","r":null,"w":null,"uid_transl":123,"uid_rev":null,"r_transl":1,"w_transl":0,"r_rev":null,"w_rev":null,"source":null,"target":null}
         *
         *   ]
         *
         */

        $this->assertCount(3, $resultMerge);

        /**
         * @var $firstKey TmKeyStruct
         */
        $firstKey = $resultMerge[0];

        /**
         * @var $secondKey TmKeyStruct
         */
        $secondKey = $resultMerge[1];

        /**
         * @var $thirdKey TmKeyStruct
         */
        $thirdKey = $resultMerge[2];


        $this->assertEquals("0000123ABC", $firstKey->key);
        $this->assertTrue($firstKey->r_transl);
        $this->assertTrue($firstKey->w_transl);
        $this->assertEquals(123, $firstKey->uid_transl);
        $this->assertEquals(1, $firstKey->owner);
        $this->assertEquals(1, $firstKey->r);
        $this->assertEquals(1, $firstKey->w);


        //This key must be untouched because client sent hashed
        $this->assertEquals("0000123GHI", $secondKey->key);
        $this->assertNull($secondKey->r_transl);
        $this->assertNull($secondKey->w_transl);
        $this->assertNull($secondKey->uid_transl);
        $this->assertEquals(1, $secondKey->owner);
        $this->assertEquals(1, $secondKey->r);
        $this->assertEquals(1, $secondKey->w);

        $this->assertEquals("0000123JKL", $thirdKey->key);
        $this->assertTrue($thirdKey->r_transl);
        if (version_compare(PHP_VERSION, 5.4) >= 0) {
            $this->assertFalse($thirdKey->w_transl);
        } else {
            $this->assertNull($thirdKey->w_transl);
        }
        $this->assertEquals(123, $thirdKey->uid_transl);
        $this->assertFalse($thirdKey->owner);
        $this->assertNull($thirdKey->r);
        $this->assertNull($thirdKey->w);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testOrdering_1()
    {
        //CLIENT changes the position of a key upward and remove a key, also change permissions, add a name
        $client_json = '[
                            {"key":"0000123MNO","name":"My MNO","r":1,"w":0},
                            {"key":"0000123ABC","name":"My ABC","r":0,"w":1},
                            {"key":"0000123GHI","name":"My GHI","r":0,"w":1}
                        ]';

        $server_json = '[
                            {"tm":true,"glos":false,"owner":true,"key":"0000123MNO","name":"My MNO","r":"1","w":"1","uid_transl":123,"uid_rev":null,"r_transl":0,"w_transl":1,"r_rev":null,"w_rev":null,"source":null,"target":null},
                            {"tm":true,"glos":false,"owner":false,"key":"0000123GHI","name":"My GHI","r":null,"w":null,"uid_transl":123,"uid_rev":null,"r_transl":1,"w_transl":0,"r_rev":null,"w_rev":null,"source":null,"target":null},
                            {"tm":true,"glos":false,"owner":false,"key":"0000123DEF","name":"My DEF","r":null,"w":null,"uid_transl":123,"uid_rev":456,"r_transl":1,"w_transl":1,"r_rev":1,"w_rev":1,"source":null,"target":null},
                            {"tm":true,"glos":false,"owner":false,"key":"0000123ABC","name":"","r":null,"w":null,"uid_transl":123,"uid_rev":456,"r_transl":1,"w_transl":0,"r_rev":1,"w_rev":1,"source":null,"target":null}
                        ]';

        /*
         * Expected behaviour
         *
         *   [
         *       {"tm":true,"glos":false,"owner":true,"key":"0000123MNO","name":"My MNO","r":"1","w":"1","uid_transl":123,"uid_rev":null,"r_transl":1,"w_transl":0,"r_rev":null,"w_rev":null,"source":null,"target":null},
         *       {"tm":true,"glos":false,"owner":false,"key":"0000123ABC","name":"My ABC","r":null,"w":null,"uid_transl":123,"uid_rev":null,"r_transl":0,"w_transl":1,"r_rev":null,"w_rev":null,"source":null,"target":null}
         *       {"tm":true,"glos":false,"owner":false,"key":"0000123GHI","name":"My GHI","r":null,"w":null,"uid_transl":123,"uid_rev":null,"r_transl":0,"w_transl":1,"r_rev":null,"w_rev":null,"source":null,"target":null},
         *       {"tm":true,"glos":false,"owner":false,"key":"0000123DEF","name":"My DEF","r":null,"w":null,"uid_transl":null,"uid_rev":456,"r_transl":null,"w_transl":null,"r_rev":1,"w_rev":1,"source":null,"target":null},
         *   ]
         *
         */

        //this should not to be, because a client can not send not hashed keys
        //already present in job as an anonymous user
        $resultMerge = TmKeyManager::mergeJsonKeys(
            $client_json,
            $server_json,
            Filter::ROLE_TRANSLATOR,
            123
        );

        $this->assertCount(3, $resultMerge);

        /**
         * @var $firstKey TmKeyStruct
         */
        $firstKey = $resultMerge[0];

        /**
         * @var $secondKey TmKeyStruct
         */
        $secondKey = $resultMerge[1];

        /**
         * @var $thirdKey TmKeyStruct
         */
        $thirdKey = $resultMerge[2];

        $this->assertEquals("0000123MNO", $firstKey->key);
        $this->assertTrue($firstKey->r_transl);
        if (version_compare(PHP_VERSION, 5.4) >= 0) {
            $this->assertFalse($firstKey->w_transl);
        } else {
            $this->assertNull($firstKey->w_transl);
        }
        $this->assertEquals("My MNO", $firstKey->name);
        $this->assertEquals(123, $firstKey->uid_transl);
        $this->assertEquals(1, $firstKey->owner);
        $this->assertEquals(1, $firstKey->r);
        $this->assertEquals(1, $firstKey->w);


        //This key must be untouched because client sent hashed
        $this->assertEquals("0000123ABC", $secondKey->key);
        if (version_compare(PHP_VERSION, 5.4) >= 0) {
            $this->assertFalse($secondKey->r_transl);
        } else {
            $this->assertNull($secondKey->r_transl);
        }
        $this->assertTrue($secondKey->w_transl);
        $this->assertEquals(123, $secondKey->uid_transl);
        $this->assertEquals("My ABC", $secondKey->name);
        $this->assertEquals(0, $secondKey->owner);
        $this->assertEquals(0, $secondKey->r);
        $this->assertEquals(0, $secondKey->w);

        $this->assertEquals("0000123GHI", $thirdKey->key);
        if (version_compare(PHP_VERSION, 5.4) >= 0) {
            $this->assertFalse($thirdKey->r_transl);
        } else {
            $this->assertNull($thirdKey->r_transl);
        }
        $this->assertTrue($thirdKey->w_transl);
        $this->assertEquals(123, $thirdKey->uid_transl);
        $this->assertEquals("My GHI", $thirdKey->name);
        $this->assertEquals(0, $thirdKey->owner);
        $this->assertEquals(0, $thirdKey->r);
        $this->assertEquals(0, $thirdKey->w);
    }

    #[Test]
    public function testUniqueKeys()
    {
        $client_json = '[
                            {"key":"0000123MNO","name":"My MNO","r":1,"w":0},
                            {"key":"0000123ABC","name":"My ABC","r":0,"w":1},
                            {"key":"*****23ABC","name":"My ABC","r":0,"w":1}
                        ]';

        $server_json = '[
                            {"tm":true,"glos":false,"owner":true,"key":"0000123MNO","name":"My MNO","r":"1","w":"1","uid_transl":123,"uid_rev":null,"r_transl":0,"w_transl":1,"r_rev":null,"w_rev":null,"source":null,"target":null},
                            {"tm":true,"glos":false,"owner":false,"key":"0000123ABC","name":"","r":null,"w":null,"uid_transl":123,"uid_rev":456,"r_transl":1,"w_transl":0,"r_rev":1,"w_rev":1,"source":null,"target":null}
                        ]';

        /*
         * Expected Exception because the same key can not be sent twice
         *
         */

        $this->expectException('Exception');
        $this->expectExceptionMessage("A key is already present in this project.");
        $this->expectExceptionCode(5);

        TmKeyManager::mergeJsonKeys(
            $client_json,
            $server_json,
            Filter::ROLE_TRANSLATOR,
            123
        );
    }

    #[Test]
    public function testJsonSerialization()
    {
        $client_json = '[{"key":"0000123MNO","name":"My MNO","r":1,"w":0}]';
        $result_arr = array_map([TmKeyManager::class, 'getTmKeyStructure'], json_decode($client_json, true));

        $this->assertTrue(strpos(json_encode($result_arr), 'u0000readable_chars') === false);
        $this->assertTrue(strpos(json_encode($result_arr[0]->toArray()), 'u0000readable_chars') === false);
    }

    /**
     * Test: Result is a list and do not contains holes and start with 0
     * @test
     * @throws Exception
     */
    #[Test]
    public function ownerKeysShouldBeAList()
    {
        if (!function_exists('array_is_list')) {
            function array_is_list(array $arr): bool
            {
                if ($arr === []) {
                    return true;
                }

                return array_keys($arr) === range(0, count($arr) - 1);
            }
        }

        $jobKeys = '[{"tm":true,"glos":true,"owner":true,"uid_transl":null,"uid_rev":null,"name":"vuota 4","key":"cXXXXXXXXXXXXae68","r":1,"w":1,"r_transl":null,"w_transl":null,"r_rev":null,"w_rev":null,"is_shared":false,"is_private":false},{"tm":true,"glos":true,"owner":true,"uid_transl":null,"uid_rev":null,"name":"","key":"baYYYYYYYYYYYY91d","r":1,"w":0,"r_transl":null,"w_transl":null,"r_rev":null,"w_rev":null,"is_shared":false,"is_private":false},{"tm":true,"glos":true,"owner":true,"uid_transl":null,"uid_rev":null,"name":"Test","key":"fe88ZZZZZZZZZZZZ79c","r":1,"w":0,"r_transl":null,"w_transl":null,"r_rev":null,"w_rev":null,"is_shared":false,"is_private":false},{"tm":true,"glos":true,"owner":true,"uid_transl":null,"uid_rev":null,"name":"","key":"a7dWWWWWWWWWWW7b3","r":1,"w":1,"r_transl":null,"w_transl":null,"r_rev":null,"w_rev":null,"is_shared":false,"is_private":false}]';
        $keys = TmKeyManager::getOwnerKeys([$jobKeys], 'w');

        $this->assertTrue(Utils::array_is_list($keys));
    }

}
