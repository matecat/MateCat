<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 13/09/2018
 * Time: 13:03
 */

namespace API\App;

use API\App\Json\CompatibilityChunk;
use API\V3\ChunkController;

/**
 * ( 2023/11/06 )
 *
 * This class is meant to allow back compatibility with running projects
 * after the advancement word-count switch from weighted to raw
 *
 * YYY [Remove] backward compatibility for current projects
 * YYY Remove after a reasonable amount of time
 */
class CompatibilityChunkController extends ChunkController {

    /**
     * @throws \Exception
     * @throws \Exceptions\NotFoundException
     */
    public function show() {

        $format = new CompatibilityChunk();

        $format->setUser( $this->user );
        $format->setCalledFromApi( true );

        $this->return404IfTheJobWasDeleted();

        $this->response->json( $format->renderOne($this->chunk) );

    }

}