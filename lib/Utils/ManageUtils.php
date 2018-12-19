<?php

use API\V2\Json\Project;
use Exceptions\NotFoundException;

class ManageUtils {

    /**
     * @param Users_UserStruct       $user
     * @param                        $start
     * @param                        $step
     * @param                        $search_in_pname
     * @param                        $search_source
     * @param                        $search_target
     * @param                        $search_status
     * @param                        $search_only_completed
     * @param                        $project_id
     * @param \Teams\TeamStruct|null $team
     * @param Users_UserStruct|null  $assignee
     * @param bool                   $no_assignee
     *
     * @return array
     * @throws NotFoundException
     * @throws Exception
     */
    public static function queryProjects(
            Users_UserStruct $user, $start, $step, $search_in_pname,
            $search_source, $search_target, $search_status, $search_only_completed,
            $project_id,
            \Teams\TeamStruct $team = null,
            Users_UserStruct $assignee = null,
            $no_assignee = false
    ) {

        $id_list = getProjects(
            $user, $start, $step, $search_in_pname, $search_source, $search_target,
            $search_status, $search_only_completed, $project_id, $team,
            $assignee, $no_assignee
        );

        $_projects = new Projects_ProjectDao();
        $projects = $_projects->getByIdList( $id_list );

        $projectRenderer = new Project( $projects );
        $projectRenderer->setUser( $user );
        return $projectRenderer->render();

    }

    /**
     * Formats a date for user visualization.
     *
     * @param $my_date        string A date in mysql format. <br/>
     *                        <b>E,g.</b> 2014-01-01 23:59:48
     *
     * @return string A formatted date
     * @throws Exception
     */
    public static function formatJobDate( $my_date ) {

        $date          = new DateTime( $my_date );
        $formattedDate = $date->format( 'Y M d H:i' );

        $now       = new DateTime();
        $yesterday = $now->sub( new DateInterval( 'P1D' ) );

        //today
        if ( $now->format( 'Y-m-d' ) == $date->format( 'Y-m-d' ) ) {
            $formattedDate = "Today, " . $date->format( 'H:i' );
        } //yesterday
        else {
            if ( $yesterday->format( 'Y-m-d' ) == $date->format( 'Y-m-d' ) ) {
                $formattedDate = 'Yesterday, ' . $date->format( 'H:i' );
            } //this month
            else {
                if ( $now->format( 'Y-m' ) == $date->format( 'Y-m' ) ) {
                    $formattedDate = $date->format( 'M d, H:i' );
                } //this year
                else {
                    if ( $now->format( 'Y' ) == $date->format( 'Y' ) ) {
                        $formattedDate = $date->format( 'M d, H:i' );
                    }
                }
            }
        }

        return $formattedDate;

    }

}
