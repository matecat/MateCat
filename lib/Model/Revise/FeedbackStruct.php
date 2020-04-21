<?php

class Revise_FeedbackStruct extends DataAccess_AbstractDaoObjectStruct implements DataAccess_IDaoStruct{

    /**
     * @var int
     */
    public $id_job;

    /**
     * @var string
     */
    public $password;

    /**
     * @var int
     */
    public $revision_number;

    /**
     * @var string
     */
    public $feedback;
}