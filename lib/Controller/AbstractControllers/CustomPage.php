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

    /**
     * @throws ReflectionException
     * @noinspection PhpMissingParentConstructorInspection
     */
    public function __construct() {
        //SESSION ENABLED
        $this->identifyUser();
        $this->featureSet = new FeatureSet();
    }

    /**
     * @param string $skeletonFile
     */
    public function setTemplate( string $skeletonFile ) {
        $this->makeTemplate( $skeletonFile );
    }

    /**
     * @param $httpCode integer
     */
    public function setCode( $httpCode ) {
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

        foreach ( $customVars as $varName => $value ) {
            $this->template->{$varName} = $value;
        }

    }

}