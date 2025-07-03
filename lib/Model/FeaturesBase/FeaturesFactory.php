<?php

namespace Model\FeaturesBase;

use DirectoryIterator;
use Features\BaseFeature;
use INIT;
use Klein\Klein;
use Klein\Request;
use Log;
use Utils;


/**
 * Class FeaturesFactory
 *
 * This class is an autoloader for Matecat FeaturesFactory, load external plugins classes
 * defined in the
 *      <matecat_root>/plugins/<plugin>/manifest.php
 *
 * and the internal ones
 * by making a fallback on the default internal namespace for \FeaturesFactory
 *
 *
 */
class FeaturesFactory {

    /**
     * @var self
     */
    protected static FeaturesFactory $_INSTANCE;

    protected array $VALID_CODES = [
            FeatureCodes::PROJECT_COMPLETION,
            FeatureCodes::TRANSLATION_VERSIONS,
            FeatureCodes::REVIEW_EXTENDED,
            FeatureCodes::MMT,
            FeatureCodes::SECOND_PASS_REVIEW
    ];

    protected array $PLUGIN_CLASSES = [];

    protected array $PLUGIN_PATHS      = [];
    protected array $DECORATOR_CLASSES = [];

    public static function getValidCodes(): array {
        return static::getInstance()->VALID_CODES;
    }

    protected static function getInstance(): FeaturesFactory {

        if ( empty( self::$_INSTANCE ) ) {

            //singleton
            static::$_INSTANCE = new static();

            //autoload feature codes
            $iterator = new DirectoryIterator( INIT::$ROOT . DIRECTORY_SEPARATOR . 'plugins' );

            foreach ( $iterator as $fileInfo ) {

                if ( $fileInfo->isDir() && $fileInfo->getBasename()[ 0 ] != '.' ) {

                    $manifest = @include_once( $fileInfo->getPathname() . DIRECTORY_SEPARATOR . 'manifest.php' );
                    if ( !empty( $manifest ) ) { //Autoload external plugins
                        if ( array_key_exists( 'FEATURE_CODE', $manifest ) ) {
                            static::populateVars( $manifest, $fileInfo->getPathname() );
                        } else {
                            foreach ( $manifest as $key => $_manifest ) {
                                static::populateVars( $_manifest, $fileInfo->getPathname() );
                            }
                        }
                    }
                }

            }

        }

        return static::$_INSTANCE;
    }

    public static function populateVars( $manifest, $pathName ) {
        static::$_INSTANCE->PLUGIN_PATHS[ $manifest[ 'FEATURE_CODE' ] ] = $pathName . DIRECTORY_SEPARATOR . "lib";
        static::$_INSTANCE->VALID_CODES[]                               = $manifest[ 'FEATURE_CODE' ];
        //load class for autoloading
        static::$_INSTANCE->PLUGIN_CLASSES[ $manifest[ 'FEATURE_CODE' ] ] = $manifest[ 'PLUGIN_CLASS' ];
    }

    /**
     * @param $code string
     *
     * @return mixed
     */
    public static function getPluginDirectoryName( string $code ) {
        $instance     = static::getInstance();
        $path         = $instance->PLUGIN_PATHS[ $code ];
        $pathExploded = explode( DIRECTORY_SEPARATOR, $path );

        return $pathExploded[ count( $pathExploded ) - 2 ];
    }


    /**
     * @param $code string
     *
     * @return string
     */
    public static function getPluginClass( string $code ): string {
        $instance = static::getInstance();
        if ( !isset( $instance->PLUGIN_CLASSES[ $code ] ) ) {
            //try default autoloading for internal plugins
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
    public static function getFeatureClassDecorator( BasicFeatureStruct $feature, $decoratorName ) {

        $instance = static::getInstance();

        if ( !isset( $instance->PLUGIN_CLASSES[ $feature->feature_code ] ) ) {
            //try default auto loading for internal plugins
            $baseClass = '\\Features\\' . Utils::underscoreToCamelCase( $feature->feature_code );
        } else {
            $baseClass = $instance->PLUGIN_CLASSES[ $feature->feature_code ];
        }

        //convention for decorators
        $cls = "$baseClass\\Decorator\\$decoratorName";

        // if this line is missing it won't log load errors.
        Log::doJsonLog( 'loading Decorator ' . $cls );

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

        $path = explode( '/', Request::createFromGlobals()->uri() );

        $instance = static::getInstance();

        if ( in_array( $path[ 2 ] ?? null, $instance->VALID_CODES ) ) {

            /**
             * Try to load external plugins classes and fallback to internal plugin code in case of failure
             *
             * If external plugin class is not defined ( no manifest or no plugin installed )
             * Try to load Matecat core plugins, so they can install it's own routes
             *
             * @deprecated because all Matecat internal route should not have a "plugins" namespace in the route, but they should have it's own controllers defined
             *             Ex: http://xxxx/plugins/review_extended/quality_report/xxx/xxxxxxx
             *             should be something like
             *             http://xxxx/review_extended/quality_report/xxx/xxxxxxx
             */
            $cls = static::getPluginClass( $path[ 2 ] );

            $klein->with( "/plugins/" . $path[ 2 ], function () use ( $cls, $klein ) {
                /**
                 * @var $cls BaseFeature
                 */
                $cls::loadRoutes( $klein );
            } );

        }

    }

}
