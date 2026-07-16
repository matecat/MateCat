<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 02/12/15
 * Time: 17.00
 *
 */

namespace Utils\TaskRunner\Commons;

/**
 * Class ContextList
 *
 * Vector container for contextes
 *
 * @package TaskRunner\Commons
 * @phpstan-consistent-constructor
 */
class ContextList
{

    /**
     * Variable holding contextes
     *
     * @var Context[]
     */
    public array $list = [];

    /**
     * QueuesList constructor.
     *
     * @param array<string, array<string, mixed>> $queue_info
     * @throws \TypeError
     */
    protected function __construct(array $queue_info)
    {
        foreach ($queue_info as $level => $values) {
            $this->list[$level] = Context::buildFromArray($values);
        }
    }

    /**
     * Static class builder
     *
     * @param array<string, array<string, mixed>> $queue_info
     *
     * @return static
     * @throws \TypeError
     */
    public static function get(array $queue_info = []): ContextList
    {
        return new static($queue_info);
    }

}