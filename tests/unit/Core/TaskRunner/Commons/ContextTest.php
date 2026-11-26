<?php

namespace Matecat\Core\TaskRunner\Commons;

use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Utils\TaskRunner\Commons\Configuration;
use Utils\TaskRunner\Commons\Context;

class ContextTest extends AbstractTest
{
    private array $queueElement = [
        'queue_name'    => 'test_queue',
        'max_executors' => 5,
    ];

    // ─── buildFromArray sets all properties ─────────────────────────────────────

    #[Test]
    public function buildFromArray_sets_queue_name(): void
    {
        $context = Context::buildFromArray($this->queueElement);

        $this->assertSame('test_queue', $context->queue_name);
    }

    #[Test]
    public function buildFromArray_sets_max_executors(): void
    {
        $context = Context::buildFromArray($this->queueElement);

        $this->assertSame(5, $context->max_executors);
    }

    #[Test]
    public function buildFromArray_sets_pid_list_len_to_zero(): void
    {
        $context = Context::buildFromArray($this->queueElement);

        $this->assertSame(0, $context->pid_list_len);
    }

    // ─── pid_set_name derived from queue_name ────────────────────────────────────

    #[Test]
    public function buildFromArray_derives_pid_set_name_from_queue_name(): void
    {
        $context = Context::buildFromArray($this->queueElement);

        $this->assertSame('test_queue_pid_set', $context->pid_set_name);
    }

    // ─── redis_key derived from queue_name ───────────────────────────────────────

    #[Test]
    public function buildFromArray_derives_redis_key_from_queue_name(): void
    {
        $context = Context::buildFromArray($this->queueElement);

        $this->assertSame('test_queue_redis_key', $context->redis_key);
    }

    // ─── loggerName derived from queue_name ──────────────────────────────────────

    #[Test]
    public function buildFromArray_derives_logger_name_from_queue_name(): void
    {
        $context = Context::buildFromArray($this->queueElement);

        $this->assertSame('test_queue.log', $context->loggerName);
    }

    // ─── buildFromArray returns Context instance ─────────────────────────────────

    #[Test]
    public function buildFromArray_returns_context_instance(): void
    {
        $context = Context::buildFromArray($this->queueElement);

        $this->assertInstanceOf(Context::class, $context);
    }

    // ─── __toString returns valid JSON ───────────────────────────────────────────

    #[Test]
    public function toString_returns_valid_json_string(): void
    {
        $context = Context::buildFromArray($this->queueElement);

        $json = (string) $context;

        $this->assertJson($json);
    }

    #[Test]
    public function toString_json_contains_queue_name(): void
    {
        $context = Context::buildFromArray($this->queueElement);

        $json   = (string) $context;
        $decoded = json_decode($json, true);

        $this->assertSame('test_queue', $decoded['queue_name']);
    }

    #[Test]
    public function toString_json_contains_max_executors(): void
    {
        $context = Context::buildFromArray($this->queueElement);

        $json    = (string) $context;
        $decoded = json_decode($json, true);

        $this->assertSame(5, $decoded['max_executors']);
    }

    #[Test]
    public function toString_json_contains_pid_set_name(): void
    {
        $context = Context::buildFromArray($this->queueElement);

        $json    = (string) $context;
        $decoded = json_decode($json, true);

        $this->assertSame('test_queue_pid_set', $decoded['pid_set_name']);
    }

    #[Test]
    public function toString_json_contains_redis_key(): void
    {
        $context = Context::buildFromArray($this->queueElement);

        $json    = (string) $context;
        $decoded = json_decode($json, true);

        $this->assertSame('test_queue_redis_key', $decoded['redis_key']);
    }

    // ─── Different queue names produce correct derived values ────────────────────

    #[Test]
    public function buildFromArray_with_different_queue_name_derives_correct_values(): void
    {
        $context = Context::buildFromArray([
            'queue_name'    => 'analysis_queue',
            'max_executors' => 10,
        ]);

        $this->assertSame('analysis_queue', $context->queue_name);
        $this->assertSame('analysis_queue_pid_set', $context->pid_set_name);
        $this->assertSame('analysis_queue_redis_key', $context->redis_key);
        $this->assertSame('analysis_queue.log', $context->loggerName);
        $this->assertSame(10, $context->max_executors);
    }

    // ─── Queue caps read from the task manager config ────────────────────────────

    #[Test]
    public function buildFromArray_without_cap_settings_leaves_the_caps_off(): void
    {
        $context = Context::buildFromArray($this->queueElement);

        $this->assertSame(0, $context->max_queued_segments);
        $this->assertSame(0, $context->max_queued_projects);
        $this->assertTrue($context->accept_new_projects);
        $this->assertSame([], $context->invalid_settings);
    }

    /**
     * @return array<string, array{mixed, mixed, int, int}>
     */
    public static function validCapsProvider(): array
    {
        return [
            'ini strings'                   => ['25000', '80', 25000, 80],
            'ints from the json hand-off'   => [25000, 80, 25000, 80],
            'ini off, no, false or nothing' => ['', '', 0, 0],
            'zero'                          => ['0', 0, 0, 0],
        ];
    }

    #[Test]
    #[DataProvider('validCapsProvider')]
    public function buildFromArray_reads_valid_caps(mixed $segments, mixed $projects, int $expectedSegments, int $expectedProjects): void
    {
        $context = Context::buildFromArray($this->queueElement + [
                'max_queued_segments' => $segments,
                'max_queued_projects' => $projects,
            ]);

        $this->assertSame($expectedSegments, $context->max_queued_segments);
        $this->assertSame($expectedProjects, $context->max_queued_projects);
        $this->assertSame([], $context->invalid_settings);
    }

    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function acceptNewProjectsProvider(): array
    {
        return [
            'ini off'    => ['', false],
            'ini 0'      => ['0', false],
            'ini on'     => ['1', true],
            'json false' => [false, false],
            'json true'  => [true, true],
        ];
    }

    #[Test]
    #[DataProvider('acceptNewProjectsProvider')]
    public function buildFromArray_maps_accept_new_projects_to_a_bool(mixed $raw, bool $expected): void
    {
        $context = Context::buildFromArray($this->queueElement + ['accept_new_projects' => $raw]);

        $this->assertSame($expected, $context->accept_new_projects);
        $this->assertSame([], $context->invalid_settings);
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function invalidCapProvider(): array
    {
        return [
            'dot as thousands separator'   => ['25.000', '25.000'],
            'comma as thousands separator' => ['25,000', '25,000'],
            'exponent'                     => ['1e5', '1e5'],
            'negative string'              => ['-5', '-5'],
            'negative int'                 => [-5, '-5'],
            'word'                         => ['abc', 'abc'],
            'leading space'                => [' 12', ' 12'],
            'trailing newline'             => ["12\n", "12\n"],
            'leading zeros'                => ['007', '007'],
            'too big for an int'           => ['99999999999999999999', '99999999999999999999'],
            'null'                         => [null, 'null'],
            'array'                        => [[1], '[1]'],
        ];
    }

    #[Test]
    #[DataProvider('invalidCapProvider')]
    public function buildFromArray_turns_an_invalid_cap_off_and_records_it(mixed $raw, string $recorded): void
    {
        $context = Context::buildFromArray($this->queueElement + ['max_queued_segments' => $raw]);

        $this->assertSame(0, $context->max_queued_segments);
        $this->assertSame(['max_queued_segments' => $recorded], $context->invalid_settings);
    }

    #[Test]
    public function buildFromArray_records_each_invalid_setting_under_its_own_key(): void
    {
        $context = Context::buildFromArray($this->queueElement + [
                'max_queued_segments' => '25000',
                'max_queued_projects' => '8O',
                'accept_new_projects' => 'yes please',
            ]);

        $this->assertSame(25000, $context->max_queued_segments);
        $this->assertSame(0, $context->max_queued_projects);
        $this->assertTrue($context->accept_new_projects);
        $this->assertSame(
            ['max_queued_projects' => '8O', 'accept_new_projects' => 'yes please'],
            $context->invalid_settings
        );
    }

    #[Test]
    public function buildFromArray_cleans_an_invalid_value_so_the_context_still_encodes(): void
    {
        $context = Context::buildFromArray($this->queueElement + [
                'max_queued_segments' => "25\xff000",
                'max_queued_projects' => str_repeat('9.', 200),
            ]);

        $this->assertSame("25?000", $context->invalid_settings['max_queued_segments']);
        $this->assertSame(100, mb_strlen($context->invalid_settings['max_queued_projects']));
        $this->assertJson((string)$context);
        $this->assertNotSame('{}', (string)$context);
    }

    #[Test]
    public function buildFromArray_rebuilt_from_its_own_json_keeps_the_caps(): void
    {
        // TaskManager passes json_encode($context) to executor_worker.php, which calls buildFromArray() again.
        $original = Context::buildFromArray($this->queueElement + [
                'max_queued_segments' => '25000',
                'max_queued_projects' => '80',
                'accept_new_projects' => '0',
            ]);

        $rebuilt = Context::buildFromArray(json_decode((string)$original, true));

        $this->assertSame(25000, $rebuilt->max_queued_segments);
        $this->assertSame(80, $rebuilt->max_queued_projects);
        $this->assertFalse($rebuilt->accept_new_projects);
        $this->assertSame([], $rebuilt->invalid_settings);
    }

    #[Test]
    public function the_caps_are_read_from_a_real_task_manager_ini_file(): void
    {
        // Pins the parse_ini_file() conversions the parsing relies on: `off` gives '', digits stay strings.
        $iniFile = tempnam(sys_get_temp_dir(), 'context_caps_');
        file_put_contents($iniFile, <<<'INI'
            loggerName = "test.log"
            [context_definitions]
            P4[queue_name] = "analysis_queue_P4"
            P4[max_executors] = 3
            P4[max_queued_segments] = 25000
            P4[max_queued_projects] = 25.000
            P4[accept_new_projects] = off
            INI);

        try {
            $context = (new Configuration($iniFile))->getContextList()->list['P4'];
        } finally {
            unlink($iniFile);
        }

        $this->assertSame(25000, $context->max_queued_segments);
        $this->assertSame(0, $context->max_queued_projects);
        $this->assertFalse($context->accept_new_projects);
        $this->assertSame(['max_queued_projects' => '25.000'], $context->invalid_settings);
    }
}
