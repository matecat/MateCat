<?php

namespace Controller\Views;

use Controller\Abstracts\BaseKleinViewController;
use Controller\Abstracts\IController;
use Controller\API\Commons\ViewValidators\ViewLoginRedirectValidator;
use Exception;

class ContextReviewController extends BaseKleinViewController implements IController
{

    protected function registerValidators(): void
    {
        $this->appendValidator(new ViewLoginRedirectValidator($this));
    }

    /**
     * @throws Exception
     */
    public function renderView(): void
    {
        $request = $this->validateTheRequest();

        if (!is_array($request)) {
            $this->setView('project_not_found.html', [], 404);
            $this->render();
        }

        $this->setView('context_preview.html', [
            'id_project' => $request['id_project'],
            'password' => $request['password'],
        ]);
        $this->render();
    }

    /**
     * @return array<string, mixed>|false|null
     */
    protected function validateTheRequest(): false|array|null
    {
        $filterArgs = [
            'id_project' => ['filter' => FILTER_SANITIZE_NUMBER_INT],
            'password' => [
                'filter' => FILTER_SANITIZE_SPECIAL_CHARS,
                'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
            ]
        ];

        return filter_var_array($this->request->paramsNamed()->all(), $filterArgs);
    }

}

