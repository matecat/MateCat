<?php

namespace Matecat\Core\DAO\TestTranslatorsProfilesDAO;

use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\RealSqlDaoTestTrait;
use Model\Translators\TranslatorProfilesStruct;
use Model\Translators\TranslatorsProfilesDao;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Real-SQL coverage for TranslatorsProfilesDao (campaign dao-realsql-90).
 *
 * getByProfile (the only public SQL method) runs against the real unittest DB on both the match
 * and no-match path. translator_profiles rows are inserted directly under an assignable
 * uid_translator (>= ASSIGNABLE_ID_FLOOR) and cleaned uid-scoped; the residue gate asserts
 * whole-table COUNT(*) is unchanged (DoD c).
 */
#[Group('PersistenceNeeded')]
#[Group('DaoRealSql')]
class TranslatorsProfilesDaoRealSqlTest extends AbstractTest
{
    use RealSqlDaoTestTrait;

    private TranslatorsProfilesDao $dao;
    private int $uid;

    protected function realSqlTableDeps(): array
    {
        return ['translator_profiles'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->realSqlSetUp();

        $this->uid = self::ASSIGNABLE_ID_FLOOR + 5401;
        $this->insertRow('en-US', 'it-IT', 0);

        $this->dao = new TranslatorsProfilesDao($this->realSqlDb);
    }

    protected function tearDown(): void
    {
        $this->realSqlTearDown(function (): void {
            $this->realSqlDb->getConnection()
                ->exec("DELETE FROM translator_profiles WHERE uid_translator = {$this->uid}");
        });
        parent::tearDown();
    }

    private function insertRow(string $source, string $target, int $isRevision): void
    {
        $stmt = $this->realSqlDb->getConnection()->prepare(
            "INSERT INTO translator_profiles (uid_translator, source, target, is_revision, translated_words, revised_words) "
            // whole-number *_words: the struct casts these to int, so decimals trip a
            // precision-loss deprecation in AbstractDao hydration (pre-existing struct typing).
            . "VALUES (:uid, :s, :t, :r, 12, 3)"
        );
        $stmt->execute(['uid' => $this->uid, 's' => $source, 't' => $target, 'r' => $isRevision]);
    }

    private function profile(string $source, string $target, int $isRevision): TranslatorProfilesStruct
    {
        return new TranslatorProfilesStruct([
            'uid_translator' => $this->uid,
            'source'         => $source,
            'target'         => $target,
            'is_revision'    => $isRevision,
        ]);
    }

    #[Test]
    public function dao_uses_the_injected_connection_not_the_singleton(): void
    {
        $this->assertInjectedConnection($this->dao);
    }

    #[Test]
    public function getByProfile_returns_the_row_matching_the_unique_key(): void
    {
        $found = $this->dao->getByProfile($this->profile('en-US', 'it-IT', 0));

        $this->assertInstanceOf(TranslatorProfilesStruct::class, $found);
        $this->assertSame($this->uid, (int)$found->uid_translator);
        $this->assertSame('en-US', $found->source);
        $this->assertSame('it-IT', $found->target);
    }

    #[Test]
    public function getByProfile_returns_null_when_no_row_matches(): void
    {
        // same uid/source/target but the revision flag differs -> no row
        $this->assertNull($this->dao->getByProfile($this->profile('en-US', 'it-IT', 1)));
        // unrelated language pair -> no row
        $this->assertNull($this->dao->getByProfile($this->profile('fr-FR', 'de-DE', 0)));
    }
}
