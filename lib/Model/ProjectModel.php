<?php

use Features\QaCheckBlacklist\BlacklistFromZip ;

class ProjectModel {

    /**
     * @var Projects_ProjectStruct
     */
    protected $project_struct ;

    protected $blacklist ;

    protected $willChange = array();

    public function __construct(Projects_ProjectStruct $project ) {
        $this->project_struct = $project ;
    }

    public function getBlacklist() {
        // TODO: replace with check of file exitence, don't read whole file. 
        return BlacklistFromZip::getContent( $this->project_struct->getFirstOriginalZipPath() ) ;
    }

    /**
     * Caches the information of blacklist file presence to project metadata.
     */
    public function saveBlacklistPresence() {
        $blacklist = $this->getBlacklist();
        
        if ( $blacklist ) {
            $this->project_struct->setMetadata('has_blacklist', '1') ;
        }
    }

    public function resetUpdateList() {
        $this->willChange = array();
    }

    public function prepareUpdate( $field, $value ) {
        $this->willChange[ $field ] = $value ;
    }

    /**
     *  prepare a new struct
     *
     */
    public function update() {
        $newStruct = new Projects_ProjectStruct( $this->project_struct->toArray());

        if ( isset( $this->willChange['name'] ) ) {
            $this->checkName();
        }

        if ( isset( $this->willChange['id_assignee'] ) ) {
            $this->checkIdAssignee();
        }

        foreach( $this->willChange as $field => $value ) {
            $newStruct->$field = $value ;
        }

        $update = Projects_ProjectDao::updateStruct($newStruct, array(
            'fields' => array_keys($this->willChange)
        ) );

        $this->project_struct = $newStruct ;

        return $this->project_struct ;
    }

    private function checkName() {
        if ( empty( $this->willChange['name'] ) ) {
            throw new \Exceptions\ValidationError('Project name cannot be empty') ;
        }
    }

    private function checkIdAssignee() {
        $membershipDao = new \Organizations\MembershipDao();
        $members = $membershipDao->getMemberListByOrganizationId($this->project_struct->id_organization);
        $id_assignee = $this->willChange['id_assignee'] ;
        $found  = array_filter($members, function(\Organizations\MembershipStruct $member) use ( $id_assignee ) {
            return ( $id_assignee == $member->uid )  ;
        });

        if ( empty( $found ) ) {
            throw new \Exceptions\ValidationError('Assignee must be project member');
        }

    }

}