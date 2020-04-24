<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 15/11/17
 * Time: 12.08
 *
 */


class CustomPage extends viewController {

    /**
     * @var integer
     */
    protected $httpCode;

    public function __construct() {

        //SESSION ENABLED
        parent::sessionStart();
        $this->setBrowserSupport();
        $this->_setUserFromAuthCookie();
        $this->setUserCredentials();

        $this->featureSet = new FeatureSet();

    }

    /**
     * @param             $skeletonFile string
     */
    public function setTemplate( $skeletonFile ){
        $this->template = $skeletonFile;
    }

    /**
     * @param $httpCode integer
     */
    public function setCode( $httpCode ){
        $this->httpCode = $httpCode;
    }

    public function doAction() {
        $this->renderCustomHTTP( $this->template, $this->httpCode );
    }

    /**
     * @param array $customVars
     *
     * @return void
     */
    public function setTemplateVars( $customVars = [] ) {

        foreach( $customVars as $varName => $value ){
            $this->template->{$varName} = $value;
        }

    }

}