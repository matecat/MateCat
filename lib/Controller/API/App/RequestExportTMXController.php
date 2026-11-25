<?php

namespace Controller\API\App;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use InvalidArgumentException;
use Utils\TmKeyManagement\TmKeyManager;
use Utils\TMS\TMSService;
use Utils\Validation\UserSuppliedName;

class RequestExportTMXController extends KleinController
{

    protected function registerValidators(): void
    {
        $this->appendValidator(new LoginValidator($this));
    }

    /**
     * @throws Exception
     */
    public function download(): void
    {
        $request = $this->validateTheRequest();

        /**
         * @var TMSService $tmxHandler
         */
        $tmxHandler = $request['tmxHandler'];

        $res = $tmxHandler->requestTMXEmailDownload(
            $this->user->email ?? '',
            $this->user->first_name ?? '',
            $this->user->last_name ?? '',
            $request['tm_key'],
            $request['strip_tags']
        );

        $this->response->json([
            'errors' => [],
            'data' => $res,
        ]);
    }

    /**
     * Testability seam: overridden in tests to return a stub, avoiding the
     * real MyMemory engine construction and its outbound HTTP calls.
     *
     * @throws Exception
     */
    protected function createTMSService(): TMSService
    {
        return new TMSService($this->getDatabase());
    }

    /**
     * @return array<string, mixed>
     * @throws Exception
     */
    private function validateTheRequest(): array
    {
        $id_job = filter_var($this->request->param('id_job'), FILTER_SANITIZE_NUMBER_INT);
        $password = filter_var($this->request->param('password'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH]);
        $tm_key = filter_var($this->request->param('tm_key'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH]);
        // FILTER_FLAG_STRIP_HIGH deleted every byte above 0x7F, so a TM named in any non-Latin
        // script arrived at MyMemory empty and the exported file was named after nothing. The name
        // is normalised instead, and the zip filename it becomes is bounded by the same cap as
        // every other resource name.
        $tm_name = filter_var($this->request->param('tm_name'), FILTER_UNSAFE_RAW);
        $downloadToken = filter_var($this->request->param('downloadToken'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH]);
        $download_to_email = filter_var($this->request->param('email'), FILTER_SANITIZE_EMAIL);
        $strip_tags = filter_var($this->request->param('strip_tags'), FILTER_VALIDATE_BOOLEAN);
        $source = filter_var($this->request->param('source'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH]);
        $target = filter_var($this->request->param('target'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH]);

        if ($download_to_email === false) {
            throw new InvalidArgumentException("Invalid email provided for download.", -1);
        }

        if ($tm_name === false) {
            throw new InvalidArgumentException("Invalid TM name provided.", -2);
        }

        $tm_name = UserSuppliedName::normalizeAndTruncate($tm_name, TmKeyManager::RESOURCE_NAME_MAX_LENGTH);

        $tmxHandler = $this->createTMSService();
        $tmxHandler->setName($tm_name);

        return [
            'id_job' => $id_job,
            'password' => $password,
            'tm_key' => $tm_key,
            'tm_name' => $tm_name,
            'downloadToken' => $downloadToken,
            'download_to_email' => $download_to_email,
            'strip_tags' => $strip_tags,
            'source' => $source,
            'target' => $target,
            'tmxHandler' => $tmxHandler,
        ];
    }
}