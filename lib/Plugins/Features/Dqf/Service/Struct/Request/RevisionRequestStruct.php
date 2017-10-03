<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 15/09/2017
 * Time: 10:47
 */

namespace Features\Dqf\Service\Struct\Request;

use Features\Dqf\Model\CachedAttributes\Severity;
use Features\Dqf\Service\Struct\BaseRequestStruct;

class RevisionRequestStruct extends BaseRequestStruct {

    public $projectId ;
    public $fileId ;
    public $targetLangCode ;
    public $sessionId ;
    public $apiKey ;
    public $projectKey ;
    public $translationId ;

    public $batchId ;

    protected $errors = [] ;

    protected $qualityReportIssues = [] ;

    /**
     * @param $qualityReportIssueStruct array Would be nice to have a struct for this one.
     */
    public function addError( $qualityReportIssueStruct ) {
        array_push( $this->qualityReportIssues, $qualityReportIssueStruct ) ;

        array_push( $this->errors, [
                'errorCategoryId' => json_decode( $qualityReportIssueStruct['category_options'], true )['dqf_id'],
                'severityId'      => Severity::obtain()->demapName( $qualityReportIssueStruct['severity'] ),
                'charPosStart'    => $qualityReportIssueStruct['start_offset'],
                'charPosEnd'      => $this->matecatToDqfCharPosEnd( $qualityReportIssueStruct['end_offset'] )
        ] ) ;
    }

    public function getHeaders() {
        return $this->toArray(['sessionId', 'apiKey', 'projectKey']);
    }

    public function getPathParams() {
        return [
                'projectId'      => $this->projectId,
                'fileId'         => $this->fileId,
                'targetLangCode' => $this->targetLangCode,
                'translationId'  => $this->translationId
        ];
    }

    public function getBody() {
        $output = [
                'overwrite' => true,
                'batchId'   => $this->batchId,
                'revisions' => [
                        [
                            'clientId' => $this->getClientId() ,
                            'comment'  => $this->getComment(),
                            'errors'   => $this->errors
                        ]
                ]
        ] ;

        return $output ;
    }

    /**
     * DQF uses char position 0 based, not offset like the one we save in database.
     *
     * @param $pos
     *
     * @return int
     */
    protected function matecatToDqfCharPosEnd( $pos ) {
        return $pos - 1;
    }

    /**
     * To be sure to send non duplicated cliendId, use issue ids.
     * @return string
     */
    protected function getClientId() {
        return md5( implode(',', array_map( function( $item ) {
                    return $item['id'] ;
                }, $this->qualityReportIssues )
        ) ) ;
    }

    /**
     * This sets the same comment for each issue. This is not correct in practice.
     * The correct form would be to have issues grouped by comments.
     *
     * @return mixed
     */
    protected function getComment() {
        return $this->errors[ 0 ]['comment'] ;
    }


}