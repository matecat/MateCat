<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 02/05/19
 * Time: 19.12
 *
 */

namespace Controller\Traits;


use InvalidArgumentException;
use Utils\Logger\LoggerFactory;
use Utils\Tools\Utils;

trait TimeLoggerTrait
{

    protected string $timingLogFileName = 'fallback_calls_time.log';

    /**
     * @var array<string, mixed>
     */
    protected array $timingCustomObject = [];
    protected float $startExecutionTime = 0;

    protected function startTimer(): void
    {
        $this->startExecutionTime = microtime(true);
    }

    public function getTimer(): float
    {
        return round(microtime(true) - $this->startExecutionTime, 4); //get milliseconds
    }

    /**
     * @return void
     * @throws InvalidArgumentException
     */
    protected function logPageCall(): void
    {
        $_request_uri = parse_url($_SERVER['REQUEST_URI']);
        if (is_array($_request_uri) && isset($_request_uri['query'])) {
            parse_str($_request_uri['query'], $str);
            $_request_uri['query'] = $str;
        }

        $object = [
            "user" => ($this->isLoggedIn() ? [
                "uid" => $this->getUser()->getUid(),
                "email" => $this->getUser()->getEmail(),
                "first_name" => $this->getUser()->getFirstName(),
                "lat_name" => $this->getUser()->getLastName()
            ] : ["uid" => 0]),
            "custom_object" => (object)$this->timingCustomObject,
            "browser" => Utils::getBrowser(),
            "request_uri" => $_request_uri,
            "Total Time" => $this->getTimer()
        ];

        $logger = LoggerFactory::getLogger($this->timingLogFileName, $this->timingLogFileName);
        $logger->debug($object);
    }

}