<?php

namespace Matecat\Core\Controllers;

use Controller\API\V1\NewController;
use Exception;
use InvalidArgumentException;
use Klein\App;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\Database;
use Model\DataAccess\IDatabase;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobsMetadataMarshaller;
use Model\ProjectCreation\ProjectStructure;
use Model\Teams\TeamStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionProperty;
use Utils\Engines\AbstractEngine;
use Utils\Engines\DeepL;
use Utils\Engines\MyMemory;
use Utils\Registry\AppConfig;

/**
 * Test seam exposing the protected ProjectStructure builder so the
 * mapping logic in NewController::buildProjectStructure() can be driven
 * directly with a validated request array (no file upload / queue side effects).
 */
class TestableNewControllerForBuild extends NewController
{
    /**
     * @param array<string, mixed> $request
     * @param array<string, mixed> $filesFound
     */
    public function callBuildProjectStructure(
        array $request,
        array $filesFound,
        string $uploadToken,
        UserStruct $user,
        AbstractEngine $engine
    ): ProjectStructure {
        return $this->buildProjectStructure($request, $filesFound, $uploadToken, $user, $engine);
    }
}

#[Group('PersistenceNeeded')]
#[AllowMockObjectsWithoutExpectations]
class NewControllerTest extends AbstractTest
{
    private NewController $controller;
    private Request $requestMock;
    private Response $responseMock;
    private ReflectionMethod $method;
    /**
     * @var UserStruct
     */
    private UserStruct $user;

    private ?IDatabase $database_instance = null;
    private $id_engine = null;

    public function setUp(): void
    {
        parent::setUp();
        $this->requestMock = $this->createStub(Request::class);
        $this->responseMock = $this->createStub(Response::class);
        $this->user = $this->createStub(UserStruct::class);


        /**
         * engine insertion
         */
        $this->database_instance = obtainTestDatabase(AppConfig::$DB_SERVER, AppConfig::$DB_USER, AppConfig::$DB_PASS, AppConfig::$DB_DATABASE);
        $sql_engine = "INSERT INTO " . AppConfig::$DB_DATABASE . ".engines (
                name, 
                type, 
                description, 
                base_url, 
                translate_relative_url, 
                contribute_relative_url, 
                update_relative_url, 
                delete_relative_url, 
                others, 
                class_load, 
                extra_parameters, 
                google_api_compliant_version, 
                penalty, 
                active, 
                uid
        ) VALUES (
                'DeepL', 
                'MT', 
                'DeepL - Accurate translations for individuals and Teams.', 
                'https://api.deepl.com',
                'v1/translate', 
                null, 
                null, 
                null, 
                '{\"relative_glossaries_url\":\"glossaries\"}', 
                'Utils\\\\Engines\\\\DeepL', 
                '{\"DeepL-Auth-Key\":\"xxxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx\"}', 
                '2', 
                15, 
                1, 
                1886428310
        );";

        $this->database_instance->getConnection()->query($sql_engine);
        $this->id_engine = $this->database_instance->getConnection()->lastInsertId();
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $this->database_instance->getConnection()->query("DELETE FROM " . AppConfig::$DB_DATABASE . ".engines WHERE id = " . $this->id_engine);
    }

    /**
     * @throws Exception
     */
    public function createMocks(): void
    {
        $app = new App();
        $app->register('getDatabase', static fn() => obtainTestDatabase());
        $this->controller = new NewController($this->requestMock, $this->responseMock, null, $app);
        $reflector = new ReflectionClass($this->controller);
        $this->method = $reflector->getMethod('validateTheRequest');

        $reflector = new ReflectionProperty($this->controller, 'user');
        $reflector->setValue($this->controller, $this->user);
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    #[Test]
    public function testValidateTheRequestWithValidParameters()
    {
        $user = $this->createMock(UserStruct::class);
        $user->expects($this->once())->method('getPersonalTeam')->willReturn(new TeamStruct());
        $user->expects($this->once())->method('getEmail')->willReturn("test-email@translated.com");

        $this->requestMock = new Request(
            [],
            [
                JobsMetadataMarshaller::CHARACTER_COUNTER_COUNT_TAGS->value => '1',
                JobsMetadataMarshaller::CHARACTER_COUNTER_MODE->value => 'google_ads',
                'due_date' => '20251231',
                'source_lang' => 'en',
                'target_lang' => 'fr,de',
                'mt_engine' => 1,
                'tms_engine' => 1,
                'segmentation_rule' => 'patent',
            ],
            [],
            [],
            [
                'file[]' => [
                    'name' => 'foo.docx',
                    'tmp_name' => '/tmp/xdwlky',
                ]
            ]
        );

        $this->createMocks();

        $reflector = new ReflectionProperty($this->controller, 'user');
        $reflector->setValue($this->controller, $user);

        $validateParameters = $this->method->invoke($this->controller);

        $this->assertIsArray($validateParameters);
        $this->assertArrayHasKey('source_lang', $validateParameters);
        $this->assertEquals('en-US', $validateParameters['source_lang']);
        $this->assertEquals('foo', $validateParameters['project_name']);
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    #[Test]
    public function testValidateTheRequestWithValidParametersAndMtDeepLEngine()
    {
        $this->user = $this->createMock(UserStruct::class);
        $this->user->expects($this->once())->method('getPersonalTeam')->willReturn(new TeamStruct());
        $this->user->expects($this->once())->method('getEmail')->willReturn("test-email@translated.com");
        $this->user->uid = 1886428310;

        $this->requestMock = new Request(
            [],
            [
                JobsMetadataMarshaller::CHARACTER_COUNTER_COUNT_TAGS->value => '1',
                JobsMetadataMarshaller::CHARACTER_COUNTER_MODE->value => 'google_ads',
                'due_date' => '20251231',
                'source_lang' => 'en',
                'target_lang' => 'fr,de',
                'tms_engine' => 1,
                'mt_engine' => $this->id_engine,
                'segmentation_rule' => 'patent',
            ],
            [],
            [],
            [
                'file[]' => [
                    'name' => 'foo.docx',
                    'tmp_name' => '/tmp/xdwlky',
                ]
            ]
        );

        $this->createMocks();

        $reflectionProperty = new ReflectionProperty($this->controller, 'userIsLogged');
        $reflectionProperty->setValue($this->controller, true);

        $validateParameters = $this->method->invoke($this->controller);

        $this->assertIsArray($validateParameters);
        $this->assertArrayHasKey('source_lang', $validateParameters);
        $this->assertEquals('en-US', $validateParameters['source_lang']);
        $this->assertEquals('foo', $validateParameters['project_name']);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testValidateTheRequestWithMissingFile()
    {
        $this->requestMock = new Request(
            [],
            [
                JobsMetadataMarshaller::CHARACTER_COUNTER_COUNT_TAGS->value => '1',
                JobsMetadataMarshaller::CHARACTER_COUNTER_MODE->value => 'google_ads',
                'due_date' => '20251231',
                'source_lang' => 'en',
                'target_lang' => 'fr,de',
                'mt_engine' => 1,
                'tms_engine' => 1,
                'segmentation_rule' => 'patent',
            ]
        );
        $this->createMocks();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing file. Not Sent.');
        $this->method->invoke($this->controller);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testValidateTheRequestWithInvalidParameters()
    {
        $this->requestMock = new Request(
            [],
            [
                JobsMetadataMarshaller::CHARACTER_COUNTER_COUNT_TAGS->value => '1',
                JobsMetadataMarshaller::CHARACTER_COUNTER_MODE->value => 'google_ads',
                'due_date' => '20251231',
                'source_lang' => 'en',
                'target_lang' => 'fr,de',
                'mt_engine' => 1,
                'tms_engine' => 5,
                'segmentation_rule' => 'patent',
            ],
            [],
            [],
            ['file[]' => ['name' => 'foo.docx']]
        );
        $this->createMocks();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid TM Engine.');
        $this->method->invoke($this->controller);
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    #[Test]
    public function testValidateTheRequestWithInvalidSourceLang()
    {
        $this->requestMock = new Request(
            [],
            [
                JobsMetadataMarshaller::CHARACTER_COUNTER_COUNT_TAGS->value => '1',
                JobsMetadataMarshaller::CHARACTER_COUNTER_MODE->value => 'google_ads',
                'due_date' => '20251231',
                'source_lang' => 'zz',
                'target_lang' => 'fr,de',
                'mt_engine' => 1,
                'tms_engine' => 5,
                'segmentation_rule' => 'patent',
            ],
            [],
            [],
            ['file[]' => ['name' => 'foo.docx']]
        );
        $this->createMocks();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing source language.');
        $this->method->invoke($this->controller);
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    #[Test]
    public function testValidateTheRequestWithLaraStyleGuideId(): void
    {
        $user = $this->createMock(UserStruct::class);
        $user->expects($this->once())->method('getPersonalTeam')->willReturn(new TeamStruct());
        $user->expects($this->once())->method('getEmail')->willReturn("test-email@translated.com");

        $this->requestMock = new Request(
            [],
            [
                JobsMetadataMarshaller::CHARACTER_COUNTER_COUNT_TAGS->value => '1',
                JobsMetadataMarshaller::CHARACTER_COUNTER_MODE->value => 'google_ads',
                'due_date' => '20251231',
                'source_lang' => 'en',
                'target_lang' => 'fr,de',
                'mt_engine' => 1,
                'tms_engine' => 1,
                'segmentation_rule' => 'patent',
                'lara_style_guideline_id' => 'guide-abc-123',
            ],
            [],
            [],
            [
                'file[]' => [
                    'name' => 'foo.docx',
                    'tmp_name' => '/tmp/xdwlky',
                ]
            ]
        );

        $this->createMocks();

        $reflector = new ReflectionProperty($this->controller, 'user');
        $reflector->setValue($this->controller, $user);

        $validateParameters = $this->method->invoke($this->controller);

        $this->assertIsArray($validateParameters);
        $this->assertArrayHasKey('lara_style_guideline_id', $validateParameters);
        $this->assertEquals('guide-abc-123', $validateParameters['lara_style_guideline_id']);
    }

    /**
     * Build a validated request array via validateTheRequest() so that
     * buildProjectStructure() can be driven with realistic, fully-populated data.
     *
     * @return array{0: array<string, mixed>, 1: TestableNewControllerForBuild, 2: UserStruct}
     * @throws ReflectionException
     * @throws Exception
     */
    private function buildValidatedRequest(): array
    {
        $user = $this->createMock(UserStruct::class);
        $user->method('getPersonalTeam')->willReturn(new TeamStruct());
        $user->method('getEmail')->willReturn('owner-build@translated.com');
        $user->method('getUid')->willReturn(777);
        $user->uid = 777;
        $user->email = 'owner-build@translated.com';

        $request = new Request(
            [],
            [
                JobsMetadataMarshaller::CHARACTER_COUNTER_COUNT_TAGS->value => '1',
                JobsMetadataMarshaller::CHARACTER_COUNTER_MODE->value => 'google_ads',
                'due_date' => '20251231',
                'source_lang' => 'en',
                'target_lang' => 'fr,de',
                'mt_engine' => 1,
                'tms_engine' => 1,
                'segmentation_rule' => 'patent',
                'pretranslate_100' => '1',
                'public_tm_penalty' => '20',
                'project_name' => 'My Build Project',
            ],
            [],
            [],
            [
                'file[]' => [
                    'name' => 'foo.docx',
                    'tmp_name' => '/tmp/xdwlky',
                ],
            ]
        );

        $controller = (new ReflectionClass(TestableNewControllerForBuild::class))->newInstanceWithoutConstructor();
        $reqProp = new ReflectionProperty($controller, 'request');
        $reqProp->setValue($controller, $request);
        $userProp = new ReflectionProperty($controller, 'user');
        $userProp->setValue($controller, $user);
        $fsProp = new ReflectionProperty($controller, 'featureSet');
        $fsProp->setValue($controller, new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class)));
        $dbProp = (new ReflectionClass(\Controller\Abstracts\KleinController::class))->getProperty('database');
        $dbProp->setAccessible(true);
        $dbProp->setValue($controller, $this->createStub(\Model\DataAccess\IDatabase::class));

        $validateMethod = (new ReflectionClass(NewController::class))->getMethod('validateTheRequest');
        /** @var array<string, mixed> $validated */
        $validated = $validateMethod->invoke($controller);

        return [$validated, $controller, $user];
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    #[Test]
    public function testBuildProjectStructureMapsValidatedRequest(): void
    {
        [$request, $controller, $user] = $this->buildValidatedRequest();

        $engine = (new ReflectionClass(MyMemory::class))->newInstanceWithoutConstructor();

        $filesFound = ['arrayFiles' => ['foo.docx'], 'arrayFilesMeta' => ['meta']];

        $projectStructure = $controller->callBuildProjectStructure(
            $request,
            $filesFound,
            'upload-token-xyz',
            $user,
            $engine
        );

        $this->assertInstanceOf(ProjectStructure::class, $projectStructure);
        $this->assertSame('My Build Project', $projectStructure->project_name);
        $this->assertSame('en-US', $projectStructure->source_language);
        $this->assertCount(2, $projectStructure->target_language);
        $this->assertContains('fr-FR', $projectStructure->target_language);
        $this->assertSame('owner-build@translated.com', $projectStructure->owner);
        $this->assertSame('owner-build@translated.com', $projectStructure->id_customer);
        $this->assertSame('upload-token-xyz', $projectStructure->uploadToken);
        $this->assertSame(['foo.docx'], $projectStructure->array_files);
        $this->assertSame(['meta'], $projectStructure->array_files_meta);
        $this->assertSame(777, $projectStructure->uid);
        $this->assertTrue($projectStructure->userIsLogged);
        $this->assertSame(1, $projectStructure->pretranslate_100);
        $this->assertSame(1, $projectStructure->mt_engine);
        $this->assertSame(1, $projectStructure->tms_engine);
        $this->assertSame(20, $projectStructure->public_tm_penalty);
    }

    /**
     * Drives the optional-branch assignments inside buildProjectStructure()
     * (dialect_strict, mt_evaluation, xliff, character counter, due_date null).
     *
     * @throws ReflectionException
     * @throws Exception
     */
    #[Test]
    public function testBuildProjectStructureOptionalBranches(): void
    {
        [$request, $controller, $user] = $this->buildValidatedRequest();

        // Force the optional branches that are skipped by the default request.
        $request['dialect_strict'] = ['en-US' => true];
        $request['mt_evaluation'] = true;
        $request['xliff_parameters'] = ['rule' => 'value'];
        $request['character_counter_mode'] = 'all_one';
        $request['character_counter_count_tags'] = true;
        $request['due_date'] = null;

        $engine = (new ReflectionClass(MyMemory::class))->newInstanceWithoutConstructor();

        $projectStructure = $controller->callBuildProjectStructure(
            $request,
            ['arrayFiles' => [], 'arrayFilesMeta' => []],
            'tok',
            $user,
            $engine
        );

        $this->assertSame(['en-US' => true], $projectStructure->dialect_strict);
        $this->assertTrue($projectStructure->mt_evaluation);
        $this->assertSame(['rule' => 'value'], $projectStructure->xliff_parameters);
        $this->assertSame('all_one', $projectStructure->character_counter_mode);
        $this->assertNull($projectStructure->due_date);
    }

    /**
     * The MT-extra-params loop copies any engine configuration parameter present
     * in the validated request onto the ProjectStructure.
     *
     * @throws ReflectionException
     * @throws Exception
     */
    #[Test]
    public function testBuildProjectStructureCopiesEngineConfigParams(): void
    {
        [$request, $controller, $user] = $this->buildValidatedRequest();
        $request['deepl_formality'] = 'more';

        $engine = (new ReflectionClass(DeepL::class))->newInstanceWithoutConstructor();

        $projectStructure = $controller->callBuildProjectStructure(
            $request,
            ['arrayFiles' => [], 'arrayFilesMeta' => []],
            'tok',
            $user,
            $engine
        );

        $this->assertSame('more', $projectStructure->deepl_formality);
    }

}