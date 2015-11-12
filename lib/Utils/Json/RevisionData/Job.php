<?php


class Json_RevisionData_Job {
    private $data ;

    public function __construct( $data ) {
        $this->data = $data;
    }

    public function render() {
        return array(
            'id'             => (string) $this->data['job_id'],
            'qualityDetails' => $this->data['quality_details'],
            'qualityOverall' => $this->data['quality_overall']
        );
    }
}
