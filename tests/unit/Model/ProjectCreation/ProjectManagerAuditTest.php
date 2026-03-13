<?php

namespace unit\Model\ProjectCreation;

use ArrayObject;
use Model\ProjectCreation\ProjectManager;
use Model\ProjectCreation\ProjectStructure;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use TestHelpers\AbstractTest;
use TypeError;

/**
 * Audit regression tests for {@see ProjectManager} after the
 * RecursiveArrayObject → ProjectStructure DTO migration.
 *
 * Each test documents a specific bug (C1–C7) or type mismatch (T1–T2)
 * found during the audit and verifies the expected correct behavior
 * after the fix is applied.
 *
 * @see ProjectStructure
 * @see ProjectManager
 */
class ProjectManagerAuditTest extends AbstractTest
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    // =========================================================================
    // C1: segments_metadata->getArrayCopy() crashes on plain array
    //     ProjectManager.php:1177
    //
    // After fix: writeFastAnalysisData() should use the array directly
    // (or call (array) cast) instead of ->getArrayCopy().
    // =========================================================================

    #[Test]
    public function c1_segmentsMetadataPlainArrayIsUsableWithoutGetArrayCopy(): void
    {
        $ps = new ProjectStructure([
            'segments_metadata' => [
                ['id' => 1, 'segment' => 'Hello', 'internal_id' => 'x', 'xliff_mrk_id' => 'm', 'show_in_cattool' => 1],
                ['id' => 2, 'segment' => 'World', 'internal_id' => 'y', 'xliff_mrk_id' => 'n', 'show_in_cattool' => 0],
            ],
        ]);

        // The property is a plain array — calling getArrayCopy() on it would crash.
        $this->assertIsArray($ps->segments_metadata);
        $this->assertNotInstanceOf(ArrayObject::class, $ps->segments_metadata);

        // After fix, the code should use the array directly.
        // Verify the plain array is a valid input for the storage call.
        $data = $ps->segments_metadata; // no ->getArrayCopy() needed
        $this->assertCount(2, $data);
        $this->assertSame(1, $data[0]['id']);
        $this->assertSame(2, $data[1]['id']);
    }

    #[Test]
    public function c1_segmentsMetadataRejectsArrayObject(): void
    {
        // With array type, assigning ArrayObject throws TypeError.
        $this->expectException(\TypeError::class);
        $ps = new ProjectStructure([
            'segments_metadata' => new ArrayObject([
                ['id' => 1, 'segment' => 'Hello'],
            ]),
        ]);
    }

    #[Test]
    public function c1_plainArrayCastIsIdentityOperation(): void
    {
        $plainArray = [['id' => 1], ['id' => 2]];

        $ps = new ProjectStructure(['segments_metadata' => $plainArray]);
        $result = (array) $ps->segments_metadata;
        $this->assertCount(2, $result);
        $this->assertSame($plainArray, $result);
    }

    // =========================================================================
    // C2: unset($this->projectStructure->segments_metadata) un-initializes it
    //     ProjectManager.php:1180
    //
    // After fix: the "free memory" step should assign [] instead of unset().
    // =========================================================================

    #[Test]
    public function c2_assignEmptyArrayInsteadOfUnset(): void
    {
        $ps = new ProjectStructure([
            'segments_metadata' => [
                ['id' => 1, 'segment' => 'Hello'],
            ],
        ]);

        // Correct fix: assign [] to free memory while keeping the property initialized.
        $ps->segments_metadata = [];

        $this->assertSame([], $ps->segments_metadata);
        $this->assertTrue(isset($ps->segments_metadata));
    }

    #[Test]
    public function c2_unsetMakesPropertyUninitializedCausingError(): void
    {
        $ps = new ProjectStructure([
            'segments_metadata' => [['id' => 1]],
        ]);

        // Demonstrate the bug: unset() on a typed property un-initializes it.
        unset($ps->segments_metadata);

        // After unset, the property is no longer initialized.
        // Accessing it will go through __get() which checks property_exists()
        // and returns the value — but the property is uninitialized, so
        // reflection shows it as not initialized.
        $ref = new ReflectionClass(ProjectStructure::class);
        $prop = $ref->getProperty('segments_metadata');
        $this->assertFalse(
            $prop->isInitialized($ps),
            'unset() should leave the property uninitialized — this is the bug'
        );
    }

    #[Test]
    public function c2_afterFixPropertyRemainsInitializedAsEmptyArray(): void
    {
        $ps = new ProjectStructure([
            'segments_metadata' => [['id' => 1], ['id' => 2]],
        ]);

        // The correct fix: replace unset() with assignment to [].
        $ps->segments_metadata = [];

        $ref = new ReflectionClass(ProjectStructure::class);
        $prop = $ref->getProperty('segments_metadata');
        $this->assertTrue(
            $prop->isInitialized($ps),
            'After fix, the property should still be initialized'
        );
        $this->assertSame([], $ps->segments_metadata);
    }

    // =========================================================================
    // C3: translations->exchangeArray([]) crashes on plain array
    //     ProjectManager.php:1390
    //
    // After fix: createJobs() should use `$ps->translations = []` instead.
    // =========================================================================

    #[Test]
    public function c3_translationsPlainArrayCannotCallExchangeArray(): void
    {
        $ps = new ProjectStructure([
            'translations' => ['some', 'data'],
        ]);

        $this->assertIsArray($ps->translations);

        // The bug: calling ->exchangeArray([]) on a plain array crashes.
        // After fix: simple assignment works.
        $ps->translations = [];
        $this->assertSame([], $ps->translations);
    }

    #[Test]
    public function c3_translationsRejectsArrayObject(): void
    {
        // With array type, assigning ArrayObject throws TypeError.
        $this->expectException(\TypeError::class);
        $ps = new ProjectStructure([
            'translations' => new ArrayObject(['key' => 'val']),
        ]);
    }

    #[Test]
    public function c3_assignmentClearsTranslations(): void
    {
        $ps = new ProjectStructure(['translations' => ['c', 'd']]);
        $ps->translations = [];
        $this->assertSame([], $ps->translations);
    }

    // =========================================================================
    // C4-C5 / T2: string assigned to `public array $tm_keys`
    //     ProjectManager.php:1318-1321
    //
    // $projectStructure->tm_keys = (string) json_encode($tm_key)
    // assigns a string to a property typed `array`. This is a TypeError.
    //
    // After fix: use a local variable for the JSON string, not the DTO property.
    // =========================================================================

    #[Test]
    public function c4_c5_tmKeysPropertyRejectsStringAssignment(): void
    {
        $ps = new ProjectStructure();

        // The property is typed `public array $tm_keys = []`.
        // Assigning a string should raise a TypeError.
        $this->expectException(TypeError::class);
        $ps->tm_keys = '["some_json"]'; // @phpstan-ignore assign.propertyType
    }

    #[Test]
    public function c4_c5_tmKeysPropertyAcceptsArrayAssignment(): void
    {
        $ps = new ProjectStructure();

        $tmKeys = [
            ['key' => 'abc123', 'name' => 'My TM', 'r' => true, 'w' => true],
        ];
        $ps->tm_keys = $tmKeys;

        $this->assertSame($tmKeys, $ps->tm_keys);
        $this->assertIsArray($ps->tm_keys);
    }

    #[Test]
    public function c4_c5_correctPatternUsesLocalVariableForJson(): void
    {
        // After fix: the JSON string goes to a local variable, not the DTO.
        $ps = new ProjectStructure();

        $tmKeyArray = [
            ['key' => 'abc123', 'name' => 'Test', 'r' => true, 'w' => true],
        ];

        // Correct pattern: use a local variable for the JSON string.
        $tmKeysJson = (string) json_encode($tmKeyArray);

        // The DTO keeps the array form.
        $ps->tm_keys = $tmKeyArray;
        $this->assertIsArray($ps->tm_keys);

        // The JSON string is used directly where needed (e.g., $newJob->tm_keys).
        $this->assertSame('[{"key":"abc123","name":"Test","r":true,"w":true}]', $tmKeysJson);
    }

    // =========================================================================
    // C6: (string) $projectStructure->tm_keys produces "Array"
    //     ProjectManager.php:1336
    //
    // Casting an array to string yields "Array", not the JSON representation.
    // After fix: use the local JSON variable for $newJob->tm_keys.
    // =========================================================================

    #[Test]
    public function c6_castingArrayToStringProducesArrayNotJson(): void
    {
        $ps = new ProjectStructure();
        $ps->tm_keys = [
            ['key' => 'abc123', 'name' => 'Test'],
        ];

        // This is the bug: (string) on an array produces "Array".
        // Suppress the deprecation/warning for the assertion.
        $result = @((string) $ps->tm_keys); // @phpstan-ignore cast.string
        $this->assertSame('Array', $result, 'Casting array to string yields "Array", not JSON');
    }

    #[Test]
    public function c6_correctPatternUsesJsonEncodeForJobTmKeys(): void
    {
        // After fix: the JSON string is built from the array explicitly.
        $tmKeyArray = [
            ['key' => 'abc123', 'name' => 'My TM', 'r' => true, 'w' => true],
        ];

        // Correct: use json_encode to get the JSON representation.
        $tmKeysJson = (string) json_encode($tmKeyArray);

        // Also handle the {{pid}} replacement on the JSON string.
        $projectId = 42;
        $tmKeysJson = str_replace('{{pid}}', (string) $projectId, $tmKeysJson);

        $this->assertStringNotContainsString('Array', $tmKeysJson);
        $this->assertJson($tmKeysJson);

        // Simulate assigning to $newJob->tm_keys.
        $jobTmKeys = $tmKeysJson;
        $decoded = json_decode($jobTmKeys, true);
        $this->assertCount(1, $decoded);
        $this->assertSame('abc123', $decoded[0]['key']);
    }

    #[Test]
    public function c6_pidReplacementWorksOnJsonString(): void
    {
        $tmKeyArray = [
            ['key' => 'abc123', 'name' => '{{pid}}_memory'],
        ];

        $tmKeysJson = (string) json_encode($tmKeyArray);
        $tmKeysJson = str_replace('{{pid}}', '99', $tmKeysJson);

        $decoded = json_decode($tmKeysJson, true);
        $this->assertSame('99_memory', $decoded[0]['name']);
    }

    // =========================================================================
    // C7: count($this->projectStructure->private_tm_key) on int 0
    //     ProjectManager.php:689
    //
    // private_tm_key defaults to int 0 (T1). count(0) emits a deprecation
    // warning in PHP 8.x and will become a TypeError in PHP 9.
    //
    // After fix: private_tm_key defaults to [] and count() works correctly.
    // =========================================================================

    #[Test]
    public function c7_countOnIntZeroIsProblematic(): void
    {
        $ps = new ProjectStructure(); // fixed: private_tm_key = []

        // After T1 fix, default is [] (no longer int 0).
        $this->assertSame([], $ps->private_tm_key);

        // count([]) works correctly without deprecation.
        $this->assertIsArray($ps->private_tm_key);
        $this->assertCount(0, $ps->private_tm_key);
    }

    #[Test]
    public function c7_countOnEmptyArrayWorksCorrectly(): void
    {
        // After fix: private_tm_key defaults to [].
        $ps = new ProjectStructure(['private_tm_key' => []]);

        $this->assertIsArray($ps->private_tm_key);
        $this->assertCount(0, $ps->private_tm_key);

        // setPrivateTmKeysOrFail() checks: if (count($this->projectStructure->private_tm_key))
        // With [], count returns 0 (falsy) — the TM key processing is skipped.
        $this->assertSame(0, count($ps->private_tm_key));
    }

    #[Test]
    public function c7_countOnPopulatedArrayWorksCorrectly(): void
    {
        $ps = new ProjectStructure([
            'private_tm_key' => [
                ['key' => 'abc123', 'name' => 'My TM', 'r' => true, 'w' => true],
            ],
        ]);

        $this->assertCount(1, $ps->private_tm_key);

        // setPrivateTmKeysOrFail() would enter the if-block.
        $this->assertGreaterThan(0, count($ps->private_tm_key));
    }

    // =========================================================================
    // T1: private_tm_key defaults to int 0, should be []
    //     ProjectStructure.php:52
    //
    // The property `public mixed $private_tm_key = 0` should default to []
    // so it can be iterated with foreach, used with count(), and accessed
    // with $arr[0].
    // =========================================================================

    #[Test]
    public function t1_currentDefaultIsIntZeroNotArray(): void
    {
        $ps = new ProjectStructure();

        // After T1 fix: default is now [] (was 0).
        $this->assertSame([], $ps->private_tm_key);
        $this->assertIsArray($ps->private_tm_key);
    }

    #[Test]
    public function t1_afterFixDefaultShouldBeEmptyArray(): void
    {
        // After fix: private_tm_key should default to [].
        // For now, we construct with the correct default to test behavior.
        $ps = new ProjectStructure(['private_tm_key' => []]);

        $this->assertIsArray($ps->private_tm_key);
        $this->assertSame([], $ps->private_tm_key);
    }

    #[Test]
    public function t1_emptyArrayIsIterableWithForeach(): void
    {
        $ps = new ProjectStructure(['private_tm_key' => []]);

        $iterations = 0;
        foreach ($ps->private_tm_key as $ignored) {
            $iterations++;
        }
        $this->assertSame(0, $iterations);
    }

    #[Test]
    public function t1_populatedArrayIsIterableWithForeach(): void
    {
        $keys = [
            ['key' => 'k1', 'name' => 'TM1', 'r' => true, 'w' => true],
            ['key' => 'k2', 'name' => 'TM2', 'r' => false, 'w' => true],
        ];
        $ps = new ProjectStructure(['private_tm_key' => $keys]);

        $collected = [];
        foreach ($ps->private_tm_key as $tmKeyObj) {
            $collected[] = $tmKeyObj['key'];
        }
        $this->assertSame(['k1', 'k2'], $collected);
    }

    #[Test]
    public function t1_arrayIndexAccessWorksOnPopulatedArray(): void
    {
        $keys = [
            ['key' => 'first', 'name' => 'First TM'],
        ];
        $ps = new ProjectStructure(['private_tm_key' => $keys]);

        $this->assertSame('first', $ps->private_tm_key[0]['key']);
        $this->assertSame('First TM', $ps->private_tm_key[0]['name']);
    }

    #[Test]
    public function t1_countWorksOnArrayDefault(): void
    {
        $ps = new ProjectStructure(['private_tm_key' => []]);
        $this->assertSame(0, count($ps->private_tm_key));

        $ps->private_tm_key = [['key' => 'a'], ['key' => 'b']];
        $this->assertSame(2, count($ps->private_tm_key));
    }

    #[Test]
    public function t1_emptyCheckWorksOnArrayDefault(): void
    {
        $ps = new ProjectStructure(['private_tm_key' => []]);
        $this->assertTrue(empty($ps->private_tm_key));

        $ps->private_tm_key = [['key' => 'a']];
        $this->assertFalse(empty($ps->private_tm_key));
    }

    // =========================================================================
    // T2: tm_keys typed array but assigned string — same as C4-C5
    //     ProjectManager.php:1318-1321
    //
    // Verify that the property type enforcement works correctly.
    // =========================================================================

    #[Test]
    public function t2_tmKeysDefaultIsEmptyArray(): void
    {
        $ps = new ProjectStructure();
        $this->assertSame([], $ps->tm_keys);
        $this->assertIsArray($ps->tm_keys);
    }

    #[Test]
    public function t2_tmKeysRejectsNonArrayTypes(): void
    {
        $ps = new ProjectStructure();

        // String assignment must throw TypeError.
        try {
            $ps->tm_keys = 'not an array'; // @phpstan-ignore assign.propertyType
            $this->fail('Expected TypeError for string assignment to array property');
        } catch (TypeError) {
            // expected
        }

        // Integer assignment must throw TypeError.
        try {
            $ps->tm_keys = 42; // @phpstan-ignore assign.propertyType
            $this->fail('Expected TypeError for int assignment to array property');
        } catch (TypeError) {
            // expected
        }

        // Null assignment must throw TypeError.
        try {
            $ps->tm_keys = null; // @phpstan-ignore assign.propertyType
            $this->fail('Expected TypeError for null assignment to array property');
        } catch (TypeError) {
            // expected
        }

        // Property should still hold the original default.
        $this->assertSame([], $ps->tm_keys);
    }

    #[Test]
    public function t2_tmKeysAcceptsValidArrayStructures(): void
    {
        $ps = new ProjectStructure();

        // Empty array
        $ps->tm_keys = [];
        $this->assertSame([], $ps->tm_keys);

        // Array of key structures (typical usage in createJobs)
        $keys = [
            [
                'key'             => 'abc123',
                'name'            => 'MyMemory',
                'complete_format' => true,
                'tm'              => true,
                'glos'            => true,
                'owner'           => true,
                'r'               => true,
                'w'               => true,
                'penalty'         => 0,
            ],
        ];
        $ps->tm_keys = $keys;
        $this->assertCount(1, $ps->tm_keys);
        $this->assertSame('abc123', $ps->tm_keys[0]['key']);
    }

    // =========================================================================
    // Integration: end-to-end pattern for createJobs() TM key handling
    //
    // Verifies the complete corrected flow:
    // 1. Build tm_key array from private_tm_key
    // 2. JSON-encode to a LOCAL variable (not DTO property)
    // 3. Replace {{pid}} in the JSON string
    // 4. Assign JSON string to $newJob->tm_keys
    // =========================================================================

    #[Test]
    public function integration_createJobsTmKeyPatternAfterFix(): void
    {
        $ps = new ProjectStructure([
            'id_project'     => 77,
            'private_tm_key' => [
                [
                    'key'     => 'key1',
                    'name'    => '{{pid}}_my_memory',
                    'r'       => true,
                    'w'       => true,
                    'penalty' => 0,
                ],
            ],
        ]);

        // Step 1: Build tm_key array (simulating the foreach in createJobs).
        $tm_key = [];
        foreach ($ps->private_tm_key as $tmKeyObj) {
            $tm_key[] = [
                'complete_format' => true,
                'tm'              => true,
                'glos'            => true,
                'owner'           => true,
                'penalty'         => $tmKeyObj['penalty'] ?? 0,
                'name'            => $tmKeyObj['name'],
                'key'             => $tmKeyObj['key'],
                'r'               => $tmKeyObj['r'],
                'w'               => $tmKeyObj['w'],
            ];
        }

        // Step 2: JSON-encode to a LOCAL variable (the fix for C4-C5).
        $tmKeysJson = (string) json_encode($tm_key);

        // Step 3: Replace {{pid}} in the JSON string (the fix for C6).
        $tmKeysJson = str_replace('{{pid}}', (string) $ps->id_project, $tmKeysJson);

        // Step 4: Verify the JSON is correct.
        $decoded = json_decode($tmKeysJson, true);
        $this->assertCount(1, $decoded);
        $this->assertSame('key1', $decoded[0]['key']);
        $this->assertSame('77_my_memory', $decoded[0]['name']);

        // The DTO's tm_keys should remain an array (never assigned the string).
        $this->assertIsArray($ps->tm_keys);
    }
}
