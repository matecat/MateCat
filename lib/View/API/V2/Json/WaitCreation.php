<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 03/03/17
 * Time: 20.06
 *
 */

namespace API\V2\Json;


class WaitCreation {

    public function render() {

        return [
                'status'       => 202,
                'message'      => 'Project in queue. Wait.',
        ];

    }

}
