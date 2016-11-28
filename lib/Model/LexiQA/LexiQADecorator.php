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
use OwnerFeatures_OwnerFeatureDao;
use PHPTAL;
use ProjectOptionsSanitizer;

class LexiQADecorator {

    protected $template;

    protected $model;

    /**
     * Switch On/Off the functionality for the MateCat installation
     *
     * @var bool
     */
    protected $deny_lexiqa = false;

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
            $this->template->lexiqa_languages = json_encode( ProjectOptionsSanitizer::$lexiQA_allowed_languages );
        }

        $this->template->lxq_enabled  = $this->lexiqa_enabled;
        $this->template->deny_lexiqa  = $this->deny_lexiqa;
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
     * Check if the feature is enabled in the matecat installation and for the specific user
     *
     * @param \Users_UserStruct $userStruct
     * @param \IDatabase        $database
     *
     * @return $this
     */
    public function featureEnabled(
            \Users_UserStruct $userStruct,
            \IDatabase $database
    ) {

        if( !INIT::$LXQ_LICENSE ){
            $this->lexiqa_enabled = false;
            return $this;
        }

        if( $userStruct != null ) {

            $ownerFeatureDao = new OwnerFeatures_OwnerFeatureDao( $database );

            $isQaGlossaryEnabled = $ownerFeatureDao->isFeatureEnabled(
                    \Features::QACHECK_GLOSSARY, $userStruct->email
            );

            $isQaGBlacklistEnabled = $ownerFeatureDao->isFeatureEnabled(
                    \Features::QACHECK_BLACKLIST, $userStruct->email
            );

            if( $isQaGlossaryEnabled === true || $isQaGBlacklistEnabled === true ) {
                $this->deny_lexiqa = true;
                $this->lexiqa_enabled = false;
            }
        }

        return $this;

    }

}