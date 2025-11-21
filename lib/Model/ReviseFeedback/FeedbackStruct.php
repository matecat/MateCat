<?php

namespace Model\ReviseFeedback;

use Model\DataAccess\AbstractDaoObjectStruct;
use Model\DataAccess\IDaoStruct;

class FeedbackStruct extends AbstractDaoObjectStruct implements IDaoStruct
{

    /**
     * @var int
     */
    public int $id_job;

    /**
     * @var string
     */
    public string $password;

    /**
     * @var int
     */
    public int $revision_number;

    /**
     * @var string
     */
    public string $feedback;
}