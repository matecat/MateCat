<?php


namespace Features\Dqf\Utils ;

class ProjectMetadata {

    public static $keys = [
            'dqf',
            'dqf_content_type',
            'dqf_industry',
            'dqf_process',
            'dqf_quality_level'
    ];

    /**
     * This function is to be used to filter both postInput from UI and
     * JSON string received from APIs.
     *
     * @return array
     */
    public static function getInputFilter() {
        return [
                'dqf'               => ['filter' => FILTER_VALIDATE_BOOLEAN],
                'dqf_content_type'  => ['filter' => FILTER_VALIDATE_INT ],
                'dqf_industry'      => ['filter' => FILTER_VALIDATE_INT ],
                'dqf_process'       => ['filter' => FILTER_VALIDATE_INT ],
                'dqf_quality_level' => ['filter' => FILTER_VALIDATE_INT ]
        ];
    }

    public static function extractProjectParameters($project_metadata) {
        return [
                'contentTypeId'  => $project_metadata['dqf_content_type'],
                'industryId'     => $project_metadata['dqf_industry'],
                'processId'      => $project_metadata['dqf_process'],
                'qualityLevelId' => $project_metadata['dqf_quality_level']
        ];
    }

}