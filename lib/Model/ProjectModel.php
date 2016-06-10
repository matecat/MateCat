<?php

use Features\QaCheckBlacklist\BlacklistFromZip ;

class ProjectModel {

    /**
     * @var Projects_ProjectStruct
     */
    protected $project_struct ;

    protected $blacklist ;

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
}