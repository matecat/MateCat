<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 15/12/17
 * Time: 17.18
 *
 */

namespace Controller\API\Commons\Validators;

use Controller\API\Commons\Exceptions\NotFoundException;
use Segments_SegmentDao;

class SegmentValidator extends Base {

    /**
     * @return mixed|void
     * @throws NotFoundException
     */
    protected function _validate(): void {

        // JobPasswordValidator is actually useless
        // in this case since we need to check for the segment
        // scope inside the job.
        //
        // if ( !$this->validator->validate()  ) {
        //     return false;
        // }

        // Ensure chunk is in project
        $dao = new Segments_SegmentDao( \Database::obtain() );

        $segment = $dao->getByChunkIdAndSegmentId(
                $this->controller->getParams()[ 'id_job' ],
                $this->controller->getParams()[ 'password' ],
                $this->controller->getParams()[ 'id_segment' ]
        );

        if ( !$segment ) {
            throw new NotFoundException( "Segment Not Found.", 404 );
        }

    }


}