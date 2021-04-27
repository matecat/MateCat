<?php

namespace Features\ReviewExtended\Email;

use Email\AbstractEmail;
use LQA\ChunkReviewStruct;

class BatchReviewProcessorAlertEmail extends AbstractEmail {

    /**
     * @var \Chunks_ChunkStruct
     */
    private $chunk;

    /**
     * @var ChunkReviewStruct
     */
    private $chunkReview;

    /**
     * @var string
     */
    protected $title = 'Alert from batch review processor';

    /**
     * BatchEventCreatorAlertEmail constructor.
     *
     * @param \Chunks_ChunkStruct $chunk
     * @param ChunkReviewStruct   $chunkReview
     */
    public function __construct( \Chunks_ChunkStruct $chunk, ChunkReviewStruct $chunkReview ) {
        $this->chunk       = $chunk;
        $this->chunkReview = $chunkReview;
        $this->_setlayout( 'empty_skeleton.html' );
        $this->_settemplate( 'ReviewExtended/batch_review_processor_alert.html' );
    }

    /**
     * @return array
     */
    protected function _getTemplateVariables() {
        return [
                'chunkId'       => $this->chunk->id,
                'chunkReviewId' => $this->chunkReview->id,
        ];
    }

    /**
     * @return void
     */
    public function send() {
        $mailConf = @parse_ini_file( \INIT::$ROOT . '/inc/Error_Mail_List.ini', true );

        if ( !empty( $mailConf[ 'email_list' ] ) ) {
            foreach ( $mailConf[ 'email_list' ] as $email => $uName ) {
                $this->sendTo( $email, $uName );
            }
        }
    }
}