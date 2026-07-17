<?php
/**
 * Created by PhpStorm.
 * @author ostico domenico@translated.net / ostico@gmail.com
 * Date: 30/05/19
 * Time: 12.05
 *
 */

namespace Controller\API\App;


use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\WhitelistAccessValidator;
use PDOException;
use RuntimeException;
use Utils\Registry\AppConfig;
use View\API\App\Json\Ping;

class HeartBeat extends KleinController
{

    protected function registerValidators(): void
    {
        $this->appendValidator(new WhitelistAccessValidator($this));
    }

    /**
     * @throws PDOException
     * @throws RuntimeException
     */
    public function ping(): void
    {
        $this->getDatabase()->ping();
        $touchFile = AppConfig::$ROOT . DIRECTORY_SEPARATOR . "touch";
        // Guard the directory before touch() so an unavailable storage path is reported as a
        // RuntimeException rather than leaking a "No such file or directory" PHP warning.
        if (!is_dir(AppConfig::$ROOT) || !is_writable(AppConfig::$ROOT) || !touch($touchFile)) {
            throw new RuntimeException("Storage unavailable.");
        }

        $format = new Ping($this);
        $this->response->json($format->render());
    }

}