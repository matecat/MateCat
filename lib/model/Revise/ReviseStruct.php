<?php
/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 19/01/15
 * Time: 18.14
 */

class Revise_ReviseStruct extends DataAccess_AbstractDaoObjectStruct implements DataAccess_IDaoStruct{

    /**
     * @var int
     */
    public $id_job;

    /**
     * @var int
     */
    public $id_segment;

    /**
     * @var string A string from the ones in Constants_Revise
     * @see Constants_Revise
     */
    public $err_typing;

    /**
     * @var string A string from the ones in Constants_Revise
     * @see Constants_Revise
     */
    public $err_translation;

    /**
     * @var string A string from the ones in Constants_Revise
     * @see Constants_Revise
     */
    public $err_terminology;

    /**
     * @var string A string from the ones in Constants_Revise
     * @see Constants_Revise
     */
    public $err_quality;

    /**
     * @var string A string from the ones in Constants_Revise
     * @see Constants_Revise
     */
    public $err_style;


} 