<?php

namespace Views;

use AbstractControllers\BaseKleinViewController;
use AbstractControllers\IController;
use ActivityLog\Activity;
use ActivityLog\ActivityLogStruct;
use API\Commons\ViewValidators\LoginRedirectValidator;
use CatUtils;
use Constants_TranslationStatus;
use Exception;
use Jobs_JobDao;
use Langs\Languages;
use Utils;

/**
 *
 * @see https://dev.matecat.com/revise-summary/9763519-772d1081eef6
 *
 * Quality Report Controller
 *
 */
class QualityReportController extends BaseKleinViewController implements IController {

    protected function afterConstruct() {
        $this->appendValidator( new LoginRedirectValidator( $this ) );
    }

    /**
     * @throws Exception
     */
    public function renderView() {

        $request = $this->validateTheRequest();

        $jobStruct = Jobs_JobDao::getByIdAndPassword( $request[ 'jid' ], $request[ 'password' ] );

        if ( empty( $jobStruct ) ) {
            $this->setView( "project_not_found.html", [], 404 );
            $this->render();
        }

        $this->setView( "revise_summary.html", [

                'jid'      => $jobStruct->id,
                'password' => $jobStruct->password,

                'brPlaceholdEnabled'   => true,
                'lfPlaceholder'        => CatUtils::lfPlaceholder,
                'crPlaceholder'        => CatUtils::crPlaceholder,
                'crlfPlaceholder'      => CatUtils::crlfPlaceholder,
                'lfPlaceholderClass'   => CatUtils::lfPlaceholderClass,
                'crPlaceholderClass'   => CatUtils::crPlaceholderClass,
                'crlfPlaceholderClass' => CatUtils::crlfPlaceholderClass,
                'lfPlaceholderRegex'   => CatUtils::lfPlaceholderRegex,
                'crPlaceholderRegex'   => CatUtils::crPlaceholderRegex,
                'crlfPlaceholderRegex' => CatUtils::crlfPlaceholderRegex,
                'tabPlaceholder'       => CatUtils::tabPlaceholder,
                'tabPlaceholderClass'  => CatUtils::tabPlaceholderClass,
                'tabPlaceholderRegex'  => CatUtils::tabPlaceholderRegex,
                'nbspPlaceholder'      => CatUtils::nbspPlaceholder,
                'nbspPlaceholderClass' => CatUtils::nbspPlaceholderClass,
                'nbspPlaceholderRegex' => CatUtils::nbspPlaceholderRegex,

                'source_code'         => $jobStruct[ 'source' ],
                'target_code'         => $jobStruct[ 'target' ],
                'source_rtl'          => Languages::getInstance()->isRTL( $jobStruct[ 'source' ] ),
                'target_rtl'          => Languages::getInstance()->isRTL( $jobStruct[ 'target' ] ),
                'searchable_statuses' => $this->searchableStatuses(),

        ] );

        if ( $jobStruct->isArchived() || $jobStruct->isCanceled() ) {
            $this->addParamsToView( [
                    'job_archived'    => true,
                    'job_owner_email' => $jobStruct[ 'job_owner' ],
            ] );
        }

        $activity             = new ActivityLogStruct();
        $activity->id_job     = $jobStruct->id;
        $activity->id_project = $jobStruct->id_project;
        $activity->action     = ActivityLogStruct::ACCESS_REVISE_SUMMARY_PAGE;
        $activity->ip         = Utils::getRealIpAddr();
        $activity->uid        = $this->getUser()->uid;
        $activity->event_date = date( 'Y-m-d H:i:s' );
        Activity::save( $activity );

        $this->render();

    }

    protected function validateTheRequest(): array {

        $filterArgs = [
                'jid'      => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'password' => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ],
        ];

        return filter_var_array( $this->request->paramsNamed()->all(), $filterArgs );
    }

    /**
     * @return array
     */
    private function searchableStatuses(): array {
        $statuses = array_merge(
                Constants_TranslationStatus::$INITIAL_STATUSES,
                Constants_TranslationStatus::$TRANSLATION_STATUSES,
                Constants_TranslationStatus::$REVISION_STATUSES
        );

        return array_map( function ( $item ) {
            return (object)[ 'value' => $item, 'label' => $item ];
        }, $statuses );
    }
}


