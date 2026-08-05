<?php

namespace Controller\API\App;

use Controller\Abstracts\AbstractStatefulKleinController;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use InvalidArgumentException;
use Klein\Exceptions\LockedResponseException;
use Klein\Exceptions\ResponseAlreadySentException;
use Model\ConnectedServices\GDrive\Session;
use PDOException;
use RuntimeException;
use TypeError;
use Utils\TMS\TMSService;

/**
 * Stateful because clearNotCompletedUploads() clears the pending GDrive upload list, which lives in the
 * session. Another restored regression: the 2015 predecessor declared its session explicitly with a
 * `//SESSION ENABLED` comment, and the migration to KleinController dropped it.
 *
 * The endpoint has been inert ever since. With no session open, GDrive\Session's constructor early-
 * returns on the missing uid, clearSession() empties the default array, and POST
 * /api/app/clear-not-completed-uploads returns {"success": true} having done nothing. It also omitted
 * the SessionStore argument its stateful siblings pass, so it fell back to a PhpSessionStore over an
 * unopened session; that argument is now supplied.
 */
class AjaxUtilsController extends AbstractStatefulKleinController
{

    protected function registerValidators(): void
    {
        $this->appendValidator(new LoginValidator($this));
    }

    /**
     * @throws LockedResponseException
     * @throws PDOException
     * @throws ResponseAlreadySentException
     */
    public function ping(): void
    {
        $stmt = $this->getDatabase()->getConnection()->prepare("SELECT 1");
        $stmt->execute();

        $this->response->json([
            'data' => [
                "OK",
                time()
            ]
        ]);
    }

    /**
     * @return void
     * @throws Exception
     */
    public function checkTMKey(): void
    {
        $tm_key = filter_var($this->request->param('tm_key'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW]);

        if (empty($tm_key)) {
            throw new InvalidArgumentException("TM key not provided.", -9);
        }

        $tmxHandler = new TMSService($this->getDatabase());
        $keyExists = $tmxHandler->checkCorrectKey($tm_key);

        if (!isset($keyExists) or $keyExists === false) {
            throw new InvalidArgumentException("TM key is not valid.", -9);
        }

        $this->response->json([
            'success' => true
        ]);
    }

    /**
     * @return void
     * @throws Exception
     * @throws RuntimeException
     * @throws TypeError
     */
    public function clearNotCompletedUploads(): void
    {
        // No acting user passed: clearSession() only drops the gdrive subtree, and this runs in a
        // daemon context where identifyUser() never populated one.
        (new Session($this->getDatabase(), $this->sessionStore()))->clearSession();

        $this->response->json([
            'success' => true
        ]);
    }
}