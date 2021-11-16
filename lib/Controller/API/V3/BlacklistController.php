<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 08/02/2019
 * Time: 13:03
 */

namespace API\V3;

use API\V2\Exceptions\ValidationError;
use API\V2\KleinController;
use CatUtils;
use Langs_Languages;


class BlacklistController extends KleinController {

    /**
     * @var int
     */
    private $idJob;

    /**
     * @var string
     */
    private $password;

    /**
     * @var mixed
     */
    private $file;

    public function upload() {

        $this->idJob = $this->request->param( 'jid' );
        $this->password = $this->request->param( 'password' );

        // validate job

        // parse and validate file

        
    }
}