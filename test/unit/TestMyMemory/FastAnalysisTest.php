<?php

/**
 * @group regression
 * @covers Engines_MyMemory::fastAnalysis
 * User: dinies
 * Date: 21/05/16
 * Time: 12.26
 */
class FastAnalisysTest extends AbstractTest
{

    /**
     * @group regression
     * @covers Engines_MyMemory::fastAnalysis
     */
    public function test_fastAnalysis()
    {
        $array_paramemeter = array(
            '0' => array(
                'jsid' => 38,
                'segment' => "- Auf der Fußhaut natürlich vorhandene Hornhautbakterien zersetzen sich durch auftretenden Schweiß an Ihren Füßen.",
                'segment_hash' => "e0170a2e381f1969056a7eb5e5bd0ac9"
            ),
            '1' => array(
                'jsid' => 38,
                'segment' => "- Auf der Fußhaut natürlich vorhandene Hornhautbakterien zersetzen sich durch auftretenden Schweiß an Ihren Füßen.",
                'segment_hash' => "e0170a2e381f1969056a7eb5e5bd0ac9"

            )
        );
        $engineDAO = new EnginesModel_EngineDAO(Database::obtain());
        $engine_struct = EnginesModel_EngineStruct::getStruct();
        $engine_struct->id = 1;
        $eng = $engineDAO->read($engine_struct);

        /**
         * @var $engineRecord EnginesModel_EngineStruct
         */
        $engine_struct_param = $eng[0];

        $engine_MyMemory = new Engines_MyMemory($engine_struct_param);
        $result = $engine_MyMemory->fastAnalysis($array_paramemeter);

        /**
         * general check of the result object structure
         */
        $this->assertTrue($result instanceof Engines_Results_MyMemory_AnalyzeResponse);
        $this->assertTrue(property_exists($result, 'responseStatus'));
        $this->assertTrue(property_exists($result, 'responseDetails'));
        $this->assertTrue(property_exists($result, 'responseData'));
        $this->assertTrue(property_exists($result, 'error'));
        $this->assertTrue(property_exists($result, '_rawResponse'));
    }

    /**
     * @group regression
     * @covers Engines_MyMemory::fastAnalysis
     */
    public function test_fastAnalysis_four_repetitions()
    {
        $array_paramemeter = array(
            '0' => array(
                'jsid' => 39,
                'segment' => "Come ti chiami ?",
                'segment_hash' => "a7cc2b1de00f5243a682ad20a9b54306"
            ),
            '1' => array(
                'jsid' => 39,
                'segment' => "Come ti chiami ?",
                'segment_hash' => "a7cc2b1de00f5243a682ad20a9b54306"
            ),
            '2' => array(
                'jsid' => 39,
                'segment' => "Come ti chiami ?",
                'segment_hash' => "a7cc2b1de00f5243a682ad20a9b54306"
            ),
            '3' => array(
                'jsid' => 39,
                'segment' => "Come ti chiami ?",
                'segment_hash' => "a7cc2b1de00f5243a682ad20a9b54306"
            ),
            '4' => array(
                'jsid' => 38,
                'segment' => "- Auf der Fußhaut natürlich vorhandene Hornhautbakterien zersetzen sich durch auftretenden Schweiß an Ihren Füßen.",
                'segment_hash' => "e0170a2e381f1969056a7eb5e5bd0ac9"
            )

        );
        $engineDAO = new EnginesModel_EngineDAO(Database::obtain());
        $engine_struct = EnginesModel_EngineStruct::getStruct();
        $engine_struct->id = 1;
        $eng = $engineDAO->read($engine_struct);

        /**
         * @var $engineRecord EnginesModel_EngineStruct
         */
        $engine_struct_param = $eng[0];

        $engine_MyMemory = new Engines_MyMemory($engine_struct_param);
        $result = $engine_MyMemory->fastAnalysis($array_paramemeter);

        /**
         * general check of the result object structure
         */
        $this->assertTrue($result instanceof Engines_Results_MyMemory_AnalyzeResponse);
        $this->assertTrue(property_exists($result, 'responseStatus'));
        $this->assertTrue(property_exists($result, 'responseDetails'));
        $this->assertTrue(property_exists($result, 'responseData'));
        $this->assertTrue(property_exists($result, 'error'));
        $this->assertTrue(property_exists($result, '_rawResponse'));
    }


    /**
     * @group regression
     * @covers Engines_MyMemory::fastAnalysis
     */
    public function test_fastAnalysis_four_repetitions_mocked()
    {
        $array_paramemeter = array(
            '0' => array(
                'jsid' => 39,
                'segment' => "Come ti chiami ?",
                'segment_hash' => "a7cc2b1de00f5243a682ad20a9b54306"
            ),
            '1' => array(
                'jsid' => 39,
                'segment' => "Come ti chiami ?",
                'segment_hash' => "a7cc2b1de00f5243a682ad20a9b54306"
            ),
            '2' => array(
                'jsid' => 39,
                'segment' => "Come ti chiami ?",
                'segment_hash' => "a7cc2b1de00f5243a682ad20a9b54306"
            ),
            '3' => array(
                'jsid' => 39,
                'segment' => "Come ti chiami ?",
                'segment_hash' => "a7cc2b1de00f5243a682ad20a9b54306"
            ),
            '4' => array(
                'jsid' => 38,
                'segment' => "- Auf der Fußhaut natürlich vorhandene Hornhautbakterien zersetzen sich durch auftretenden Schweiß an Ihren Füßen.",
                'segment_hash' => "e0170a2e381f1969056a7eb5e5bd0ac9"
            )

        );


        $segs = <<<'T'
[{"jsid":39,"segment":"Come ti chiami ?","segment_hash":"a7cc2b1de00f5243a682ad20a9b54306"},{"jsid":39,"segment":"Come ti chiami ?","segment_hash":"a7cc2b1de00f5243a682ad20a9b54306"},{"jsid":39,"segment":"Come ti chiami ?","segment_hash":"a7cc2b1de00f5243a682ad20a9b54306"},{"jsid":39,"segment":"Come ti chiami ?","segment_hash":"a7cc2b1de00f5243a682ad20a9b54306"},{"jsid":38,"segment":"- Auf der Fu\u00dfhaut nat\u00fcrlich vorhandene Hornhautbakterien zersetzen sich durch auftretenden Schwei\u00df an Ihren F\u00fc\u00dfen.","segment_hash":"e0170a2e381f1969056a7eb5e5bd0ac9"}]
T;

        $curl_opt_mock_param = array(
            CURLOPT_POSTFIELDS => array(
                'fast' => "1",
                'df' => "matecat_array",
                'segs' => $segs
            ),
            CURLOPT_TIMEOUT => 120
        );

        $url_mock_param = "http://api.mymemory.translated.net/analyze";
        $mock_raw_value = <<<'T'
{"responseData":"OK","responseStatus":200,"data":{"39":{"type":"No_match","wc":3},"38":{"type":"No_match","wc":14}}}
T;


        $engineDAO = new EnginesModel_EngineDAO(Database::obtain());
        $engine_struct = EnginesModel_EngineStruct::getStruct();
        $engine_struct->id = 1;
        $eng = $engineDAO->read($engine_struct);

        /**
         * @var $engineRecord EnginesModel_EngineStruct
         */
        $engine_struct_param = $eng[0];

        /**
         * creation of the engine
         * @var Engines_MyMemory
         */
        $engine_MyMemory = $this->getMockBuilder('\Engines_MyMemory')->setConstructorArgs(array($engine_struct_param))->setMethods(array('_call'))->getMock();
        $engine_MyMemory->expects($this->once())->method('_call')->with($url_mock_param, $curl_opt_mock_param)->willReturn($mock_raw_value);


        $result = $engine_MyMemory->fastAnalysis($array_paramemeter);

        $this->assertTrue($result instanceof Engines_Results_MyMemory_AnalyzeResponse);

        $this->assertEquals(200, $result->responseStatus);
        $this->assertEquals("OK", $result->responseDetails);
        $this->assertCount(2, $result->responseData);
        $this->assertArrayHasKey('38', $result->responseData);
        $this->assertArrayHasKey('39', $result->responseData);
        /**
         * Checking arrays of result in responseData field
         * first
         */
        $array_result_data = $result->responseData['38'];
        $this->assertCount(2, $array_result_data);
        $this->assertArrayHasKey('type', $array_result_data);
        $this->assertArrayHasKey('wc', $array_result_data);

        $type = $array_result_data['type'];
        $this->assertEquals("No_match", $type);
        $wc = $array_result_data['wc'];
        $this->assertEquals("14", $wc);
        /**
         * second
         */
        $array_result_data = $result->responseData['39'];
        $this->assertCount(2, $array_result_data);
        $this->assertArrayHasKey('type', $array_result_data);
        $this->assertArrayHasKey('wc', $array_result_data);

        $type = $array_result_data['type'];
        $this->assertEquals("No_match", $type);
        $wc = $array_result_data['wc'];
        $this->assertEquals("3", $wc);

        $this->assertNull($result->error);

        /**
         * check of protected property
         */
        $reflector = new ReflectionClass($result);
        $property = $reflector->getProperty('_rawResponse');
        $property->setAccessible(true);
        $this->assertEquals("", $property->getValue($result));

    }


    /**
     * @group regression
     * @covers Engines_MyMemory::fastAnalysis
     */
    public function test_fastAnalysis_with_no_array_as_param_for_coverage_purpose(){

        $array_paramemeter= "bar_and_foo";


        $engineDAO = new EnginesModel_EngineDAO(Database::obtain());
        $engine_struct = EnginesModel_EngineStruct::getStruct();
        $engine_struct->id = 1;
        $eng = $engineDAO->read($engine_struct);

        /**
         * @var $engineRecord EnginesModel_EngineStruct
         */
        $engine_struct_param = $eng[0];

        $engine_MyMemory = new Engines_MyMemory($engine_struct_param);
        $this->assertNull($engine_MyMemory->fastAnalysis($array_paramemeter));
    }

}