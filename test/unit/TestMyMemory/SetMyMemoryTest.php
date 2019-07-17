<?php

/**
 * @group regression
 * @covers Engines_MyMemory::set
 * User: dinies
 * Date: 02/05/16
 * Time: 18.22
 */
class SetMyMemoryTest extends AbstractTest
{

    /**
     * @var EnginesModel_EngineStruct
     */
    protected $engine_struct_param;

    /**
     * @var Engines_MyMemory
     */
    protected $engine_MyMemory;

    protected $reflector;
    protected $property;

    /**
     * @var string
     */
    protected $str_seg_1;
    protected $str_seg_2;
    protected $str_tra_1;
    protected $str_tra_2;

    /**
     * @var EnginesModel_EngineDAO
     */
    protected $engine_DAO;
    /**
     * @var json
     */
    protected $prop;
    /**
     * @var array
     */
    protected $curl_param;
    protected $config_param_of_delete;
    protected $config_param_of_set;


    public function setUp()
    {
        parent::setUp();

        $engineDAO = new EnginesModel_EngineDAO(Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE ));
        $engine_struct = EnginesModel_EngineStruct::getStruct();
        $engine_struct->id = 1;
        $eng = $engineDAO->read($engine_struct);

        /**
         * @var $engineRecord EnginesModel_EngineStruct
         */
        $this->engine_struct_param = $eng[0];


        /**
         * parameters initialization
         */

        $this->str_seg_1 = "Il Sistema genera un numero di serie per quella copia e lo stampa (anche sotto forma di codice a barre) su un’etichetta adesiva.";
        $this->str_seg_2 = <<<'LABEL'
Ad esempio, una copia del film <g id="10">Blade Runner</g> in formato DVD, con numero di serie 6457.
LABEL;

        $this->str_tra_1 = "The system becomes bar and thinks foo.";
        $this->str_tra_2 = <<<'LABEL'
For example, a copy of the film <g id="10">Flade Bunner</g> in DVD format, with numbers of 6457 series.
LABEL;

        $this->prop = <<<'LABEL'
{"project_id":"987654","project_name":"barfoo","job_id":"321"}
LABEL;
        $param_de = "demo@matecat.com";

        $this->config_param_of_set = array(
            'tnote' => NULL,
            'source' => "it-IT",
            'target' => "en-US",
            'email' => $param_de,
            'prop' => $this->prop,
            'get_mt' => 1,
            'num_result' => 3,
            'mt_only' => false,
            'isConcordance' => false,
            'isGlossary' => false,
            'id_user' => array()
        );

        $this->config_param_of_delete = array(
            'tnote' => NULL,
            'source' => "IT",
            'target' => "EN",
            'email' => $param_de,
            'prop' => $this->prop,
            'get_mt' => 1,
            'num_result' => 3,
            'mt_only' => false,
            'isConcordance' => false,
            'isGlossary' => false,
            'id_user' => array()
        );


        $this->curl_param = array(
            CURLOPT_HTTPGET => true,
            CURLOPT_TIMEOUT => 10
        );

        $this->engine_MyMemory = new Engines_MyMemory($this->engine_struct_param);
        $this->reflector = new ReflectionClass($this->engine_MyMemory);
        $this->property = $this->reflector->getProperty("result");
        $this->property->setAccessible(true);


        /**
         * cleaning of matches for my_memory
         */
        $this->config_param_of_delete['segment'] = $this->str_seg_1;
        $this->config_param_of_delete['translation'] = $this->str_tra_1;
        $this->engine_MyMemory->delete($this->config_param_of_delete);
        $this->config_param_of_delete['segment'] = $this->str_seg_2;
        $this->config_param_of_delete['translation'] = $this->str_tra_2;
        $this->engine_MyMemory->delete($this->config_param_of_delete);
        sleep(1);


    }

    public function tearDown()
    {
        sleep(1);
        /**
         * cleaning of matches for my_memory
         */
        $this->config_param_of_delete['segment'] = $this->str_seg_1;
        $this->config_param_of_delete['translation'] = $this->str_tra_1;
        $this->engine_MyMemory->delete($this->config_param_of_delete);
        $this->config_param_of_delete['segment'] = $this->str_seg_2;
        $this->config_param_of_delete['translation'] = $this->str_tra_2;
        $this->engine_MyMemory->delete($this->config_param_of_delete);

        parent::tearDown();

    }

    /**
     * @group regression
     * @covers Engines_MyMemory::set
     */
    public function test_set_segment_1_general_check()
    {

        $this->config_param_of_set['segment'] = $this->str_seg_1;
        $this->config_param_of_set['translation'] = $this->str_tra_1;

        $result = $this->engine_MyMemory->set($this->config_param_of_set);

        $this->assertTrue($result);

        /**
         *  general check of the Engines_Results_MyMemory_SetContributionResponse object
         * @var Engines_Results_MyMemory_SetContributionResponse
         */
        $result_object = $this->property->getValue($this->engine_MyMemory);

        $this->assertTrue($result_object instanceof Engines_Results_MyMemory_SetContributionResponse);
        $this->assertFalse(property_exists($result_object, 'matches'));
        $this->assertTrue(property_exists($result_object, 'responseStatus'));
        $this->assertTrue(property_exists($result_object, 'responseDetails'));
        $this->assertTrue(property_exists($result_object, 'responseData'));
        $this->assertTrue(property_exists($result_object, 'error'));
        $this->assertTrue(property_exists($result_object, '_rawResponse'));


    }

    /**
     * @group regression
     * @covers Engines_MyMemory::set
     */
    public function test_set_segment_1_with_mock()
    {

        $this->config_param_of_set['segment'] = $this->str_seg_1;
        $this->config_param_of_set['translation'] = $this->str_tra_1;

//        $url_mock_param = "http://api.mymemory.translated.net/set?seg=Il+Sistema+genera+un+numero+di+serie+per+quella+copia+e+lo+stampa+%28anche+sotto+forma+di+codice+a+barre%29+su+un%E2%80%99etichetta+adesiva.&tra=The+system+becomes+bar+and+thinks+foo.&langpair=it-IT%7Cen-US&de=demo%40matecat.com&prop=%7B%22project_id%22%3A%22987654%22%2C%22project_name%22%3A%22barfoo%22%2C%22job_id%22%3A%22321%22%7D";
        $url_mock_param = "http://api.mymemory.translated.net/set";

        $mock_json_return = <<<'TAB'
{"responseData":"OK","responseStatus":200,"responseDetails":[484525156]}
TAB;

        $curl_params = [
            CURLOPT_POSTFIELDS => [
                    'seg' => 'Il Sistema genera un numero di serie per quella copia e lo stampa (anche sotto forma di codice a barre) su un’etichetta adesiva.',
                    'tra'   => 'The system becomes bar and thinks foo.',
                    'tnote' => NULL,
                    'langpair' => 'it-IT|en-US',
                    'de' => 'demo@matecat.com',
                    'prop' => '{"project_id":"987654","project_name":"barfoo","job_id":"321"}',
            ],
            CURLINFO_HEADER_OUT => true,
            CURLOPT_TIMEOUT => 120,
        ];

        /**
         * @var Engines_MyMemory
         */
        $this->engine_MyMemory = $this->getMockBuilder('\Engines_MyMemory')->setConstructorArgs(array($this->engine_struct_param))->setMethods(array('_call'))->getMock();
        $this->engine_MyMemory->expects($this->once())->method('_call')->with($url_mock_param, $curl_params)->willReturn($mock_json_return);

        $actual_result = $this->engine_MyMemory->set($this->config_param_of_set);

        $this->assertTrue($actual_result);


        /**
         * check of the Engines_Results_MyMemory_SetContributionResponse object
         * @var Engines_Results_MyMemory_SetContributionResponse
         */
        $result_object = $this->property->getValue($this->engine_MyMemory);

        $this->assertTrue($result_object instanceof Engines_Results_MyMemory_SetContributionResponse);
        $this->assertEquals(200, $result_object->responseStatus);
        $this->assertEquals(array('0' => 484525156), $result_object->responseDetails);
        $this->assertEquals("OK", $result_object->responseData);
        $this->assertNull($result_object->error);
        /**
         * check of protected property
         */
        $this->reflector = new ReflectionClass($result_object);
        $property = $this->reflector->getProperty('_rawResponse');
        $property->setAccessible(true);

        $this->assertEquals("", $property->getValue($result_object));


    }

    /**
     * @group regression
     * @covers Engines_MyMemory::set
     */
    public function test_set_segment_2_general_check_with_id_user_not_in_array_coverage_purpose()
    {
        $this->config_param_of_set['segment'] = $this->str_seg_2;
        $this->config_param_of_set['translation'] = $this->str_tra_2;
        $this->config_param_of_set['id_user']= "a6043e606ac9b5d7ff24";
        $result = $this->engine_MyMemory->set($this->config_param_of_set);

        $this->assertTrue($result);

        /**
         *  general check of the Engines_Results_MyMemory_SetContributionResponse object
         * @var Engines_Results_MyMemory_SetContributionResponse
         */
        $result_object = $this->property->getValue($this->engine_MyMemory);

        $this->assertTrue($result_object instanceof Engines_Results_MyMemory_SetContributionResponse);
        $this->assertFalse(property_exists($result_object, 'matches'));
        $this->assertTrue(property_exists($result_object, 'responseStatus'));
        $this->assertTrue(property_exists($result_object, 'responseDetails'));
        $this->assertTrue(property_exists($result_object, 'responseData'));
        $this->assertTrue(property_exists($result_object, 'error'));
        $this->assertTrue(property_exists($result_object, '_rawResponse'));


    }

    public function test_set_segment_2_with_mock()
    {

        $this->config_param_of_set['segment'] = $this->str_seg_2;
        $this->config_param_of_set['translation'] = $this->str_tra_2;


//        $url_mock_param = "http://api.mymemory.translated.net/set?seg=Ad+esempio%2C+una+copia+del+film+%3Cg+id%3D%2210%22%3EBlade+Runner%3C%2Fg%3E+in+formato+DVD%2C+con+numero+di+serie+6457.&tra=For+example%2C+a+copy+of+the+film+%3Cg+id%3D%2210%22%3EFlade+Bunner%3C%2Fg%3E+in+DVD+format%2C+with+numbers+of+6457+series.&langpair=it-IT%7Cen-US&de=demo%40matecat.com&prop=%7B%22project_id%22%3A%22987654%22%2C%22project_name%22%3A%22barfoo%22%2C%22job_id%22%3A%22321%22%7D";
        $url_mock_param = "http://api.mymemory.translated.net/set";

        $mock_json_return = <<<'TAB'
{"responseData":"OK","responseStatus":200,"responseDetails":[484540480]}
TAB;

        $curl_params = [
                CURLOPT_POSTFIELDS => [
                        'seg' => 'Ad esempio, una copia del film <g id="10">Blade Runner</g> in formato DVD, con numero di serie 6457.',
                        'tra'   => 'For example, a copy of the film <g id="10">Flade Bunner</g> in DVD format, with numbers of 6457 series.',
                        'tnote' => NULL,
                        'langpair' => 'it-IT|en-US',
                        'de' => 'demo@matecat.com',
                        'prop' => '{"project_id":"987654","project_name":"barfoo","job_id":"321"}',
                ],
                CURLINFO_HEADER_OUT => true,
                CURLOPT_TIMEOUT => 120,
        ];

        /**
         * @var Engines_MyMemory
         */
        $this->engine_MyMemory = $this->getMockBuilder('\Engines_MyMemory')->setConstructorArgs(array($this->engine_struct_param))->setMethods(array('_call'))->getMock();
        $this->engine_MyMemory->expects($this->once())->method('_call')->with($url_mock_param, $curl_params)->willReturn($mock_json_return);

        $actual_result = $this->engine_MyMemory->set($this->config_param_of_set);

        $this->assertTrue($actual_result);


        /**
         * check of the Engines_Results_MyMemory_SetContributionResponse object
         * @var Engines_Results_MyMemory_SetContributionResponse
         */
        $result_object = $this->property->getValue($this->engine_MyMemory);

        $this->assertTrue($result_object instanceof Engines_Results_MyMemory_SetContributionResponse);
        $this->assertEquals(200, $result_object->responseStatus);
        $this->assertEquals(array('0' => 484540480), $result_object->responseDetails);
        $this->assertEquals("OK", $result_object->responseData);
        $this->assertNull($result_object->error);
        /**
         * check of protected property
         */
        $this->reflector = new ReflectionClass($result_object);
        $property = $this->reflector->getProperty('_rawResponse');
        $property->setAccessible(true);

        $this->assertEquals("", $property->getValue($result_object));


    }
    /**
     * @group regression
     * @covers Engines_MyMemory::set
     */
    public function test_set_with_error_from_mocked__call_for_coverage_purpose()
    {

        $this->config_param_of_set['segment'] = $this->str_seg_1;
        $this->config_param_of_set['translation'] = $this->str_tra_1;

//        $url_mock_param = "http://api.mymemory.translated.net/set?seg=Il+Sistema+genera+un+numero+di+serie+per+quella+copia+e+lo+stampa+%28anche+sotto+forma+di+codice+a+barre%29+su+un%E2%80%99etichetta+adesiva.&tra=The+system+becomes+bar+and+thinks+foo.&langpair=it-IT%7Cen-US&de=demo%40matecat.com&prop=%7B%22project_id%22%3A%22987654%22%2C%22project_name%22%3A%22barfoo%22%2C%22job_id%22%3A%22321%22%7D";
        $url_mock_param = "http://api.mymemory.translated.net/set";

        $rawValue_error = array(
            'error' => array(
                'code'      => -6,
                'message'   => "Could not resolve host: api.mymemory.translated.net. Server Not Available (http status 0)",
                'response'  => "",
            ),
            'responseStatus'    => 0
        );

        $curl_params = [
                CURLOPT_POSTFIELDS => [
                        'seg' => 'Il Sistema genera un numero di serie per quella copia e lo stampa (anche sotto forma di codice a barre) su un’etichetta adesiva.',
                        'tra'   => 'The system becomes bar and thinks foo.',
                        'tnote' => NULL,
                        'langpair' => 'it-IT|en-US',
                        'de' => 'demo@matecat.com',
                        'prop' => '{"project_id":"987654","project_name":"barfoo","job_id":"321"}',
                ],
                CURLINFO_HEADER_OUT => true,
                CURLOPT_TIMEOUT => 120,
        ];


        /**
         * @var Engines_MyMemory
         */
        $this->engine_MyMemory = $this->getMockBuilder('\Engines_MyMemory')->setConstructorArgs(array($this->engine_struct_param))->setMethods(array('_call'))->getMock();
        $this->engine_MyMemory->expects($this->once())->method('_call')->with($url_mock_param, $curl_params)->willReturn($rawValue_error);

        $actual_result = $this->engine_MyMemory->set($this->config_param_of_set);

        $this->assertFalse($actual_result);


        /**
         * check of the Engines_Results_MyMemory_SetContributionResponse object
         * @var Engines_Results_MyMemory_SetContributionResponse
         */
        $result_object = $this->property->getValue($this->engine_MyMemory);

        $this->assertTrue($result_object instanceof Engines_Results_MyMemory_SetContributionResponse);
        $this->assertEquals(0, $result_object->responseStatus);
        $this->assertEquals("", $result_object->responseDetails);
        $this->assertEquals("", $result_object->responseData);
        $this->assertTrue($result_object->error instanceof Engines_Results_ErrorMatches);

        $this->assertEquals(-6, $result_object->error->code);
        $this->assertEquals("Could not resolve host: api.mymemory.translated.net. Server Not Available (http status 0)", $result_object->error->message);

        /**
         * check of protected property
         */
        $this->reflector = new ReflectionClass($result_object);
        $property = $this->reflector->getProperty('_rawResponse');
        $property->setAccessible(true);

        $this->assertEquals("", $property->getValue($result_object));


    }

}