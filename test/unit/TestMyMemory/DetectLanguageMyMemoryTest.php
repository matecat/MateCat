<?php

/**
 * @group regression
 * @covers Engines_MyMemory::detectLanguage
 * User: dinies
 * Date: 24/05/16
 * Time: 18.21
 */
class DetectLanguageMyMemoryTest extends AbstractTest
{


    public function test_detectLanguage()
    {

        $lang_detect_files =
            Array
            (
                'WhiteHouse.doc.sdlxliff' => "detect"
            );




        $fid= 99;
        $strips=  array (
            0 => '(\'808b9638-ef7b-4e6b-89f8-87bfede8b05c\',7,NULL,\'<g id=\\"pt2\\">WASHINGTON </g><g id=\\"pt3\\">— The Treasury Department and Internal Revenue Service today requested public comment on issues relating to the shared responsibility provisions included in the Affordable Care Act that will apply to certain employers starting in 2014.</g>\',\'0f9451f30ba062de262d665a53fb16e3\',35,\'1\',\'<g id=\\"pt1\\">\',\'</g>\',1,\'\',\'\')',
            1 => '(\'84199992-566e-42f5-b8a9-f768919892cd\',7,NULL,\'Under the Affordable Care Act, employers with 50 or more full-time employees that do not offer affordable health coverage to their full-time employees may be required to make a shared responsibility payment.\',\'689c9e816ee6a66cdcc453f7375d5abe\',32,\'2\',\'<g id=\\"pt4\\"><g id=\\"pt5\\">\',\'\',1,\'\',\'\')',
            2 => '(\'84199992-566e-42f5-b8a9-f768919892cd\',7,NULL,\'The law specifically exempts small firms that have fewer than 50 full-time employees.\',\'13559a5ceb53eb9b98ccdf1d12421665\',13,\'3\',\'  \',\'\',1,\'\',\'\')',
            3 => '(\'84199992-566e-42f5-b8a9-f768919892cd\',7,NULL,\'This provision takes effect in 2014.\',\'3345f6c45bea8e88b887c1b28ebe32ba\',6,\'4\',\' \',\'</g></g>\',1,\'\',\'\')',
            4 => '(\'6645b356-9fb8-4cf2-882d-c469ac28d42e\',7,NULL,\'Notice 2011-36, posted today on IRS.gov, solicits public input and comment on several issues that will be the subject of future proposed guidance as Treasury and the IRS work to provide information to employers on how to comply with the shared responsibility provisions.\',\'972bc7c4f1b7afedf94b8f8f99d853d9\',44,\'5\',\'<g id=\\"pt6\\"><g id=\\"pt7\\">\',\'\',1,\'\',\'\')',
            5 => '(\'6645b356-9fb8-4cf2-882d-c469ac28d42e\',7,NULL,\'In particular, the notice requests comment on possible approaches employers could use to determine who is a full-time employee.\',\'d6a8c01a8c07472bb21f782b860b7980\',19,\'6\',\'  \',\' </g></g>\',1,\'\',\'\')',
            6 => '(\'2ab2b47f-b2c1-4d7f-b59f-318665273b9e\',7,NULL,\'Today’s request for comment is designed to ensure that Treasury and IRS continue to receive broad input from stakeholders on how best to implement the shared responsibility provisions in a way that is workable and administrable for employers, allowing them flexibility and minimizing  burdens.\',\'fae06ab82d1a4d3f2ab83676cc536269\',45,\'7\',\'<g id=\\"pt8\\"><g id=\\"pt9\\">\',\'\',1,\'\',\'\')',
            7 => '(\'2ab2b47f-b2c1-4d7f-b59f-318665273b9e\',7,NULL,\'Employers have asked for guidance on this provision, and a number of stakeholder groups have approached Treasury and IRS with information and initial suggestions, which have been taken into account in developing today’s notice.\',\'139aa30acaff15b9d5f0310f676cc1a4\',35,\'8\',\'  \',\'\',1,\'\',\'\')',
            8 => '(\'2ab2b47f-b2c1-4d7f-b59f-318665273b9e\',7,NULL,\'By soliciting comments and feedback now, Treasury and IRS are giving all interested parties the opportunity for input before proposed regulations are issued at a later date.\',\'e2533b722f7497598b8d54824b71d05e\',27,\'9\',\'  \',\'</g></g>\',1,\'\',\'\')',
            9 => '(\'a84fe41b-9f57-4812-bfa4-85dba634adfb\',7,NULL,\'Consistent with the coordinated approach the Departments of Treasury, Labor, and Health and Human Services are taking in developing the regulations and other guidance under the Affordable Care Act, the notice also solicits input on how the three Departments should interpret and apply the Act’s provisions limiting the ability of plans and issuers to impose a waiting period for health coverage of longer than 90 days starting in 2014.\',\'5e1baea9e1b4014e300206885c98e017\',70,\'10\',\'<g id=\\"pt10\\"><g id=\\"pt11\\">\',\'\',1,\'\',\'\')',
            10 => '(\'a84fe41b-9f57-4812-bfa4-85dba634adfb\',7,NULL,\'In addition, the notice invites comment on how guidance under the 90-day provisions should be coordinated with the rules Treasury and IRS will propose regarding the shared responsibility provisions.\',\'918d34a7f0761b6101ee0d2d75428053\',29,\'11\',\'  \',\'</g></g>\',1,\'\',\'\')',
        );


        $x = new RecursiveArrayObject(
            array(
                'segments' => array()
            )
        );
        $x['segments']->offsetSet( $fid, new ArrayObject( array() ) );
        $x['segments'][ $fid ]->exchangeArray( array( $strips ) );

        $engineDAO = new EnginesModel_EngineDAO(Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE ));
        $engine_struct = EnginesModel_EngineStruct::getStruct();
        $engine_struct->id = 1;
        $eng = $engineDAO->read($engine_struct);

        /**
         * @var $engineRecord EnginesModel_EngineStruct
         */
        $engine_struct_param = $eng[0];

        $engine_MyMemory = new Engines_MyMemory($engine_struct_param);
        $result = $engine_MyMemory->detectLanguage($x['segments'], $lang_detect_files);
        $this->assertNotNull($result);
        $this->assertCount(5,$result);
        $this->assertTrue(key_exists('responseData',$result));
        $this->assertTrue(key_exists('responseDetails',$result));
        $this->assertTrue(key_exists('responseStatus',$result));
        $this->assertTrue(key_exists('responderId',$result));
        $this->assertTrue(key_exists('matches',$result));
        $search = $result['responseData'];
        $this->assertTrue(key_exists('translatedText', $search));
        $var = $result['responseData']['translatedText'][0];
        $this->assertEquals("en", $var);
        $responseDetails = $result['responseDetails'];
        $this->assertEquals("", $responseDetails);
        $responseStatus = $result['responseStatus'];
        $this->assertEquals(200, $responseStatus);
        $responderId = $result['responderId'];
        $this->assertEquals("", $responderId);
        $matches = $result['matches'];
        $this->assertEquals("", $matches);



    }

}