<?php

namespace Plugins\Features\ReviewExtended\Email;

use Exception;
use Model\Jobs\JobStruct;
use Model\LQA\ChunkReviewStruct;
use Utils\Email\AbstractEmail;
use Utils\Registry\AppConfig;

class BatchReviewProcessorAlertEmail extends AbstractEmail
{

    /**
     * @var JobStruct
     */
    private JobStruct $chunk;

    /**
     * @var ChunkReviewStruct
     */
    private ChunkReviewStruct $chunkReview;

    /**
     * @var string|null
     */
    protected ?string $title = 'Alert from batch review processor';

    /**
     * BatchEventCreatorAlertEmail constructor.
     *
     * @param JobStruct         $chunk
     * @param ChunkReviewStruct $chunkReview
     */
    public function __construct(JobStruct $chunk, ChunkReviewStruct $chunkReview)
    {
        $this->chunk       = $chunk;
        $this->chunkReview = $chunkReview;
        $this->_setlayout('empty_skeleton.html');
        $this->_settemplate('ReviewExtended/batch_review_processor_alert.html');
    }

    /**
     * @return array
     */
    protected function _getTemplateVariables(): array
    {
        return [
                'chunkId'       => $this->chunk->id,
                'chunkReviewId' => $this->chunkReview->id,
        ];
    }

    /**
     * @return void
     * @throws Exception
     */
    public function send(): void
    {
        $mailConf = @parse_ini_file(AppConfig::$ROOT . '/inc/Error_Mail_List.ini', true);

        if (!empty($mailConf[ 'email_list' ])) {
            foreach ($mailConf[ 'email_list' ] as $email => $uName) {
                $this->sendTo($email, $uName);
            }
        }
    }
}