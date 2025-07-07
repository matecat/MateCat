<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 16/02/2017
 * Time: 16:13
 */

namespace Utils\Email;


use CatUtils;
use Exception;
use Model\Jobs\JobStruct;
use Model\Projects\MetadataDao;
use Model\Projects\ProjectStruct;
use Model\Users\UserStruct;
use Model\WordCount\WordCountStruct;
use Routes;

class ProjectAssignedEmail extends AbstractEmail {

    protected UserStruct    $user;
    protected ProjectStruct $project;
    protected UserStruct    $assignee;
    protected ?string       $title;
    /**
     * @var JobStruct[]
     */
    private array $jobs;

    public function __construct( UserStruct $user, ProjectStruct $project, UserStruct $assignee ) {
        $this->user     = $user;
        $this->project  = $project;
        $this->assignee = $assignee;

        $this->jobs = $project->getJobs();

        $this->title = "You've been assigned a project";

        $this->_setLayout( 'skeleton.html' );
        $this->_setTemplate( 'Project/project_assigned_content.html' );
    }

    protected function _getTemplateVariables(): array {
        $words_count = [];
        foreach ( $this->jobs as $job ) {
            $jStruct  = new JobStruct( $job->getArrayCopy() );
            $jobStats = new WordCountStruct();
            $jobStats->setIdJob( $jStruct->id );
            $jobStats->setJobPassword( $jStruct->password );
            $jobStats->setDraftWords( $jStruct->draft_words + $jStruct->new_words ); // (draft_words + new_words) AS DRAFT
            $jobStats->setRejectedWords( $jStruct->rejected_words );
            $jobStats->setTranslatedWords( $jStruct->translated_words );
            $jobStats->setApprovedWords( $jStruct->approved_words );
            $stats         = CatUtils::getFastStatsForJob( $jobStats, false );
            $words_count[] = $stats[ MetadataDao::WORD_COUNT_RAW ][ 'total' ];
        }

        return [
                'user'        => $this->assignee->toArray(),
                'sender'      => $this->user->toArray(),
                'project'     => $this->project->toArray(),
                'words_count' => number_format( array_sum( $words_count ) ),
                'project_url' => Routes::analyze( [
                        'project_name' => $this->project->name,
                        'id_project'   => $this->project->id,
                        'password'     => $this->project->password
                ] )
        ];
    }

    protected function _getLayoutVariables( $messageBody = null ): array {
        $vars            = parent::_getLayoutVariables();
        $vars[ 'title' ] = $this->title;

        return $vars;
    }

    /**
     * @throws Exception
     */
    public function send() {
        $recipient = [ $this->assignee->email, $this->assignee->fullName() ];

        $this->doSend( $recipient, $this->title,
                $this->_buildHTMLMessage(),
                $this->_buildTxtMessage( $this->_buildMessageContent() )
        );
    }

}