<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 08/11/2017
 * Time: 15:24
 */

namespace API\V2;


use API\V2\Json\SegmentVersion;
use API\V2\Validators\SegmentTranslation;
use Translations_TranslationVersionDao;

class ReviseTranslationIssuesController extends KleinController {

    public function afterConstruct() {
        $this->appendValidator(
                ( new SegmentTranslation( $this->request ) )->setPassword( $this->request->password )
        );
    }

    public function index() {
        $records = ( new Translations_TranslationVersionDao() )->setCacheTTL(0)
                ->getVersionsForRevision(
                        $this->request->id_job, $this->request->id_segment
                );

        $version_formatter = new SegmentVersion( $records, true )  ;
        $this->response->json( [ 'versions' => $version_formatter->render() ] ) ;
    }

}