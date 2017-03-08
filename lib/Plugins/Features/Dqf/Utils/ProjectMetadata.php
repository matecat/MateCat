<?php


namespace Features\Dqf\Utils ;

class ProjectMetadata {

    public static $keys = array(
            'dqf'
    );

    /**
     * This function is to be used to filter both postInput from UI and
     * JSON string received from APIs.
     *
     * @return array
     */
    public static function getInputFilter() {
        return array(
                'dqf' => array(
                        'filter' => FILTER_VALIDATE_BOOLEAN,
                )
        );

    }

    /**
     *
     */
    public static function extractProjectParameters($project_metadata) {
        // TODO: mocking for now, get saved project options into project metadata

        return array(
                'contentTypeId'  => 1, // User interface text
                'industryId'     => 2, // Automotive
                'processId'      => 5, // Human Translation
                'qualityLevelId' => 2  // High quality
        );
    }


}