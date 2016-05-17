<?php
/**
 * @group regression
 * @covers Engines_MyMemory::glossaryImport
 * User: dinies
 * Date: 12/05/16
 * Time: 16.14
 */

/**
 *We are avoiding reports of deprecations because the global variable that will cause the deprecation
 * is set before the execution of the curl call by the curl handler.
 * @see MultiCurlHandler::curl_setopt_array
 * @link http://nl1.php.net/manual/en/function.curl-setopt.php  @find  CURLOPT_POSTFIELDS
 */
error_reporting(~E_DEPRECATED);

class GlossaryImportTest extends AbstractTest
{
    protected $engine_struct_param;
    protected $engine_MyMemory;
    protected $glossary_folder_path;
    protected $filename;
    protected $path_of_file_for_test;
    protected $key_param;

    public function setUp(){
        parent::setUp();
        $this->key_param="a6043e606ac9b5d7ff24";
        $engineDAO = new EnginesModel_EngineDAO(Database::obtain());
        $engine_struct = EnginesModel_EngineStruct::getStruct();
        $engine_struct->id = 1;
        $eng = $engineDAO->read($engine_struct);

        /**
         * @var $engineRecord EnginesModel_EngineStruct
         */
        $this->engine_struct_param = $eng[0];

        $this->engine_MyMemory = new Engines_MyMemory($this->engine_struct_param);
    }

    public function tearDown()
    {
        unlink($this->path_of_file_for_test);
        parent::tearDown();
    }

    public function test_glossaryImport_correct_behaviour()
    {
        $this->filename= "GlossaryImportcorrectBehaviour";

        $path_of_the_original_file = TEST_DIR . '/support/files/glossary/' . "{$this->filename}" . "Original.g";
        $this->path_of_file_for_test = TEST_DIR . '/support/files/glossary/' . "{$this->filename}" . "Temp.g";


        chmod($path_of_the_original_file, 0644);

        copy($path_of_the_original_file,$this->path_of_file_for_test);


        $file_param = $this->path_of_file_for_test;
        $name_param = "{$this->filename}"."Temp.g";


        Langs_Languages::getInstance();
        $result = $this->engine_MyMemory->glossaryImport($file_param, $this->key_param, $name_param);

        $this->assertTrue($result instanceof Engines_Results_MyMemory_TmxResponse);
        $this->assertEquals(202, $result->responseStatus);
        $this->assertEquals("", $result->responseDetails);
        $this->assertCount(1, $result->responseData);
        $this->assertNull($result->error);

        $reflector = new ReflectionClass($result);
        $property = $reflector->getProperty('_rawResponse');
        $property->setAccessible(true);

        $this->assertEquals("", $property->getValue($result));


    }

    public function test_glossaryImport_short_count_of_lines()
    {


        $this->filename= "GlossaryImportShortCountoflines";


        $path_of_the_original_file = TEST_DIR . '/support/files/glossary/' . "{$this->filename}" . "Original.g";
        $this->path_of_file_for_test = TEST_DIR . '/support/files/glossary/' . "{$this->filename}" . "Temp.g";


        chmod($path_of_the_original_file, 0644);

        copy($path_of_the_original_file,$this->path_of_file_for_test);


        $file_param = $this->path_of_file_for_test;
        $name_param = "{$this->filename}"."Temp.g";


        Langs_Languages::getInstance();
        $result = $this->engine_MyMemory->glossaryImport($file_param, $this->key_param, $name_param);

        $this->assertTrue($result instanceof Engines_Results_MyMemory_TmxResponse);
        $this->assertEquals(406, $result->responseStatus);
        $this->assertEquals("No valid glossary file provided. Field separator could be not valid.", $result->responseDetails);
        $this->assertEquals("",$result->responseData);

    }

    public function test_glossaryImport_wrong_source_lang()
    {


        $this->filename= "GlossaryImportWrongSourceLang";


        $path_of_the_original_file = TEST_DIR . '/support/files/glossary/' . "{$this->filename}" . "Original.g";
        $this->path_of_file_for_test = TEST_DIR . '/support/files/glossary/' . "{$this->filename}" . "Temp.g";


        chmod($path_of_the_original_file, 0644);

        copy($path_of_the_original_file,$this->path_of_file_for_test);


        $file_param = $this->path_of_file_for_test;
        $name_param = "{$this->filename}"."Temp.g";

        Langs_Languages::getInstance();
        $result = $this->engine_MyMemory->glossaryImport($file_param, $this->key_param, $name_param);

        $this->assertTrue($result instanceof Engines_Results_MyMemory_TmxResponse);
        $this->assertEquals(406, $result->responseStatus);
        $this->assertEquals("Undefined index: wrong", $result->responseDetails);
        $this->assertEquals("",$result->responseData);


    }

    public function test_glossaryImport_wrong_target_lang()
    {


        $this->filename= "GlossaryImportWrongTargetLang";


        $path_of_the_original_file = TEST_DIR . '/support/files/glossary/' . "{$this->filename}" . "Original.g";
        $this->path_of_file_for_test = TEST_DIR . '/support/files/glossary/' . "{$this->filename}" . "Temp.g";


        chmod($path_of_the_original_file, 0644);

        copy($path_of_the_original_file,$this->path_of_file_for_test);


        $file_param = $this->path_of_file_for_test;
        $name_param = "{$this->filename}"."Temp.g";

        Langs_Languages::getInstance();
        $result = $this->engine_MyMemory->glossaryImport($file_param, $this->key_param, $name_param);

        $this->assertTrue($result instanceof Engines_Results_MyMemory_TmxResponse);
        $this->assertEquals(406, $result->responseStatus);
        $this->assertEquals("Undefined index: wrong", $result->responseDetails);
        $this->assertEquals("",$result->responseData);

    }

    public function test_glossaryImport_empty_languages()
    {


        $this->filename= "GlossaryImportEmptyLanguages";


        $path_of_the_original_file = TEST_DIR . '/support/files/glossary/' . "{$this->filename}" . "Original.g";
        $this->path_of_file_for_test = TEST_DIR . '/support/files/glossary/' . "{$this->filename}" . "Temp.g";


        chmod($path_of_the_original_file, 0644);

        copy($path_of_the_original_file,$this->path_of_file_for_test);


        $file_param = $this->path_of_file_for_test;
        $name_param = "{$this->filename}"."Temp.g";

        Langs_Languages::getInstance();
        $result = $this->engine_MyMemory->glossaryImport($file_param, $this->key_param, $name_param);

        $this->assertTrue($result instanceof Engines_Results_MyMemory_TmxResponse);
        $this->assertEquals(406, $result->responseStatus);
        $this->assertEquals("Undefined index: ", $result->responseDetails);
        $this->assertEquals("",$result->responseData);


    }

}