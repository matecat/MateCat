<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 06/10/14
 * Time: 15.45
 * 
 */

/**
 * Class MemoryKeyStruct<br>
 * This class represents a row in the table user_groups.
 */
class TmKeyManagement_MemoryGroupStruct extends DataAccess_AbstractDaoObjectStruct implements DataAccess_IDaoStruct {

    /**
     * @var integer The group's ID
     */
    public $gid;

    /**
     * @var integer The user's ID
     */
    public $uid;

    /**
     * @var string The group Name
     */
    public $group_name;


} 