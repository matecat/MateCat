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
     * Total number of drifted rows, which may be more than count($mismatches) when the caller passed
     * only a page of them.
     */
    private int $totalMismatches;

    /**
     * @param array<int, array{id:int,id_job:int,password:string,source_page:int,recorded_penalty_points:float,actual_penalty_points:float}> $mismatches
     * @param int|null $totalMismatches How many rows have drifted in total, when $mismatches is only
     *                                  a page of them. Defaults to the page size, i.e. "this is all
     *                                  of it".
     */
    public function __construct(array $mismatches, ?int $totalMismatches = null)
    {
        $this->mismatches = $mismatches;
        $this->totalMismatches = $totalMismatches ?? count($mismatches);
        $this->_setlayout('empty_skeleton.html');
        $this->_settemplate('ReviewExtended/penalty_points_drift_alert.html');
    }

    /**
     * shown/total are computed here rather than in the template so both numbers are assertable and
     * the template stays free of logic beyond the truncation notice.
     *
     * @return array<string, mixed>
     */
    protected function _getTemplateVariables(): array
    {
        return [
            'mismatches' => $this->mismatches,
            'shown' => count($this->mismatches),
            'total' => $this->totalMismatches,
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
