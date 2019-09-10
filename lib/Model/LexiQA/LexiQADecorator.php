<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 24/11/16
 * Time: 12.56
 *
 */

namespace LexiQA;


use INIT;
use PHPTAL;
use ProjectOptionsSanitizer;

class LexiQADecorator {

    protected $template;

    protected $model;

    /**
     * This var means that the job has this functionality enabled
     *
     * @var bool
     */
    protected $lexiqa_enabled = true;

    /**
     * Url of lexiQa
     *
     * @var null
     */
    protected $lexiqa_server;

    protected function __construct( PHPTAL $template ) {
        $this->template      = $template;
        $this->lexiqa_server = INIT::$LXQ_SERVER;
    }

    public static function getInstance( PHPTAL $template ){
        return new static( $template );
    }

    /**
     * Decorate the controllers with the lexiqa template vars
     *
     */
    public function decorateViewLexiQA(){

        if( INIT::$LXQ_LICENSE ){
            //LEXIQA license key
            $this->template->lxq_license = INIT::$LXQ_LICENSE;
            $this->template->lxq_partnerid = INIT::$LXQ_PARTNERID;
            $this->template->lexiqa_languages = json_encode( ProjectOptionsSanitizer::$lexiQA_allowed_languages );
        }

        $this->template->lxq_enabled  = $this->lexiqa_enabled;
        $this->template->lexiqaServer = $this->lexiqa_server;

    }

    /**
     * Called to check if the JOb has this feature enabled from creation project
     * @param \ChunkOptionsModel $model
     *
     * @return $this
     */
    public function checkJobHasLexiQAEnabled( \ChunkOptionsModel $model ){
        $this->lexiqa_enabled = $model->isEnabled( 'lexiqa' );
        return $this;
    }

    /**
     * Check if the feature is enabled in the matecat installation according to the
     * given preloaded featureSet. In fact, some Features exclude LexiQA.
     *
     * @param \Users_UserStruct $userStruct
     *
     * @return $this
     */
    public function featureEnabled( \FeatureSet $featureSet ) {

        if ( !INIT::$LXQ_LICENSE ){
            $this->lexiqa_enabled = false;
            return $this;
        }
        $this->lexiqa_enabled = true;

        return $this;

    }

}
