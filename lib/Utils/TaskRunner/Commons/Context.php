<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 23/12/15
 * Time: 20.55
 *
 */

namespace Utils\TaskRunner\Commons;

use Stringable;
use TypeError;

/**
 * Class Context
 *
 * Context definition for the Executors
 *
 * @package TaskRunner\Commons
 * @phpstan-consistent-constructor
 */
class Context implements Stringable
{
    /**
     * A cap set to this value is off.
     */
    private const int NO_CAP = 0;

    /**
     * Longest raw value kept in $invalid_settings, so a stray ini line cannot bloat the executors' argv.
     */
    private const int MAX_RECORDED_LENGTH = 100;

    /**
     * The name for the queue on AMQ
     * @var string
     */
    public string $queue_name;

    /**
     * Name of the set of processes in which every pid will be put by TaskManager
     * @var string
     */
    public string $pid_set_name;

    /**
     * Number of the processes actually tied to that queue
     * @var int
     */
    public int $pid_list_len = 0;

    /**
     * Max processes that must be tied the queue
     * @var int
     */
    public int $max_executors = 0;

    /**
     * Default Logger name
     * @var string
     */
    public string $loggerName = 'Executor.log';

    /**
     * @var string $redis_key
     */
    public string $redis_key;

    /**
     * Segment messages queued here at which no new project is routed to this queue; 0 = no cap.
     *
     * Only FastAnalysis reads it today, for the Lara queues. Example: 25000 stops new projects at 25,000 messages.
     */
    public int $max_queued_segments = self::NO_CAP;

    /**
     * Projects still in analysis here at which no new project is routed to this queue; 0 = no cap.
     */
    public int $max_queued_projects = self::NO_CAP;

    /**
     * False while the queue is being drained: no new project is routed here, its workers keep running.
     */
    public bool $accept_new_projects = true;

    /**
     * Settings rejected while reading the config, as key => raw value; each one kept its default.
     *
     * Example: `P4[max_queued_segments] = 25.000` gives `['max_queued_segments' => '25.000']` and no cap.
     *
     * @var array<string, string>
     */
    public array $invalid_settings = [];

    /**
     * AbstractContext constructor.
     *
     * @param array<string, mixed> $queueElement
     *
     * @throws TypeError
     */
    protected function __construct(array $queueElement)
    {
        $this->queue_name = $queueElement['queue_name'];
        $this->pid_set_name = $queueElement['queue_name'] . '_pid_set';
        $this->max_executors = $queueElement['max_executors'];
        $this->redis_key = $queueElement['queue_name'] . '_redis_key';
        $this->loggerName = $queueElement['queue_name'] . '.log';

        $this->max_queued_segments = $this->readCap($queueElement, 'max_queued_segments');
        $this->max_queued_projects = $this->readCap($queueElement, 'max_queued_projects');
        $this->accept_new_projects = $this->readSwitch($queueElement, 'accept_new_projects', true);
    }

    /**
     * Reads a cap written with digits only; any other value turns the cap off and is recorded.
     *
     * Example: '25000' gives 25000; '25.000', ' 12', '007' and '99999999999999999999' give no cap.
     *
     * @param array<string, mixed> $queueElement
     */
    private function readCap(array $queueElement, string $key): int
    {
        if (!array_key_exists($key, $queueElement)) {
            return self::NO_CAP;
        }

        $raw = $queueElement[$key];

        // parse_ini_file() turns `off`, `no` and `false` into ''.
        if ($raw === '') {
            return self::NO_CAP;
        }

        if (is_int($raw) && $raw >= 0) {
            return $raw;
        }

        // FILTER_VALIDATE_INT refuses a number too big for an int, and leading zeros.
        if (is_string($raw) && preg_match('/^\d+\z/', $raw) === 1) {
            $cap = filter_var($raw, FILTER_VALIDATE_INT);
            if ($cap !== false) {
                return $cap;
            }
        }

        $this->recordInvalid($key, $raw);

        return self::NO_CAP;
    }

    /**
     * Reads an on/off setting: a bool, or '1' for on and '0' or '' for off; any other value gives $default.
     *
     * Example: ini `off` arrives as '' and gives false; 'yes please' gives $default and is recorded.
     *
     * @param array<string, mixed> $queueElement
     */
    private function readSwitch(array $queueElement, string $key, bool $default): bool
    {
        if (!array_key_exists($key, $queueElement)) {
            return $default;
        }

        $raw = $queueElement[$key];

        // A bool arrives when executor_worker.php rebuilds the context from TaskManager's json_encode().
        if (is_bool($raw)) {
            return $raw;
        }

        if (in_array($raw, ['1', 1], true)) {
            return true;
        }

        if (in_array($raw, ['0', 0, ''], true)) {
            return false;
        }

        $this->recordInvalid($key, $raw);

        return $default;
    }

    /**
     * Keeps a rejected value for FastAnalysis to log, cleaned so the context still json-encodes.
     *
     * Example: an ini value with a broken byte, "25\xff000", is kept as "25?000"; -5 is kept as '-5'.
     */
    private function recordInvalid(string $key, mixed $raw): void
    {
        // A string is kept as written; json_encode() spells out the rest (-5, true, an array).
        $shown = is_string($raw) ? $raw : (string)json_encode($raw, JSON_INVALID_UTF8_SUBSTITUTE);

        // Invalid UTF-8 would make TaskManager's json_encode($context) fail, and the queue's workers would not start.
        $this->invalid_settings[$key] = mb_substr(mb_scrub($shown, 'UTF-8'), 0, self::MAX_RECORDED_LENGTH);
    }

    /**
     * Concrete Static builder method
     *
     * @param array<string, mixed> $context
     *
     * @return self
     *
     * @throws TypeError
     */
    public static function buildFromArray(array $context): Context
    {
        return new static($context);
    }

    /**
     * Magic to string method
     *
     * @return string
     */
    public function __toString(): string
    {
        return json_encode($this) ?: '{}';
    }

}