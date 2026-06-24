<?php
/**
 * Created by PhpStorm.
 * @author hashashiyyin domenico@translated.net / ostico@gmail.com
 * Date: 30/07/24
 * Time: 13:17
 *
 */

namespace Utils\TaskRunner\Commons;

use Exception;

class Configuration
{

    /**
     * @var ContextList
     */
    protected ContextList $_contextList;

    protected string $_loggerName;

    /** @var array<string, mixed> */
    private array $__raw;

    /**
     * @param string $rawConfig
     * @param string|null $contextIndex
     *
     * @throws Exception
     * @throws \TypeError
     */
    public function __construct(string $rawConfig, ?string $contextIndex = null)
    {
        $config = @parse_ini_file($rawConfig, true);

        if (empty($rawConfig) || empty($config['context_definitions'])) {
            throw new Exception('Wrong configuration file provided.');
        }

        if (!isset($contextIndex)) {
            $this->_contextList = ContextList::get($config['context_definitions']);
        } else {
            $this->_contextList = ContextList::get($config['context_definitions'][$contextIndex]);
        }

        $this->_loggerName = $config['loggerName'];
        $this->__raw = $config;
    }

    public function getContextList(): ContextList
    {
        return $this->_contextList;
    }

    public function getLoggerName(): string
    {
        return $this->_loggerName;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRaw(): array
    {
        return $this->__raw;
    }

}