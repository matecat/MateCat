<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 20/12/2016
 * Time: 10:14
 */

namespace CommandLineTasks\Outsource;

use API\V2\Json\ProjectUrls;
use Features\Microsoft;
use Features\Microsoft\Utils\Email\ConfirmedQuotationEmail;
use Features\Microsoft\Utils\Email\ErrorQuotationEmail;
use Plugins\Features\Outsource\Constants\ServiceTypes;


class MicrosoftOutsourceToHTS extends AbstractOutsource {

    protected function configure() {

        parent::configure();

        $this
                // the name of the command (the part after "bin/console")
                ->setName( 'outsource:microsoft' );

    }

    protected function _call( \Jobs_JobStruct $job, \Projects_ProjectStruct $project ){

        $projectData = ( new \Projects_ProjectDao() )->getProjectData( $project->id, $project->password );
        $formatted   = new ProjectUrls( $projectData );

        //Let the Feature Class decide about Urls
        $formatted = Microsoft::projectUrls( $formatted );

        $this->config = Microsoft::getConfig();

        $eq_word = \Jobs_JobDao::getTODOWords( $job );

        if( $eq_word != 0 ){
            $eq_word = max( number_format( $eq_word + 0.00000001, 0, "", "" ), 1 );
        }

        if( $this->input->getOption( 'test' ) ){
            $this->output->writeln( "  - Quote would have been sent, Job ID {$job->id} and password {$job->password}. Words: $eq_word" , true );
            return;
        }

        $this->output->writeln( "  - Sending Quote and Confirm, Job ID {$job->id} and password {$job->password}. Words: $eq_word" , true );

        $this->setSuccessMailSender( new ConfirmedQuotationEmail( Microsoft::getPluginBasePath() . '/Features/Microsoft/View/Emails/confirmed_quotation.html' ) );
        $this->setFailureMailSender( new ErrorQuotationEmail( Microsoft::getPluginBasePath() . '/Features/Microsoft/View/Emails/error_quotation.html' ) );

        $response = $this->requestJobQuote( $job, $eq_word, $project, $formatted, ServiceTypes::SERVICE_TYPE_PROFESSIONAL );

        if ( !empty( $response ) ) {
            $this->output->writeln( "  - Quote Success, HTS PID: " . $this->getExternalProjectId() . " - Words: $eq_word", true );
        } else {
            $this->output->writeln( "  - FAILED...." );
        }

    }


}