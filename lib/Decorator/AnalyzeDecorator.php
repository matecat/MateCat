<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 04/04/2018
 * Time: 12:39
 */

use Analysis\Status;

class AnalyzeDecorator {

    private $controller;

    /**
     * @var PHPTALWithAppend
     */
    private $template;

    public function __construct( analyzeController $controller, PHPTAL $template ) {

        $this->controller = $controller;
        $this->template   = $template;

    }


    public function decorate() {

        $this->template->jobs                       = $this->controller->model->jobs;
        $this->template->json_jobs                  = json_encode($this->controller->model->jobs);
        $this->template->fast_analysis_wc           = $this->controller->model->fast_analysis_wc;
        $this->template->fast_analysis_wc_print     = $this->controller->model->fast_analysis_wc_print;
        $this->template->tm_analysis_wc             = $this->controller->model->tm_analysis_wc;
        $this->template->tm_analysis_wc_print       = $this->controller->model->tm_analysis_wc_print;
        $this->template->standard_analysis_wc       = $this->controller->model->standard_analysis_wc;
        $this->template->standard_analysis_wc_print = $this->controller->model->standard_analysis_wc_print;
        $this->template->total_raw_word_count       = $this->controller->model->total_raw_word_count;
        $this->template->total_raw_word_count_print = $this->controller->model->total_raw_word_count_print;
        $this->template->pname                      = $this->controller->model->pname;
        $this->template->pid                        = $this->controller->model->pid;


        $this->template->tm_wc_time                 = $this->controller->model->tm_wc_time;
        $this->template->fast_wc_time               = $this->controller->model->fast_wc_time;
        $this->template->tm_wc_unit                 = $this->controller->model->tm_wc_unit;
        $this->template->fast_wc_unit               = $this->controller->model->fast_wc_unit;
        $this->template->standard_wc_unit           = $this->controller->model->standard_wc_unit;
        $this->template->raw_wc_time                = $this->controller->model->raw_wc_time;
        $this->template->standard_wc_time           = $this->controller->model->standard_wc_time;
        $this->template->raw_wc_unit                = $this->controller->model->raw_wc_unit;
        $this->template->project_status             = $this->controller->model->project_status;
        $this->template->num_segments               = $this->controller->model->num_segments;
        $this->template->num_segments_analyzed      = $this->controller->model->num_segments_analyzed;

        $this->template->logged_user                = ($this->controller->isLoggedIn() !== false ) ? $this->controller->getUser()->shortName() : "";
        $this->template->extended_user              = ($this->controller->isLoggedIn() !== false ) ? trim( $this->controller->getUser()->fullName() ) : "";

        $this->template->subject                    = $this->controller->model->subject;

        //first two letter of code lang
        $project_data = $this->controller->model->getProjectData()[ 0 ];

        $this->template->isCJK = false;

        if ( array_key_exists( explode( "-" , $project_data[ 'source' ] )[0], CatUtils::$cjk ) ) {
            $this->template->isCJK = true;
        }

        $this->template->isLoggedIn = $this->controller->isLoggedIn();

        //perform check on running daemons and send a mail randomly
        $misconfiguration = Status::thereIsAMisconfiguration();
        if ( $misconfiguration && mt_rand( 1, 3 ) == 1 ) {
            $msg = "<strong>The analysis daemons seem not to be running despite server configuration.</strong><br />Change the application configuration or start analysis daemons.";
            Utils::sendErrMailReport( $msg, "Matecat Misconfiguration" );
        }
        $this->template->daemon_misconfiguration = var_export( $misconfiguration, true );
        
        $this->template->build_number = INIT::$BUILD_NUMBER;
        $this->template->support_mail = INIT::$SUPPORT_MAIL;

        $this->template->split_enabled    = true;
        $this->template->enable_outsource = INIT::$ENABLE_OUTSOURCE;

        if ( array_key_exists( explode( '-', $project_data->target )[0], CatUtils::$cjk ) ) {
            $this->template->targetIsCJK = var_export( true, true ); //config.js -> editArea is a CJK lang?
        } else {
            $this->template->targetIsCJK = var_export( false, true );
        }
    }

}
