<?php

namespace unit\DAO\TestFeedbackDAO;

use Model\DataAccess\IDatabase;
use Model\ReviseFeedback\FeedbackDAO;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

class FeedbackDaoTest extends AbstractTest
{
    private FeedbackDAO $dao;

    protected function setUp(): void
    {
        parent::setUp();
        $dbStub = $this->createStub(IDatabase::class);
        $this->dao = new FeedbackDAO($dbStub);
    }

    #[Test]
    public function getFeedbackReturnsNullWhenIdJobIsNull(): void
    {
        $this->assertNull($this->dao->getFeedback(null, 'password', 1));
    }

    #[Test]
    public function getFeedbackReturnsNullWhenPasswordIsNull(): void
    {
        $this->assertNull($this->dao->getFeedback(1, null, 1));
    }

    #[Test]
    public function getFeedbackReturnsNullWhenRevisionNumberIsNull(): void
    {
        $this->assertNull($this->dao->getFeedback(1, 'password', null));
    }
}
