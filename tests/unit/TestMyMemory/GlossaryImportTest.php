<?php
/**
 * @group  regression
 * @covers MyMemory::glossaryImport
 * User: dinies
 * Date: 12/05/16
 * Time: 16.14
 */

/**
 *We are avoiding reports of deprecations because the global variable that will cause the deprecation
 * is set before the execution of the curl call by the curl handler.
 * @see  MultiCurlHandler::curl_setopt_array
 * @link http://nl1.php.net/manual/en/function.curl-setopt.php  @find  CURLOPT_POSTFIELDS
 */


use Model\DataAccess\Database;
use Model\Engines\EngineDAO;
use Model\Engines\Structs\EngineStruct;
use TestHelpers\AbstractTest;
use Utils\Engines\MyMemory;
use Utils\Engines\Results\ErrorResponse;
use Utils\Langs\Languages;
use Utils\Network\MultiCurlHandler;
use Utils\Registry\AppConfig;
use Utils\Tools\Utils;

error_reporting(~E_DEPRECATED);

class GlossaryImportTest extends AbstractTest
{
    protected $engine_struct_param;
    /**
     * @var MyMemory
     */
    protected MyMemory $engine_MyMemory;
    protected $glossary_folder_path;
    protected $filename;
    protected $path_of_file_for_test;
    protected $key_param;

    /**
     * @throws Exception
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->key_param = "a6043e606ac9b5d7ff24";
        $engineDAO = new EngineDAO(Database::obtain(AppConfig::$DB_SERVER, AppConfig::$DB_USER, AppConfig::$DB_PASS, AppConfig::$DB_DATABASE));
        $engine_struct = EngineStruct::getStruct();
        $engine_struct->id = 1;
        $eng = $engineDAO->read($engine_struct);

        /**
         * @var $engineRecord EngineStruct
         */
        $this->engine_struct_param = $eng[0];

        $this->engine_MyMemory = new MyMemory($this->engine_struct_param);
    }

    public function tearDown(): void
    {
        unlink($this->path_of_file_for_test);
        parent::tearDown();
    }

    /**
     * @group  regression
     * @covers MyMemory::glossaryImport
     */
    public function test_glossaryImport_correct_behaviour()
    {
        $path_of_the_original_file = AppConfig::$ROOT . '/tests/resources/files/glossary/Final-Matecat-new_glossary_format-Glossary.csv';
        $this->path_of_file_for_test = AppConfig::$ROOT . '/tests/resources/files/glossary/Final-Matecat-new_glossary_format-GlossaryCopy.csv';

        chmod($path_of_the_original_file, 0644);

        copy($path_of_the_original_file, $this->path_of_file_for_test);

        Languages::getInstance();
        $result = $this->engine_MyMemory->glossaryImport($this->path_of_file_for_test, $this->key_param, 'Final-Matecat-new_glossary_format-Glossary.csv');

        $this->assertEquals(202, $result->responseStatus);
        $this->assertArrayHasKey('UUID', $result->responseData);
        $this->assertTrue(Utils::isTokenValid($result->responseData['UUID']));
        $this->assertTrue(Utils::isTokenValid($result->id));
        $this->assertEquals("", $result->responseDetails);
        $this->assertCount(1, $result->responseData);
        $this->assertNull($result->error);

        $reflector = new ReflectionClass($result);
        $property = $reflector->getProperty('_rawResponse');


        $this->assertEquals("", $property->getValue($result));
    }

    /**
     * @group  regression
     * @covers MyMemory::glossaryImport
     */
    public function test_glossaryImport_wrong_target_lang()
    {
        $path_of_the_original_file = AppConfig::$ROOT . '/tests/resources/files/glossary/Final-Matecat-new_glossary_format-InvalidTargetLang.csv';
        $this->path_of_file_for_test = AppConfig::$ROOT . '/tests/resources/files/glossary/Final-Matecat-new_glossary_format-InvalidTargetLangCopy.csv';


        chmod($path_of_the_original_file, 0644);

        copy($path_of_the_original_file, $this->path_of_file_for_test);

        Languages::getInstance();
        $result = $this->engine_MyMemory->glossaryImport($this->path_of_file_for_test, $this->key_param, 'Final-Matecat-new_glossary_format-InvalidTargetLangCopy.csv');

        $this->assertEquals(403, $result->responseStatus);
        $this->assertEquals("HEADER DON'T MATCH THE CORRECT STRUCTURE", $result->responseDetails);
        $this->assertEquals("HEADER DON'T MATCH THE CORRECT STRUCTURE", $result->responseData['translatedText']);
    }

    /**
     * @group  regression
     * @covers MyMemory::glossaryImport
     */
    public function test_glossaryImport_invalid_header()
    {
        $path_of_the_original_file = AppConfig::$ROOT . '/tests/resources/files/glossary/GlossaryInvalidHeader.csv';
        $this->path_of_file_for_test = AppConfig::$ROOT . '/tests/resources/files/glossary/GlossaryInvalidHeaderCopy.csv';


        chmod($path_of_the_original_file, 0644);

        copy($path_of_the_original_file, $this->path_of_file_for_test);

        Languages::getInstance();
        $result = $this->engine_MyMemory->glossaryImport($this->path_of_file_for_test, $this->key_param, 'GlossaryInvalidHeaderCopy.csv');

        $this->assertEquals(403, $result->responseStatus);
        $this->assertEquals("HEADER DON'T MATCH THE CORRECT STRUCTURE", $result->responseDetails);
        $this->assertEquals("HEADER DON'T MATCH THE CORRECT STRUCTURE", $result->responseData['translatedText']);
    }

    /**
     * @group  regression
     * @covers MyMemory::glossaryImport
     */
    public function test_glossaryImport_with_error_from_mocked__call_for_coverage_purpose()
    {
        $rawValue_error = json_encode([
                'error' => [
                    'code' => -6,
                    'message' => "Could not resolve host: api.mymemory.translated.net. Server Not Available (http status 0)",
                    'response' => "",
                ],
                'responseStatus' => 401
            ]
        );

        /**
         * @var $this ->engine_MyMemory MyMemory
         *            mocking _call
         */
        $this->engine_MyMemory = @$this->getMockBuilder('\Utils\Engines\MyMemory')->setConstructorArgs([$this->engine_struct_param])->onlyMethods(['_call'])->getMock();
        $this->engine_MyMemory->expects($this->once())->method('_call')->willReturn($rawValue_error);


        $path_of_the_original_file = AppConfig::$ROOT . '/tests/resources/files/glossary/GlossaryInvalidHeader.csv';
        $this->path_of_file_for_test = AppConfig::$ROOT . '/tests/resources/files/glossary/GlossaryInvalidHeaderCopy.csv';

        copy($path_of_the_original_file, $this->path_of_file_for_test);

        Languages::getInstance();
        $result = $this->engine_MyMemory->glossaryImport($this->path_of_file_for_test, $this->key_param, "GlossaryInvalidHeaderCopy.csv");

        $this->assertNull($result->id);
        $this->assertEquals(401, $result->responseStatus);
        $this->assertEquals("", $result->responseDetails);
        $this->assertEquals("", $result->responseData);
        $this->assertTrue($result->error instanceof ErrorResponse);

        $this->assertEquals(-6, $result->error->code);
        $this->assertEquals("Could not resolve host: api.mymemory.translated.net. Server Not Available (http status 0)", $result->error->message);


        $reflector = new ReflectionClass($result);
        $property = $reflector->getProperty('_rawResponse');


        $this->assertEquals("", $property->getValue($result));
    }


}