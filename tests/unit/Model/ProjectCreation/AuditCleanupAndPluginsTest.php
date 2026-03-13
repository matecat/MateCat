<?php

namespace unit\Model\ProjectCreation;

use ArrayObject;
use Model\ProjectCreation\ProjectStructure;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

/**
 * Audit-driven regression tests for the RecursiveArrayObject → ProjectStructure migration.
 *
 * Covers:
 *  - C23/C24: Plugin notes access (Uber/Airbnb offsetExists on notes)
 *  - T1:      private_tm_key default type mismatch (int 0 vs array [])
 *  - T2:      tm_keys type consistency (stays array, not re-assigned as JSON string)
 *  - D1:      GDrive Session dead code (session['user'] is never ArrayObject after toArray)
 *  - D3:      getRequestedFeatures dead code (project_features elements are never ArrayObject)
 *  - D5:      Removed properties file_part_id / file_metadata verified as gone
 *  - N3/N4:   TmKeyService fragility with empty private_tm_key
 *  - Serialization round-trip through toArray() → new ProjectStructure()
 */
class AuditCleanupAndPluginsTest extends AbstractTest
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    // ──────────────────────────────────────────────────────────────
    // C23/C24: Plugin notes access — Uber.php:248 / Airbnb.php:54
    //
    // After fix (Option B), plugins use plain array syntax:
    //   isset($projectStructure->notes[$internal_id])
    // instead of:
    //   $projectStructure->notes->offsetExists($internal_id)
    // ──────────────────────────────────────────────────────────────

    #[Test]
    public function notesSupportsIssetAccessForPluginPattern(): void
    {
        $ps = new ProjectStructure([
            'notes' => [
                'unit-1' => [
                    'entries' => [
                        ['type' => 'comment', 'content' => 'Review needed'],
                        ['type' => 'instruction', 'content' => 'Do not translate'],
                    ],
                ],
                'unit-2' => [
                    'entries' => [
                        ['type' => 'comment', 'content' => 'Glossary term'],
                    ],
                ],
            ],
        ]);

        // Plugin pattern: check if notes exist for a segment's internal_id
        $internal_id = 'unit-1';
        self::assertTrue(isset($ps->notes[$internal_id]));
        self::assertIsArray($ps->notes[$internal_id]['entries']);
        self::assertCount(2, $ps->notes[$internal_id]['entries']);
        self::assertSame('Review needed', $ps->notes[$internal_id]['entries'][0]['content']);
    }

    #[Test]
    public function notesIssetReturnsFalseForMissingKey(): void
    {
        $ps = new ProjectStructure([
            'notes' => [
                'unit-1' => ['entries' => []],
            ],
        ]);

        self::assertFalse(isset($ps->notes['nonexistent-key']));
    }

    #[Test]
    public function notesDefaultIsEmptyArraySupportingIsset(): void
    {
        $ps = new ProjectStructure();

        // Default [] — isset should not error, just return false
        self::assertFalse(isset($ps->notes['any-key']));
        self::assertIsArray($ps->notes);
        self::assertEmpty($ps->notes);
    }

    #[Test]
    public function notesCanBeIteratedByPluginPattern(): void
    {
        $ps = new ProjectStructure([
            'notes' => [
                'unit-1' => [
                    'entries' => [
                        ['type' => 'comment', 'content' => 'A'],
                        ['type' => 'comment', 'content' => 'B'],
                    ],
                ],
            ],
        ]);

        // Simulates the full plugin loop pattern from Uber.php / Airbnb.php
        $collected = [];
        $internal_id = 'unit-1';
        if (isset($ps->notes[$internal_id])) {
            foreach ($ps->notes[$internal_id]['entries'] as $entry) {
                $collected[] = $entry['content'];
            }
        }

        self::assertSame(['A', 'B'], $collected);
    }

    #[Test]
    public function notesRejectsArrayObjectInput(): void
    {
        // With array type, assigning ArrayObject throws TypeError.
        $this->expectException(\TypeError::class);
        $notesAO = new ArrayObject([
            'unit-1' => new ArrayObject([
                'entries' => new ArrayObject([
                    new ArrayObject(['type' => 'comment', 'content' => 'From AO']),
                ]),
            ]),
        ]);

        new ProjectStructure([
            'notes' => $notesAO,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // T1: private_tm_key default type mismatch
    //
    // CURRENT BUG: public mixed $private_tm_key = 0;
    // SHOULD BE:   public array $private_tm_key = [];
    //
    // When default is int 0:
    //   count(0)          → PHP 8 deprecation
    //   foreach(0 as ...) → TypeError
    //   empty(0)          → true (accidentally correct)
    // ──────────────────────────────────────────────────────────────

    #[Test]
    public function privateTmKeyDefaultIsEmptyArray(): void
    {
        $ps = new ProjectStructure();

        // After fix: default is empty array [], not int 0
        self::assertSame([], $ps->private_tm_key);
        self::assertIsArray($ps->private_tm_key);
    }

    #[Test]
    public function privateTmKeyDefaultAllowsCountWithoutDeprecation(): void
    {
        $ps = new ProjectStructure();

        // After fix: default is [], so count() works correctly without deprecation
        self::assertIsArray($ps->private_tm_key);
        self::assertSame(0, count($ps->private_tm_key));
    }

    #[Test]
    public function privateTmKeyDefaultAllowsForeachWithoutWarning(): void
    {
        $ps = new ProjectStructure();

        // After fix: default is [], so foreach works without warning
        self::assertIsArray($ps->private_tm_key);

        $iterations = 0;
        foreach ($ps->private_tm_key as $ignored) {
            $iterations++;
        }

        self::assertSame(0, $iterations);
    }

    #[Test]
    public function privateTmKeyWorksCorrectlyWhenSetToArray(): void
    {
        $ps = new ProjectStructure([
            'private_tm_key' => [
                ['key' => 'abc123', 'name' => 'My TM'],
                ['key' => 'def456', 'name' => 'Other TM'],
            ],
        ]);

        // When properly populated as array, all operations work
        self::assertCount(2, $ps->private_tm_key);
        self::assertSame('abc123', $ps->private_tm_key[0]['key']);
        self::assertSame('Other TM', $ps->private_tm_key[1]['name']);
        self::assertFalse(empty($ps->private_tm_key));

        $keys = [];
        foreach ($ps->private_tm_key as $tmKeyObj) {
            $keys[] = $tmKeyObj['key'];
        }
        self::assertSame(['abc123', 'def456'], $keys);
    }

    #[Test]
    public function privateTmKeyEmptyArrayIsCorrectDefault(): void
    {
        // This is what the default SHOULD be: demonstrates correct behavior
        $ps = new ProjectStructure([
            'private_tm_key' => [],
        ]);

        self::assertIsArray($ps->private_tm_key);
        self::assertCount(0, $ps->private_tm_key);       // No deprecation
        self::assertTrue(empty($ps->private_tm_key));     // Correct
        self::assertEmpty($ps->private_tm_key);

        // foreach on empty array: no iterations, no error
        $count = 0;
        foreach ($ps->private_tm_key as $ignored) {
            $count++;
        }
        self::assertSame(0, $count);
    }

    // ──────────────────────────────────────────────────────────────
    // T2: tm_keys type consistency
    //
    // tm_keys should stay as array throughout its lifecycle.
    // JSON encoding should happen in a local variable, not mutate the property.
    // ──────────────────────────────────────────────────────────────

    #[Test]
    public function tmKeysDefaultIsEmptyArray(): void
    {
        $ps = new ProjectStructure();

        self::assertIsArray($ps->tm_keys);
        self::assertEmpty($ps->tm_keys);
    }

    #[Test]
    public function tmKeysHoldsTmKeyStructures(): void
    {
        $tmKeyData = [
            ['key' => 'abc123', 'name' => 'Main TM', 'r' => true, 'w' => true],
            ['key' => 'def456', 'name' => 'Read-only TM', 'r' => true, 'w' => false],
        ];

        $ps = new ProjectStructure([
            'tm_keys' => $tmKeyData,
        ]);

        self::assertIsArray($ps->tm_keys);
        self::assertCount(2, $ps->tm_keys);
        self::assertSame('abc123', $ps->tm_keys[0]['key']);
        self::assertSame('Read-only TM', $ps->tm_keys[1]['name']);
    }

    #[Test]
    public function tmKeysJsonEncodingDoesNotMutateProperty(): void
    {
        $tmKeyData = [
            ['key' => 'abc123', 'name' => 'Main TM'],
        ];

        $ps = new ProjectStructure([
            'tm_keys' => $tmKeyData,
        ]);

        // JSON encoding should be done on a local variable, not $ps->tm_keys
        $jsonEncoded = json_encode($ps->tm_keys);
        self::assertIsString($jsonEncoded);
        self::assertJson($jsonEncoded);

        // Property must still be an array after encoding
        self::assertIsArray($ps->tm_keys);
        self::assertSame($tmKeyData, $ps->tm_keys);
    }

    #[Test]
    public function tmKeysRemainsArrayAfterToArray(): void
    {
        $ps = new ProjectStructure([
            'tm_keys' => [['key' => 'k1', 'name' => 'TM1']],
        ]);

        $arr = $ps->toArray();
        self::assertIsArray($arr['tm_keys']);
        self::assertSame('k1', $arr['tm_keys'][0]['key']);

        // Property unchanged
        self::assertIsArray($ps->tm_keys);
    }

    // ──────────────────────────────────────────────────────────────
    // D1: GDrive Session dead code
    //
    // GDrive/Session.php:221 checks `instanceof ArrayObject` on
    // session['user']. After migration, session is always a plain
    // array (or null) — that branch is dead.
    // ──────────────────────────────────────────────────────────────

    #[Test]
    public function sessionIsPlainArrayAfterToArrayRoundTrip(): void
    {
        $sessionData = [
            'user' => [
                'uid'   => 42,
                'email' => 'test@example.com',
                'name'  => 'Test User',
            ],
            'token' => 'abc-123',
        ];

        $ps = new ProjectStructure([
            'session' => $sessionData,
        ]);

        $arr = $ps->toArray();

        // session['user'] is a plain array, not ArrayObject
        self::assertIsArray($arr['session']);
        self::assertIsArray($arr['session']['user']);
        self::assertNotInstanceOf(ArrayObject::class, $arr['session']);
        self::assertNotInstanceOf(ArrayObject::class, $arr['session']['user']);
        self::assertSame(42, $arr['session']['user']['uid']);
    }

    #[Test]
    public function sessionWithArrayObjectUserFlattensViaToArray(): void
    {
        // Simulates legacy code that might have set session as ArrayObject
        $sessionAO = new ArrayObject([
            'user' => new ArrayObject([
                'uid'   => 42,
                'email' => 'test@example.com',
            ]),
        ]);

        $ps = new ProjectStructure([
            'session' => $sessionAO,
        ]);

        // Before toArray(), session is ArrayObject
        self::assertInstanceOf(ArrayObject::class, $ps->session);

        // IMPORTANT: toArray() uses reflection on objects. ArrayObject has no
        // public properties (data is in internal C storage), so reflection
        // returns []. This documents that ArrayObject session data is lost
        // through toArray() — proving the migration to plain arrays is correct.
        $arr = $ps->toArray();
        self::assertIsArray($arr['session']);
        self::assertEmpty(
            $arr['session'],
            'toArray() cannot extract data from ArrayObject — proves ArrayObject must not be used for session'
        );
    }

    #[Test]
    public function sessionDefaultIsNull(): void
    {
        $ps = new ProjectStructure();

        self::assertNull($ps->session);
    }

    // ──────────────────────────────────────────────────────────────
    // D3: getRequestedFeatures dead code
    //
    // ProjectManager.php:256 checks `$feature instanceof ArrayObject`.
    // project_features elements should always be plain arrays.
    // ──────────────────────────────────────────────────────────────

    #[Test]
    public function projectFeaturesDefaultIsEmptyArray(): void
    {
        $ps = new ProjectStructure();

        self::assertIsArray($ps->project_features);
        self::assertEmpty($ps->project_features);
    }

    #[Test]
    public function projectFeaturesElementsAreNeverArrayObject(): void
    {
        $ps = new ProjectStructure([
            'project_features' => [
                ['feature_code' => 'translation_versions'],
                ['feature_code' => 'review_extended'],
                ['feature_code' => 'quality_framework'],
            ],
        ]);

        foreach ($ps->project_features as $feature) {
            self::assertNotInstanceOf(
                ArrayObject::class,
                $feature,
                'project_features elements must be plain arrays, not ArrayObject'
            );
            self::assertIsArray($feature);
        }
    }

    #[Test]
    public function projectFeaturesRemainPlainArraysAfterToArray(): void
    {
        $ps = new ProjectStructure([
            'project_features' => [
                ['feature_code' => 'translation_versions'],
            ],
        ]);

        $arr = $ps->toArray();

        self::assertIsArray($arr['project_features']);
        foreach ($arr['project_features'] as $feature) {
            self::assertNotInstanceOf(ArrayObject::class, $feature);
            self::assertIsArray($feature);
        }
    }

    #[Test]
    public function projectFeaturesWithArrayObjectInputFlattensViaToArray(): void
    {
        // Edge case: what if someone passes ArrayObject elements?
        $ps = new ProjectStructure([
            'project_features' => [
                new ArrayObject(['feature_code' => 'quality_framework']),
            ],
        ]);

        // Before toArray(), the element is ArrayObject
        self::assertInstanceOf(ArrayObject::class, $ps->project_features[0]);

        // After toArray(): ArrayObject has no public properties visible to
        // reflection, so the element becomes []. This documents that
        // ArrayObject must NOT be used for project_features — the branch
        // in ProjectManager.php:256 that checks instanceof ArrayObject is dead.
        $arr = $ps->toArray();
        self::assertIsArray($arr['project_features'][0]);
        self::assertEmpty(
            $arr['project_features'][0],
            'ArrayObject element loses data through toArray() — confirms dead code path'
        );
    }

    // ──────────────────────────────────────────────────────────────
    // D5: Removed properties — file_part_id and file_metadata
    //
    // These properties were removed from ProjectStructure because
    // they had zero production reads/writes. Verify they are gone.
    // ──────────────────────────────────────────────────────────────

    #[Test]
    public function filePartIdPropertyWasRemoved(): void
    {
        $ps = new ProjectStructure();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Unknown property');

        $ps->file_part_id = [];
    }

    #[Test]
    public function fileMetadataPropertyWasRemoved(): void
    {
        $ps = new ProjectStructure();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Unknown property');

        $ps->file_metadata = [];
    }

    // ──────────────────────────────────────────────────────────────
    // N3/N4: TmKeyService fragility with private_tm_key
    //
    // TmKeyService::setPrivateTMKeys() starts with:
    //   foreach ($projectStructure->private_tm_key as $_tmKey) { ... }
    //
    // When private_tm_key = [] (correct default), the foreach
    // simply doesn't execute. When it's int 0, it throws TypeError.
    // ──────────────────────────────────────────────────────────────

    #[Test]
    public function foreachOnEmptyArrayPrivateTmKeyExecutesZeroIterations(): void
    {
        $ps = new ProjectStructure([
            'private_tm_key' => [],
        ]);

        // Simulates the TmKeyService::setPrivateTMKeys() opening loop
        $iterations = 0;
        foreach ($ps->private_tm_key as $tmKeyObj) {
            $iterations++;
        }

        self::assertSame(0, $iterations);
    }

    #[Test]
    public function foreachOnDefaultPrivateTmKeyTriggersNoWarning(): void
    {
        $ps = new ProjectStructure();

        // After fix: default is [], so no warning is triggered
        self::assertSame([], $ps->private_tm_key);

        // Simulates TmKeyService::setPrivateTMKeys() opening loop
        $warningTriggered = false;
        set_error_handler(function (int $errno, string $errstr) use (&$warningTriggered): bool {
            if (str_contains($errstr, 'foreach() argument must be of type array|object')) {
                $warningTriggered = true;
            }
            return true;
        });

        try {
            $iterations = 0;
            foreach ($ps->private_tm_key as $tmKeyObj) {
                $iterations++;
            }
        } finally {
            restore_error_handler();
        }

        self::assertFalse($warningTriggered, 'foreach on empty array should not trigger any warning');
        self::assertSame(0, $iterations);
    }

    #[Test]
    public function privateTmKeyAsArrayAllowsTmKeyServicePattern(): void
    {
        // N3/N4: Full pattern from TmKeyService::setPrivateTMKeys()
        $ps = new ProjectStructure([
            'private_tm_key' => [
                ['key' => 'valid-key-1', 'name' => 'TM One'],
                ['key' => 'valid-key-2', 'name' => 'TM Two'],
            ],
        ]);

        // Simulates the validation loop in setPrivateTMKeys
        $validatedKeys = [];
        foreach ($ps->private_tm_key as $_tmKey) {
            self::assertArrayHasKey('key', $_tmKey);
            self::assertArrayHasKey('name', $_tmKey);
            $validatedKeys[] = $_tmKey['key'];
        }

        self::assertSame(['valid-key-1', 'valid-key-2'], $validatedKeys);
    }

    #[Test]
    public function privateTmKeyEmptyArrayPassesPushTmxEarlyReturnCheck(): void
    {
        // TmKeyService::pushTMXToMyMemory() checks:
        //   empty($projectStructure->private_tm_key[0]['key'] ?? null)
        $ps = new ProjectStructure([
            'private_tm_key' => [],
        ]);

        // This is the guard check — should evaluate to true (early return)
        self::assertTrue(empty($ps->private_tm_key[0]['key'] ?? null));
    }

    // ──────────────────────────────────────────────────────────────
    // Serialization round-trip
    //
    // ProjectQueue::sendProject() does toArray() before serializing.
    // ProjectCreationWorker does new ProjectStructure($array) to rebuild.
    // ──────────────────────────────────────────────────────────────

    #[Test]
    public function serializationRoundTripPreservesAllPropertyTypes(): void
    {
        $original = new ProjectStructure([
            // Identity
            'id_project'      => 999,
            'ppassword'       => 'secret-pass',
            'uid'             => 42,
            'id_customer'     => 'acme_corp',
            'owner'           => 'admin@example.com',
            'userIsLogged'    => true,
            'create_date'     => '2025-01-15 10:30:00',
            'instance_id'     => 5,

            // Project metadata
            'project_name'    => 'Audit Test',
            'source_language' => 'en-US',
            'target_language' => ['it-IT', 'de-DE', 'fr-FR'],
            'job_subject'     => 'legal',
            'due_date'        => '2025-02-01',

            // TM/MT configuration
            'mt_engine'       => 1,
            'tms_engine'      => 1,
            'private_tm_key'  => [['key' => 'abc123', 'name' => 'Main TM']],
            'pretranslate_100' => 1,
            'pretranslate_101' => 0,
            'only_private'     => 1,
            'tm_keys'          => [['key' => 'abc123', 'name' => 'Main TM', 'r' => true, 'w' => true]],

            // Pipeline state
            'status'           => 'NOT_READY_FOR_ANALYSIS',
            'id_team'          => 7,
            'project_features' => [
                ['feature_code' => 'translation_versions'],
                ['feature_code' => 'review_extended'],
            ],

            // Per-file transient
            'array_files'      => ['doc1.xlf', 'doc2.xlf'],
            'array_files_meta' => [
                ['extension' => 'xlf'],
                ['extension' => 'xlf'],
            ],
            'file_id_list'     => [101, 102],

            // Output
            'result'           => ['errors' => [], 'data' => ['id' => 999]],
            'array_jobs'       => [
                'job_list'      => [1],
                'job_pass'      => ['pass1'],
                'job_segments'  => [[1, 50]],
                'job_languages' => ['it-IT'],
                'payable_rates' => [0.05],
            ],

            // Session
            'session'          => [
                'user'  => ['uid' => 42, 'email' => 'admin@example.com'],
                'token' => 'session-token',
            ],

            // Notes (plain array form)
            'notes'            => [
                'seg-1' => ['entries' => [['type' => 'comment', 'content' => 'Review']]],
            ],

            // Features
            'features'         => ['quality_framework' => true],
            'create_2_pass_review' => true,
        ]);

        // Step 1: toArray() — simulates ProjectQueue::sendProject()
        $serialized = $original->toArray();
        self::assertIsArray($serialized);

        // Step 2: new ProjectStructure($array) — simulates ProjectCreationWorker
        $restored = new ProjectStructure($serialized);

        // Step 3: Verify scalar properties
        self::assertSame(999, $restored->id_project);
        self::assertSame('secret-pass', $restored->ppassword);
        self::assertSame(42, $restored->uid);
        self::assertSame('acme_corp', $restored->id_customer);
        self::assertSame('admin@example.com', $restored->owner);
        self::assertTrue($restored->userIsLogged);
        self::assertSame('2025-01-15 10:30:00', $restored->create_date);
        self::assertSame(5, $restored->instance_id);
        self::assertSame('Audit Test', $restored->project_name);
        self::assertSame('en-US', $restored->source_language);
        self::assertSame('legal', $restored->job_subject);
        self::assertSame('2025-02-01', $restored->due_date);
        self::assertSame(1, $restored->mt_engine);
        self::assertSame('NOT_READY_FOR_ANALYSIS', $restored->status);
        self::assertSame(7, $restored->id_team);
        self::assertTrue($restored->create_2_pass_review);

        // Step 4: Verify array properties
        self::assertSame(['it-IT', 'de-DE', 'fr-FR'], $restored->target_language);
        self::assertSame([['key' => 'abc123', 'name' => 'Main TM']], $restored->private_tm_key);
        self::assertSame([['key' => 'abc123', 'name' => 'Main TM', 'r' => true, 'w' => true]], $restored->tm_keys);
        self::assertSame(['doc1.xlf', 'doc2.xlf'], $restored->array_files);
        self::assertSame([101, 102], $restored->file_id_list);

        // Step 5: Verify nested structures
        self::assertSame(
            [['feature_code' => 'translation_versions'], ['feature_code' => 'review_extended']],
            $restored->project_features
        );
        self::assertSame(['errors' => [], 'data' => ['id' => 999]], $restored->result);
        self::assertSame('session-token', $restored->session['token']);
        self::assertSame(42, $restored->session['user']['uid']);

        // Step 6: Notes survived
        self::assertTrue(isset($restored->notes['seg-1']));
        self::assertSame('Review', $restored->notes['seg-1']['entries'][0]['content']);

        // Step 7: Features survived
        self::assertTrue($restored->features['quality_framework']);
    }

    #[Test]
    public function serializationRoundTripRejectsArrayObjectForTypedProperties(): void
    {
        // With strict array types, assigning ArrayObject throws TypeError.
        $this->expectException(\TypeError::class);
        new ProjectStructure([
            'segments' => new ArrayObject([
                1 => new ArrayObject(['sid' => 1, 'source' => 'Hello']),
            ]),
        ]);
    }

    #[Test]
    public function serializationRoundTripWorksWithPlainArrayPipelineData(): void
    {
        // The correct pattern: pipeline data as plain arrays (not ArrayObject)
        // survives serialization round-trip perfectly.
        $original = new ProjectStructure([
            'segments' => [
                1 => ['sid' => 1, 'source' => 'Hello'],
                2 => ['sid' => 2, 'source' => 'World'],
            ],
            'notes' => [
                'seg-1' => [
                    'entries' => [
                        ['type' => 'comment', 'content' => 'Note text'],
                    ],
                ],
            ],
            'context_group' => [
                'ctx-1' => ['context_type' => 'x-pos', 'value' => '5'],
            ],
        ]);

        $serialized = $original->toArray();

        self::assertIsArray($serialized['segments']);
        self::assertSame('Hello', $serialized['segments'][1]['source']);
        self::assertSame('Note text', $serialized['notes']['seg-1']['entries'][0]['content']);
        self::assertSame('x-pos', $serialized['context_group']['ctx-1']['context_type']);

        $restored = new ProjectStructure($serialized);

        self::assertSame('Hello', $restored->segments[1]['source']);
        self::assertSame('Note text', $restored->notes['seg-1']['entries'][0]['content']);
        self::assertSame('x-pos', $restored->context_group['ctx-1']['context_type']);
    }

    #[Test]
    public function serializationRoundTripPreservesDefaultsForUnsetProperties(): void
    {
        // Minimal ProjectStructure — most properties stay at defaults
        $original = new ProjectStructure([
            'id_project'   => 1,
            'project_name' => 'Minimal',
        ]);

        $serialized = $original->toArray();
        $restored = new ProjectStructure($serialized);

        // Verify key defaults survived the round-trip
        self::assertNull($restored->ppassword);
        self::assertNull($restored->uid);
        self::assertSame('translated_user', $restored->id_customer);
        self::assertFalse($restored->userIsLogged);
        self::assertSame([], $restored->private_tm_key);
        self::assertSame([], $restored->tm_keys);
        self::assertSame([], $restored->project_features);
        self::assertEmpty($restored->notes);
        self::assertNull($restored->session);
        self::assertSame(['errors' => [], 'data' => []], $restored->result);
        self::assertFalse($restored->create_2_pass_review);
    }

    #[Test]
    public function jsonSerializeDelegatesToToArray(): void
    {
        // Use plain arrays (not ArrayObject) to test the JSON round-trip properly
        $ps = new ProjectStructure([
            'id_project'   => 42,
            'project_name' => 'JSON Test',
            'notes'        => [
                'u1' => ['entries' => [['type' => 'note', 'content' => 'Hello']]],
            ],
        ]);

        $jsonResult = $ps->jsonSerialize();
        $toArrayResult = $ps->toArray();

        // jsonSerialize() delegates to toArray() — results must be identical
        self::assertSame($toArrayResult, $jsonResult);

        // JSON encoding should work without error
        $json = json_encode($ps);
        self::assertIsString($json);
        self::assertJson($json);

        // Decode and verify
        $decoded = json_decode($json, true);
        self::assertSame(42, $decoded['id_project']);
        self::assertSame('JSON Test', $decoded['project_name']);
        self::assertIsArray($decoded['notes']['u1']);
        self::assertSame('Hello', $decoded['notes']['u1']['entries'][0]['content']);
    }

    // ──────────────────────────────────────────────────────────────
    // Edge cases: DomainException on unknown properties
    // ──────────────────────────────────────────────────────────────

    #[Test]
    public function settingUnknownPropertyThrowsDomainException(): void
    {
        $ps = new ProjectStructure();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Unknown property');

        $ps->completely_bogus_property = 'should fail';
    }

    #[Test]
    public function constructorWithUnknownKeyThrowsDomainException(): void
    {
        $this->expectException(\DomainException::class);

        new ProjectStructure([
            'nonexistent_key' => 'value',
        ]);
    }
}
