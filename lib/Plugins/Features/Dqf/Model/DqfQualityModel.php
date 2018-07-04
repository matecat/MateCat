<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 06/06/2017
 * Time: 11:28
 */

namespace Features\Dqf\Model;

use DomainException;
use Exception;
use Features\Dqf\Model\CachedAttributes\Severity;
use Features\Dqf\Service\Struct\Request\ReviewSettingsRequestStruct;
use LQA\CategoryStruct;
use LQA\ModelStruct;
use Projects_ProjectStruct;

class DqfQualityModel {

    /**
     * @var ModelStruct
     */
    protected $qaModelStruct ;

    /**
     * @var CategoryStruct[]
     */
    protected $matecat_categories ;

    /**
     * @var array
     */
    protected $severities ;

    public function __construct( Projects_ProjectStruct $project ) {

        $this->qaModelStruct = $project->getLqaModel() ;
        $this->matecat_categories = $this->qaModelStruct->getCategories();
    }

    public function getReviewSettings() {

        $struct = new ReviewSettingsRequestStruct();
        $struct->reviewType        = 'error_typology';
        $struct->severityWeights   = $this->getSeverities() ;
        $struct->sampling          = 100 ;
        $struct->passFailThreshold = $this->getPassFailThreshold();

        /**
         * Decide which categories to use. DQF expects four categories to be defined:
         * neutral,minor,major,critical.
         * Count of severities for the project must be 4 and all must have dqf_id set.
         */

        foreach( $this->matecat_categories as $category ) {
            $options = json_decode( $category->options, true );
            $struct->addErrorCategory( $options['dqf_id'] ) ;
        }

        return $struct ;
    }

    protected function getPassFailThreshold() {
        if ( $this->qaModelStruct->pass_type != 'points_per_thousand' ) {
            throw new Exception("type not supported " . $this->qaModelStruct->pass_type );
        }
        return $this->qaModelStruct->getLimit() ;
    }

    /**
     * In DQF all severities are the same for all categories, so we find the
     * severties defined in the first record and use those.
     *
     */
    protected function getSeverities() {
        $severities = [] ;

        foreach( $this->matecat_categories[ 0 ]->getJsonSeverities() as $severity )  {

            $ids[] = $severity['dqf_id'] ;

            $severities[] = [
                    'severityId' => $severity['dqf_id'],
                    'weight'     => $severity['penalty']
            ];
        }

        sort($ids, SORT_NUMERIC) ;

        $cachedSeverities = new Severity();

        if ( $cachedSeverities->getSortedDqfIds() != $ids ) {
            throw new DomainException('Your QA model is missing some DQF severities. All severities defined in DQF are expected.') ;
        }

        return json_encode( $severities );
    }

}
