<?php

/**
 * @group regression
 * @covers Engines_MyMemory
 * User: dinies
 * Date: 16/05/16
 * Time: 19.35
 */


/**
 *We are avoiding reports of deprecations because the global variable that will cause the deprecation
 * is set before the execution of the curl call by the curl handler.
 * @see MultiCurlHandler::curl_setopt_array
 * @link http://nl1.php.net/manual/en/function.curl-setopt.php  @find  CURLOPT_POSTFIELDS
 */
error_reporting(~E_DEPRECATED);


class MacroFunctionalMyMemoryTest extends AbstractTest
{

    protected $resource;

    public function tearDown()
    {
        if (is_resource($this->resource)) {
            fclose($this->resource);
        }
        parent::tearDown();
    }

    /**
     * @covers Engines_MyMemory::import
     * @covers Engines_MyMemory::getStatus
     * @covers Engines_MyMemory::createExport
     * @covers Engines_MyMemory::checkExport
     * @covers Engines_MyMemory::downloadExport
     */
    public function test_about_best_case_scenario_of_TMX_cycle_of_functions()
    {

        /**
         * Engine creation
         */


        $engineDAO = new EnginesModel_EngineDAO(Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE ));
        $engine_struct = EnginesModel_EngineStruct::getStruct();
        $engine_struct->id = 1;
        $eng = $engineDAO->read($engine_struct);

        /**
         * @var $engineRecord EnginesModel_EngineStruct
         */
        $engine_struct_param = $eng[0];

        $engine_MyMemory = new Engines_MyMemory($engine_struct_param);

        Langs_Languages::getInstance();


        /**
         * Path File initialization
         */


        $filename = "exampleForTest";

        $path_of_the_original_file = TEST_DIR . '/support/files/tmx/' . "{$filename}" . "Original.tmx";


        chmod($path_of_the_original_file, 0644);


        $file_param = $path_of_the_original_file;
        $key_param = "a6043e606ac9b5d7ff24";
        $name_param = "{$filename}" . "Original.tmx";


        /**
         * Importing
         */
        $result = $engine_MyMemory->import($file_param, $key_param, $name_param);


        $this->assertTrue($result instanceof Engines_Results_MyMemory_TmxResponse);
        $this->assertTrue(is_numeric($result->id));
        $this->assertEquals(202, $result->responseStatus);
        $this->assertEquals("", $result->responseDetails);
        $this->assertCount(1, $result->responseData);
        $this->assertNull($result->error);

        $reflector = new ReflectionClass($result);
        $property = $reflector->getProperty('_rawResponse');
        $property->setAccessible(true);

        $this->assertEquals("", $property->getValue($result));

        /**
         * Getting Status
         */
        $ready = false;
        $tmx_max_id = 0;
        $result_tmx = array();
        $time=0;
        while ((!$ready) && ($time<100)) {
            usleep(500000);//0.5 sec
            $time ++;
            $result = $engine_MyMemory->getStatus($key_param, $name_param);

            /**
             * Fetching the last result inserted searching the record with max id
             */
            foreach ($result->responseData['tm'] as $memory) {
                //obtain max id
                $tmx_max_id = max($tmx_max_id, $memory['id']);

                //if maximum is current, pick it (it means that, among duplicates, it's the latest)
                if ($tmx_max_id == $memory['id']) {
                    $result_tmx = $memory;
                }
            }

            $ready = $result_tmx['status'] == 1;
        }

        $this->assertTrue($result instanceof Engines_Results_MyMemory_TmxResponse);
        $this->assertNull($result->id);
        $this->assertEquals(200, $result->responseStatus);
        $this->assertEquals("", $result->responseDetails);
        $this->assertCount(1, $result->responseData);


        $tm = $result->responseData['tm'];
        $this->assertNotEquals(0, count($tm));


        $this->assertCount(11, $result_tmx);

        $this->assertTrue(is_numeric($result_tmx['id']));

        $this->assertEquals("Italian", $result_tmx['source_lang']);

        $this->assertEquals("English", $result_tmx['target_lang']);

        $this->assertRegExp('/^[0-9]{4}-[0,1][0-9]-[0-3][0-9] [0-2][0-9]:[0-5][0-9]:[0-5][0-9]$/', $result_tmx['creation_date']);

        $this->assertEquals("2", $result_tmx['num_seg_tot']);

        $this->assertEquals(2, $result_tmx['temp_seg_ins']);

        $this->assertEquals(0, $result_tmx['temp_seg_not_ins']);

        $this->assertEquals("{$filename}" . "Original.tmx", $result_tmx['file_name']);

        $this->assertEquals("{$filename}" . "Original.tmx", $result_tmx['description']);

        $this->assertEquals("1", $result_tmx['status']);

        $this->assertNull($result_tmx['log']);


        $this->assertNull($result->error);

        $reflector = new ReflectionClass($result);
        $property = $reflector->getProperty('_rawResponse');
        $property->setAccessible(true);

        $this->assertEquals("", $property->getValue($result));

        /**
         * Creating Export
         */
        $result = $engine_MyMemory->createExport($key_param);

        $this->assertTrue($result instanceof Engines_Results_MyMemory_ExportResponse);
        $this->assertTrue(is_numeric($result->id));
        $this->assertEquals("http://api.mymemory.translated.net/tmx/export/check?key={$key_param}", $result->resourceLink);
        $this->assertEquals("202", $result->responseStatus);
        $this->assertEquals("QUEUED", $result->responseDetails);
        $this->assertCount(1, $result->responseData);
        $this->assertTrue(is_numeric($result->responseData['id']));
        $this->assertNull($result->error);

        $reflector = new ReflectionClass($result);
        $property = $reflector->getProperty('_rawResponse');
        $property->setAccessible(true);

        $this->assertEquals("", $property->getValue($result));

        /**
         * Checking Export
         */
        $time=0;
        $ready=false;
        while ((!$ready) && ($time<100)) {
            usleep(500000);//0.5 sec
            $time ++;
            $result = $engine_MyMemory->checkExport($key_param);
            $ready = $result->responseDetails == "READY";
        }


        $this->assertTrue($result instanceof Engines_Results_MyMemory_ExportResponse);

        $exp = '#^http:\/\/api.mymemory.translated.net\/tmx\/export\/download\?key=' . $key_param . '&pass=[0-9a-z]{40}$#';
        $this->assertRegExp($exp, $result->resourceLink);


        $this->assertEquals("200", $result->responseStatus);
        $this->assertEquals("READY", $result->responseDetails);
        $this->assertCount(1, $result->responseData);
        $this->assertTrue(is_numeric($result->responseData['id']));
        $this->assertNull($result->error);

        $reflector = new ReflectionClass($result);
        $property = $reflector->getProperty('_rawResponse');
        $property->setAccessible(true);

        $this->assertEquals("", $property->getValue($result));

        /**
         * extracting Password
         */

        $_download_url = parse_url($result->resourceLink);
        parse_str($_download_url['query'], $secrets);
        list($_key, $pass) = array_values($secrets);

        $this->assertEquals($key_param, $_key);

        /**
         * Downloading Export
         */

        $this->resource = $engine_MyMemory->downloadExport($key_param, $pass);

        $this->assertTrue(is_resource($this->resource));
        $this->assertEquals("stream", get_resource_type($this->resource));
        $stats = fstat($this->resource);
        $this->assertTrue($stats['size'] > 0);
    }


}