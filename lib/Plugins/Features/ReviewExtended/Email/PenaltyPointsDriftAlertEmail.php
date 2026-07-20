<?php

namespace Plugins\Features\ReviewExtended\Email;

use Exception;
use Utils\Email\AbstractEmail;
use Utils\Registry\AppConfig;

class PenaltyPointsDriftAlertEmail extends AbstractEmail
{
    /**
     * @var array<int, array{id:int,id_job:int,password:string,source_page:int,recorded_penalty_points:float,actual_penalty_points:float}>
     */
    private array $mismatches;

    /**
     * @var string|null
     */
    protected ?string $title = 'Alert: qa_chunk_reviews penalty_points drift detected';

    /**
     * @param array<int, array{id:int,id_job:int,password:string,source_page:int,recorded_penalty_points:float,actual_penalty_points:float}> $mismatches
     */
    public function __construct(array $mismatches)
    {
        $this->mismatches = $mismatches;
        $this->_setlayout('empty_skeleton.html');
        $this->_settemplate('ReviewExtended/penalty_points_drift_alert.html');
    }

    /**
     * @return array<string, mixed>
     */
    protected function _getTemplateVariables(): array
    {
        return [
            'mismatches' => $this->mismatches,
        ];
    }

    /**
     * @throws Exception
     */
    public function send(): void
    {
        $mailConf = @parse_ini_file(AppConfig::$ROOT . '/inc/Error_Mail_List.ini', true);

        if (!empty($mailConf['email_list'])) {
            foreach ($mailConf['email_list'] as $email => $uName) {
                $this->sendTo($email, $uName);
            }
        }
    }
}
