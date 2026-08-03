<?php


namespace Matecat\Core\TestMyMemory;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\Database;
use Model\Engines\EngineDAO;
use Model\Engines\Structs\EngineStruct;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionMethod;
use Utils\Engines\MyMemory;
use Utils\Engines\Results\MyMemory\GetMemoryResponse;
use Utils\Engines\Results\MyMemory\SetContributionResponse;
use Utils\Registry\AppConfig;


/**
 * @group  regression
 * @covers MyMemory::_decode
 * User: dinies
 * Date: 28/04/16
 * Time: 17.58
 */
#[Group('PersistenceNeeded')]
class DecodeMyMemoryTest extends AbstractTest
{
    protected ReflectionMethod $method;
    protected MyMemory $myMemory;

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function setUp(): void
    {
        parent::setUp();
        $engineDAO = new EngineDAO(obtainTestDatabase(AppConfig::$DB_SERVER, AppConfig::$DB_USER, AppConfig::$DB_PASS, AppConfig::$DB_DATABASE));
        $engine_struct = EngineStruct::getStruct();
        $engine_struct->id = 1;
        $eng = $engineDAO->read($engine_struct);

        /**
         * @var $engineRecord EngineStruct
         */
        $engine_struct_param = $eng[0];


        $this->myMemory = new MyMemory($engine_struct_param, $this->createStub(\Model\DataAccess\IDatabase::class));
        $reflector = new ReflectionClass($this->myMemory);
        $this->method = $reflector->getMethod("_decode");
    }

    /**
     * It tests the behaviour of the decoding of json input.
     * @group   regression
     * @covers  MyMemory::_decode
     * @throws ReflectionException
     */
    #[Test]
    public function test__decode_with_json_in_input_deusch_segment()
    {
        $json_input = <<<LAB
{"responseData":{"translatedText":null,"match":null},"responseDetails":"","responseStatus":200,"responderId":"235","matches":[]}
LAB;

        $array_params = [
            'q' => "- Auf der Fußhaut natürlich vorhandene Hornhautbakterien zersetzen sich durch auftretenden Schweiß an Ihren Füßen.",
            'langpair' => "en-US|fr-FR",
            'de' => "demo@matecat.com",
            'mt' => null,
            'numres' => 100
        ];
        $input_function_purpose = "gloss_get_relative_url";

        $actual_result = $this->method->invoke($this->myMemory, $json_input, $array_params, $input_function_purpose);

        /**
         * general check on the keys of TSM object returned
         */
        $this->assertTrue($actual_result instanceof GetMemoryResponse);
        $this->assertTrue(property_exists($actual_result, 'matches'));
        $this->assertTrue(property_exists($actual_result, 'responseStatus'));
        $this->assertTrue(property_exists($actual_result, 'responseDetails'));
        $this->assertTrue(property_exists($actual_result, 'responseData'));
        $this->assertTrue(property_exists($actual_result, 'error'));
        $this->assertTrue(property_exists($actual_result, '_rawResponse'));
    }

    /**
     * It tests the behaviour of the decoding of json input.
     * @group   regression
     * @covers  MyMemory::_decode
     * @throws ReflectionException
     */
    #[Test]
    public function test__decode_with_json_in_input_from_italian_to_aragonese_segment_with_private_TM()
    {
        $json_input = <<<LAB
{"responseData":{"translatedText":null,"match":null},"responseDetails":"","responseStatus":200,"responderId":null,"matches":[]}
LAB;

        $array_params = [
            'q' => "Il Sistema genera un numero di serie per quella copia e lo stampa (anche sotto forma di codice a barre) su un’etichetta adesiva.",
            'langpair' => "it-IT|an-ES",
            'de' => "demo@matecat.com",
            'mt' => true,
            'numres' => "3",
            'key' => "a6043e606ac9b5d7ff24"
        ];
        $input_function_purpose = "translate_relative_url";

        $actual_result = $this->method->invoke($this->myMemory, $json_input, $array_params, $input_function_purpose);
        /**
         * general check on the keys of GetMemoryResponse object returned
         */
        $this->assertTrue($actual_result instanceof GetMemoryResponse);
        $this->assertTrue(property_exists($actual_result, 'matches'));
        $this->assertTrue(property_exists($actual_result, 'responseStatus'));
        $this->assertTrue(property_exists($actual_result, 'responseDetails'));
        $this->assertTrue(property_exists($actual_result, 'responseData'));
        $this->assertTrue(property_exists($actual_result, 'error'));
        $this->assertTrue(property_exists($actual_result, '_rawResponse'));
    }

    /**
     * It tests the behaviour of the decoding of json input.
     * @group   regression
     * @covers  MyMemory::_decode
     * @throws ReflectionException
     */
    #[Test]
    public function test__decode_with_json_in_input_from_italian_to_english_triggered_by_set_method_check_1()
    {
        $json_input = <<<LAB
{"responseData":"OK","responseStatus":200,"responseDetails":[484525156]}
LAB;

        $prop = <<<'LABEL'
{"project_id":"10","project_name":"tyuio","job_id":"10"}
LABEL;


        $array_params = [
            'seg' => "Il Sistema registra le informazioni sul nuovo film.",
            'tra' => "The system records the information on the new movie.",
            'tnote' => null,
            'langpair' => "it-IT|en-US",
            'de' => "demo@matecat.com",
            'prop' => $prop,
            'key' => "a6043e606ac9b5d7ff24"
        ];

        $input_function_purpose = "contribute_relative_url";

        $actual_result = $this->method->invoke($this->myMemory, $json_input, $array_params, $input_function_purpose);
        /**
         * general check on the keys of SetContributionResponse object returned
         */
        $this->assertTrue($actual_result instanceof SetContributionResponse);
        $this->assertFalse(property_exists($actual_result, 'matches'));
        $this->assertTrue(property_exists($actual_result, 'responseStatus'));
        $this->assertTrue(property_exists($actual_result, 'responseDetails'));
        $this->assertTrue(property_exists($actual_result, 'responseData'));
        $this->assertTrue(property_exists($actual_result, 'error'));
        $this->assertTrue(property_exists($actual_result, '_rawResponse'));

        $this->assertEquals(200, $actual_result->responseStatus);
        $this->assertEquals(['0' => 484525156], $actual_result->responseDetails);
        $this->assertEquals("OK", $actual_result->responseData);
        $this->assertNull($actual_result->error);
        /**
         * check of protected property
         */
        $reflector = new ReflectionClass($actual_result);
        $property = $reflector->getProperty('_rawResponse');


        $this->assertEquals("", $property->getValue($actual_result));
    }


    /**
     * It tests the behaviour of the decoding of json input.
     * @group   regression
     * @covers  MyMemory::_decode
     * @throws ReflectionException
     */
    #[Test]
    public function test__decode_with_json_in_input_from_italian_to_english_triggered_by_set_method_check_2()
    {
        $json_input = <<<LAB
{"responseData":"OK","responseStatus":200,"responseDetails":[484540480]}
LAB;
        $prop = <<<'LABEL'
{"project_id":"9","project_name":"eryt","job_id":"9"}
LABEL;
        $segment = <<<'LABEL'
Ad esempio, una copia del film <g id="10">Blade Runner</g> in formato DVD, con numero di serie 6457.
LABEL;

        $translation = <<<'LABEL'
For example, a copy of the film <g id="10">Flade Bunner</g> in DVD format, with numbers of 6457 series.
LABEL;


        $array_params = [
            'seg' => $segment,
            'tra' => $translation,
            'tnote' => null,
            'langpair' => "it-IT|en-US",
            'de' => "demo@matecat.com",
            'prop' => $prop,
            'key' => "a6043e606ac9b5d7ff24"
        ];

        $input_function_purpose = "contribute_relative_url";

        $actual_result = $this->method->invoke($this->myMemory, $json_input, $array_params, $input_function_purpose);
        /**
         * general check on the keys of SetContributionResponse object returned
         */
        $this->assertTrue($actual_result instanceof SetContributionResponse);
        $this->assertFalse(property_exists($actual_result, 'matches'));
        $this->assertTrue(property_exists($actual_result, 'responseStatus'));
        $this->assertTrue(property_exists($actual_result, 'responseDetails'));
        $this->assertTrue(property_exists($actual_result, 'responseData'));
        $this->assertTrue(property_exists($actual_result, 'error'));
        $this->assertTrue(property_exists($actual_result, '_rawResponse'));


        $this->assertEquals(200, $actual_result->responseStatus);
        $this->assertEquals(['0' => 484540480], $actual_result->responseDetails);
        $this->assertEquals("OK", $actual_result->responseData);
        $this->assertNull($actual_result->error);
        /**
         * check of protected property
         */
        $reflector = new ReflectionClass($actual_result);
        $property = $reflector->getProperty('_rawResponse');


        $this->assertEquals("", $property->getValue($actual_result));
    }


    /**
     * It tests the behaviour of the decoding of json input.
     * @group   regression
     * @covers  MyMemory::_decode
     * @throws ReflectionException
     */
    #[Test]
    public function test__decode_with_json_in_input_from_italian_to_english_triggered_by_delete_method_check()
    {
        $json_input = <<<LAB
{"responseStatus":200,"responseData":"Found and deleted 1 segments"}
LAB;

        $array_params = [
            'seg' => "Il Sistema registra le informazioni sul nuovo film.",
            'tra' => "The system records the information on the new movie.",
            'langpair' => "IT|EN",
            'de' => "demo@matecat.com",
        ];


        $input_function_purpose = "delete_relative_url";

        /**
         * @var $actual_result GetMemoryResponse
         */
        $actual_result = $this->method->invoke($this->myMemory, $json_input, $array_params, $input_function_purpose);


        /**
         * general check on the keys of TSM object returned
         */
        $this->assertTrue($actual_result instanceof GetMemoryResponse);
        $this->assertTrue(property_exists($actual_result, 'matches'));
        $this->assertTrue(property_exists($actual_result, 'responseStatus'));
        $this->assertTrue(property_exists($actual_result, 'responseDetails'));
        $this->assertTrue(property_exists($actual_result, 'responseData'));
        $this->assertTrue(property_exists($actual_result, 'error'));
        $this->assertTrue(property_exists($actual_result, '_rawResponse'));

        $this->assertEquals([], $actual_result->matches);
        $this->assertEquals(200, $actual_result->responseStatus);
        $this->assertEquals("", $actual_result->responseDetails);
        $this->assertEquals("Found and deleted 1 segments", $actual_result->responseData);
        $this->assertNull($actual_result->error);
        /**
         * check of protected property
         */
        $reflector = new ReflectionClass($actual_result);
        $property = $reflector->getProperty('_rawResponse');


        $this->assertEquals("", $property->getValue($actual_result));
    }

    /**
     * Proves that the source/target language codes returned by MyMemory on the match
     * itself take precedence over the ones held in $this->_config: the config values
     * are only a fallback for when MyMemory omits them from the match.
     * @group   regression
     * @covers  MyMemory::_decode
     * @throws ReflectionException
     */
    #[Test]
    public function test__decode_prefers_match_source_and_target_over_config_values()
    {
        $reflector = new ReflectionClass($this->myMemory);
        $configProperty = $reflector->getProperty('_config');
        $configProperty->setValue($this->myMemory, array_merge(
            $configProperty->getValue($this->myMemory),
            ['source' => 'fr-FR', 'target' => 'de-DE']
        ));

        $json_input = <<<LAB
{"responseData":{"translatedText":null,"match":null},"responseDetails":"","responseStatus":200,"matches":[{"id":"1","segment":"Hello world","translation":"Ciao mondo","quality":"70","reference":"","usage-count":1,"subject":"All","created-by":"MyMemory","last-updated-by":"MyMemory","create-date":"2016-05-02 17:15:11","last-update-date":"2016-05-02 17:15:11","tm_properties":"","match":0.95,"source":"en-US","target":"it-IT"}]}
LAB;

        $array_params = [
            'q' => 'Hello world',
            'langpair' => 'en-US|it-IT',
            'de' => 'demo@matecat.com',
            'mt' => null,
            'numres' => 100,
        ];

        /**
         * @var $actual_result GetMemoryResponse
         */
        $actual_result = $this->method->invoke($this->myMemory, $json_input, $array_params, 'gloss_get_relative_url');
        $array = $actual_result->get_matches_as_array(1);

        $this->assertSame('en-US', $array[0]['source'], 'match source must win over config source');
        $this->assertSame('it-IT', $array[0]['target'], 'match target must win over config target');
    }

    /**
     * Proves that when MyMemory omits source/target from the match, the values
     * configured on the engine ($this->_config) are used as a fallback.
     * @group   regression
     * @covers  MyMemory::_decode
     * @throws ReflectionException
     */
    #[Test]
    public function test__decode_falls_back_to_config_source_and_target_when_match_omits_them()
    {
        $reflector = new ReflectionClass($this->myMemory);
        $configProperty = $reflector->getProperty('_config');
        $configProperty->setValue($this->myMemory, array_merge(
            $configProperty->getValue($this->myMemory),
            ['source' => 'fr-FR', 'target' => 'de-DE']
        ));

        $json_input = <<<LAB
{"responseData":{"translatedText":null,"match":null},"responseDetails":"","responseStatus":200,"matches":[{"id":"1","segment":"Hello world","translation":"Ciao mondo","quality":"70","reference":"","usage-count":1,"subject":"All","created-by":"MyMemory","last-updated-by":"MyMemory","create-date":"2016-05-02 17:15:11","last-update-date":"2016-05-02 17:15:11","tm_properties":"","match":0.95}]}
LAB;

        $array_params = [
            'q' => 'Hello world',
            'langpair' => 'fr-FR|de-DE',
            'de' => 'demo@matecat.com',
            'mt' => null,
            'numres' => 100,
        ];

        /**
         * @var $actual_result GetMemoryResponse
         */
        $actual_result = $this->method->invoke($this->myMemory, $json_input, $array_params, 'gloss_get_relative_url');
        $array = $actual_result->get_matches_as_array(1);

        $this->assertSame('fr-FR', $array[0]['source'], 'config source must be used as fallback');
        $this->assertSame('de-DE', $array[0]['target'], 'config target must be used as fallback');
    }

}