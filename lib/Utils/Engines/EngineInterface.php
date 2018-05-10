<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 27/02/15
 * Time: 11.55
 * 
 */

interface Engines_EngineInterface {

    /**
     * @param $_config
     *
     * @return Engines_Results_AbstractResponse
     */
    public function get( $_config );

    /**
     * @param $_config
     *
     * @return bool
     */
    public function set( $_config );

    /**
     * @param $_config
     *
     * @return bool
     */
    public function update( $_config );

    /**
     * @param $_config
     *
     * @return bool
     */
    public function delete( $_config );

    /**
     * @return mixed
     */
    public function getConfigStruct();

    /**
     * @return Engines_EngineInterface
     */
    public function setAnalysis();

    /**
     * @return EnginesModel_EngineStruct
     */
    public function getEngineRow();

}