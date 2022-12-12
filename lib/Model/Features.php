<?php

use Features\BaseFeature;
use Features\Dqf;
use Features\Mmt;
use Features\ProjectCompletion;
use Features\QaCheckBlacklist;
use Features\QaCheckGlossary;
use Features\ReviewExtended;
use Features\SecondPassReview;
use Features\TranslationVersions;
use Klein\Klein;
use Klein\Request;


/**
 * Class Features
 *
 * This class is an autoloader for MateCat Features, load external plugins classes
 * defined in the
 *      <matecat_root>/plugins/<plugin>/manifest.php
 *
 * and the internal ones
 * by making a fallback on the default internal namespace for \Features
 *
 *
 */
class Features {

    /**
     * @var self
     */
    protected static $_INSTANCE;

    const PROJECT_COMPLETION   = ProjectCompletion::FEATURE_CODE;
    const TRANSLATION_VERSIONS = TranslationVersions::FEATURE_CODE;
    const QACHECK_GLOSSARY     = QaCheckGlossary::FEATURE_CODE;
    const QACHECK_BLACKLIST    = QaCheckBlacklist::FEATURE_CODE;
    const DQF                  = Dqf::FEATURE_CODE;
    const REVIEW_EXTENDED      = ReviewExtended::FEATURE_CODE;
    const MMT                  = Mmt::FEATURE_CODE;
    const SECOND_PASS_REVIEW   = SecondPassReview::FEATURE_CODE ;

    protected $VALID_CODES = [
            Features::PROJECT_COMPLETION,
            Features::TRANSLATION_VERSIONS,
            Features::QACHECK_GLOSSARY,
            Features::QACHECK_BLACKLIST,
            Features::DQF,
            Features::REVIEW_EXTENDED,
            Features::MMT,
            Features::SECOND_PASS_REVIEW
    ];

    protected $PLUGIN_CLASSES = [];

    protected $PLUGIN_PATHS = [];
    protected $DECORATOR_CLASSES = [];

    public static function getValidCodes() {
        return static::getInstance()->VALID_CODES;
    }

    protected static function getInstance() {

        if ( empty( self::$_INSTANCE ) ) {

            //singleton
            static::$_INSTANCE = new static();

            //autoload feature codes
            $iterator = new DirectoryIterator( INIT::$ROOT . DIRECTORY_SEPARATOR . 'plugins' );

            foreach ( $iterator as $fileInfo ) {

                if ( $fileInfo->isDir() && $fileInfo->getBasename()[ 0 ] != '.' ) {

                    $manifest = @include_once( $fileInfo->getPathname() . DIRECTORY_SEPARATOR . 'manifest.php' );
                    if ( !empty( $manifest ) ) { //Autoload external plugins
                        if ( array_key_exists( 'FEATURE_CODE', $manifest )) {
                            static::populateVars( $manifest, $fileInfo->getPathname() ) ;
                        } else {
                            foreach( $manifest as $key => $manifest ) {
                                static::populateVars( $manifest, $fileInfo->getPathname() ) ;
                            }
                        }
                    }
                }

            }

        }

        return static::$_INSTANCE;
    }

    public static function populateVars( $manifest, $pathName ) {
        static::$_INSTANCE->PLUGIN_PATHS[ $manifest[ 'FEATURE_CODE' ] ] =  $pathName . DIRECTORY_SEPARATOR . "lib";
        static::$_INSTANCE->VALID_CODES[] = $manifest[ 'FEATURE_CODE' ];
        //load class for autoloading
        static::$_INSTANCE->PLUGIN_CLASSES[ $manifest[ 'FEATURE_CODE' ] ]    = $manifest[ 'PLUGIN_CLASS' ];
    }

    /**
     * @param $code string
     *
     * @return mixed
     */
    public static function getPluginDirectoryName( $code ) {
        $instance = static::getInstance();
        $path = $instance->PLUGIN_PATHS[ $code ];
        $pathExploded = explode(DIRECTORY_SEPARATOR, $path);
        return $pathExploded[ count( $pathExploded ) - 2 ];
    }


    /**
     * @param $code string
     *
     * @return string
     */
    public static function getPluginClass( $code ) {
        $instance = static::getInstance();
        if( !isset( $instance->PLUGIN_CLASSES[ $code ] ) ){
            //try default auto loading for internal plugins
            return '\\Features\\' . Utils::underscoreToCamelCase( $code );
        }
        return $instance->PLUGIN_CLASSES[ $code ];
    }

    /**
     * @param BasicFeatureStruct $feature
     * @param                    $decoratorName
     *
     * @return bool|string
     */
    public static function getFeatureClassDecorator( BasicFeatureStruct $feature, $decoratorName ){

        $instance = static::getInstance();

        if( !isset( $instance->PLUGIN_CLASSES[ $feature->feature_code ] ) ){
            //try default auto loading for internal plugins
            $baseClass = '\\Features\\' . Utils::underscoreToCamelCase( $feature->feature_code );
        } else {
            $baseClass = $instance->PLUGIN_CLASSES[ $feature->feature_code ];
        }

        //convention for decorators
        $cls =  "$baseClass\\Decorator\\$decoratorName" ;

        // if this line is missing it won't log load errors.
        Log::doJsonLog('loading Decorator ' . $cls );

        if ( class_exists( $cls ) ) {
            return $cls;
        }

        return false;

    }

    /**
     *
     */
    public static function setIncludePath() {
        $instance = static::getInstance();
        set_include_path( get_include_path() . PATH_SEPARATOR . implode( PATH_SEPARATOR, $instance->PLUGIN_PATHS ) );
    }

    /**
     * Give your plugins the possibility to install routes
     *
     * @param Klein $klein
     */
    public static function loadRoutes( Klein $klein ) {

        list( , , $plugin_code ) = explode( '/', Request::createFromGlobals()->uri() );

        $instance = static::getInstance();

        if ( array_search( $plugin_code, $instance->VALID_CODES ) !== false ) {

            /**
             * Try to load external plugins classes and fallback to internal plugin code in case of failure
             *
             * If external plugin class is not defined ( no manifest or no plugin installed )
             * Try to load MateCat core plugins, so they can install it's own routes
             *
             * @deprecated because all MateCat internal route should not have a "plugins" namespace in the route, but they should have it's own controllers defined
             *             Ex: http://xxxx/plugins/review_extended/quality_report/xxx/xxxxxxx
             *             should be something like
             *             http://xxxx/review_extended/quality_report/xxx/xxxxxxx
             */
            $cls = static::getPluginClass( $plugin_code );

            $klein->with( "/plugins/$plugin_code", function () use ( $cls, $klein ) {
                /**
                 * @var $cls BaseFeature
                 */
                $cls::loadRoutes( $klein );
            } );

        }

    }

}
