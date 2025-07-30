<?php

namespace Revise;

use DataAccess\AbstractDaoObjectStruct;
use DataAccess\IDaoStruct;

class FeedbackStruct extends AbstractDaoObjectStruct implements IDaoStruct {

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