<?php

namespace Matecat\Core\Model\Comments;

use Matecat\TestHelpers\AbstractTest;
use Model\Comments\BaseCommentStruct;
use Model\Comments\CommentDao;
use Model\DataAccess\IDatabase;
use PHPUnit\Framework\Attributes\Test;

/**
 * RED→GREEN guard test for BaseCommentStruct::templateMessage singleton removal.
 *
 * Written BEFORE the implementation change (TDD strict RED step).
 * After T1 implementation the test must be GREEN.
 */
class BaseCommentStructGuardTest extends AbstractTest
{
    /**
     * templateMessage must use the injected CommentDao, never the singleton.
     *
     * Before T1: templateMessage() builds `new CommentDao(\Model\DataAccess\Database::obtain())` internally → hits singleton.
     * After T1: templateMessage(CommentDao $dao) — injected dao is used, singleton never touched.
     */
    #[Test]
    public function templateMessage_uses_injected_dao_not_singleton(): void
    {
        $struct = new BaseCommentStruct();
        $struct->message = 'Hello @user!';

        // Mock CommentDao — placeholdContent must be called on the injected instance
        $dao = $this->createMock(CommentDao::class);
        $dao->expects($this->once())
            ->method('placeholdContent')
            ->with('Hello @user!')
            ->willReturn('Hello [user]!');

        // Poison singleton — must never be touched after T1
        $poison = $this->createMock(IDatabase::class);
        $poison->expects($this->never())->method('getConnection');
        $this->setDatabaseInstance($poison);

        // After T1: templateMessage(CommentDao $dao)
        $struct->templateMessage($dao);

        $this->assertSame('Hello [user]!', $struct->message);
    }
}
