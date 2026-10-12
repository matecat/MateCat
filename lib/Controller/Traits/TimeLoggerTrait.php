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
use Klein\Request;
use Utils\Logger\LoggerFactory;
use Utils\Tools\Utils;

trait TimeLoggerTrait
{

    /**
     * Provided by the host class (KleinController).
     */
    protected Request $request;

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
        // Read from the request the controller was given rather than from $_SERVER. The two carry
        // the same value under Apache, but only one of them exists off the web: the superglobal has
        // no REQUEST_URI under the CLI, so logging a page call from a test warned about a missing
        // key and then passed null to parse_url(). Request::uri() reads the server collection the
        // request was constructed with and falls back to "/", so there is nothing to prop up.
        $_request_uri = parse_url($this->request->uri());
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