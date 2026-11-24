<?php

declare(strict_types=1);

namespace Matecat\Core\Migrations;

use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\RealSqlDaoTestTrait;
use Model\DataAccess\Database;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use RuntimeException;

/**
 * The three migrations that move every ModernMT Full user to ModernMT Lite (MyMemory, id 1).
 *
 * Each migration is a table-wide UPDATE, so each test runs it inside a transaction it rolls back:
 * the seeded rows of the test schema (engine 2 is a ModernMT Full engine) must come out unchanged.
 */
#[Group('PersistenceNeeded')]
#[Group('DaoRealSql')]
class ModernMtRemovalMigrationsTest extends AbstractTest
{
    use RealSqlDaoTestTrait;

    private const string MIGRATIONS_DIR = __DIR__ . '/../../../../migrations/';

    private PDO $pdo;

    private int $uid;

    protected function realSqlTableDeps(): array
    {
        return ['engines', 'project_templates', 'jobs', 'projects'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->realSqlSetUp();
        $this->uid = self::ASSIGNABLE_ID_FLOOR + 7311;

        $database = $this->realSqlDb();
        if (!$database instanceof Database) {
            throw new RuntimeException('The migration tests need the concrete Database to roll back');
        }

        $database->begin();
        $this->pdo = $database->getConnection();
    }

    protected function tearDown(): void
    {
        $database = $this->realSqlDb();
        if ($database instanceof Database) {
            $database->rollback();
        }

        $this->realSqlTearDown(static function (): void {
        });
        parent::tearDown();
    }

    #[Test]
    public function aTemplateOnAModernMtEngineMovesToModernMtLite(): void
    {
        $mmt = $this->engine('Utils\Engines\MMT');
        $shortMmt = $this->engine('MMT');
        $deepL = $this->engine('Utils\Engines\DeepL');

        $byInteger = $this->template(json_encode(['id' => $mmt, 'extra' => ['mmt_glossaries' => [1]]]));
        $byString = $this->template(json_encode(['id' => (string)$shortMmt, 'extra' => []]));
        $onDeepL = $this->template(json_encode(['id' => $deepL, 'extra' => ['deepl_formality' => 'prefer_more']]));
        $noMt = $this->template(null);
        $notJson = $this->template('not json');

        $this->runMigration('20261124120000_migrate_mmt_project_templates_to_mymemory.php');

        self::assertSame(['id' => 1, 'extra' => []], json_decode((string)$this->templateMt($byInteger), true));
        self::assertSame(['id' => 1, 'extra' => []], json_decode((string)$this->templateMt($byString), true));
        self::assertSame(
            ['id' => $deepL, 'extra' => ['deepl_formality' => 'prefer_more']],
            json_decode((string)$this->templateMt($onDeepL), true)
        );
        self::assertNull($this->templateMt($noMt));
        self::assertSame('not json', $this->templateMt($notJson));
    }

    #[Test]
    public function aJobOnAModernMtEngineMovesToModernMtLite(): void
    {
        $mmt = $this->engine('Utils\Engines\MMT');
        $deepL = $this->engine('Utils\Engines\DeepL');

        $onMmt = $this->job($mmt);
        $onDeepL = $this->job($deepL);
        $onLite = $this->job(1);

        $this->runMigration('20261124120100_migrate_mmt_jobs_to_mymemory.php');

        self::assertSame(1, $this->jobEngine($onMmt));
        self::assertSame($deepL, $this->jobEngine($onDeepL));
        self::assertSame(1, $this->jobEngine($onLite));
    }

    #[Test]
    public function theModernMtEnginesAreDeactivatedAndLaraLosesItsModernMtLicense(): void
    {
        $mmt = $this->engine('Utils\Engines\MMT');
        $shortMmt = $this->engine('MMT');
        $deepL = $this->engine('Utils\Engines\DeepL');
        $laraWithLicense = $this->engine('Utils\Engines\Lara', [
            'Lara-AccessKeyId'     => 'id',
            'Lara-AccessKeySecret' => 'secret',
            'MMT-License'          => 'license',
        ]);
        $laraWithoutLicense = $this->engine('Utils\Engines\Lara', [
            'Lara-AccessKeyId'     => 'id',
            'Lara-AccessKeySecret' => 'secret',
        ]);
        $laraNotJson = $this->engine('Utils\Engines\Lara', 'not json');

        // What an installation made before the removal holds as the ModernMT Lite description.
        $this->pdo->exec(
            "UPDATE engines SET description = 'Smart machine translation that learns from your corrections for enhanced quality and productivity thanks to ModernMT’s basic features. To unlock all features, <a href=\"https://www.modernmt.com/pricing#translators\">click here</a>.' WHERE id = 1"
        );

        $this->runMigration('20261124120200_deactivate_mmt_engines.php');

        self::assertSame(
            'Smart machine translation that learns from your corrections for enhanced quality and productivity thanks to ModernMT’s basic features.',
            (string)$this->pdo->query('SELECT description FROM engines WHERE id = 1')->fetchColumn()
        );

        self::assertSame(0, $this->engineActive($mmt));
        self::assertSame(0, $this->engineActive($shortMmt));
        self::assertSame(1, $this->engineActive($deepL));
        self::assertSame(1, $this->engineActive($laraWithLicense));

        self::assertSame(
            ['Lara-AccessKeyId' => 'id', 'Lara-AccessKeySecret' => 'secret'],
            json_decode($this->engineExtra($laraWithLicense), true)
        );
        self::assertSame(
            ['Lara-AccessKeyId' => 'id', 'Lara-AccessKeySecret' => 'secret'],
            json_decode($this->engineExtra($laraWithoutLicense), true)
        );
        self::assertSame('not json', $this->engineExtra($laraNotJson));
    }

    /**
     * Executes a migration's `sql_up` exactly as AbstractMatecatMigration::up() would, statement by
     * statement, on the test connection.
     */
    private function runMigration(string $file): void
    {
        if (!class_exists('Phinx\Migration\AbstractMigration')) {
            require_once __DIR__ . '/PhinxAbstractMigrationStub.php';
        }
        require_once self::MIGRATIONS_DIR . 'AbstractMatecatMigration.php';
        require_once self::MIGRATIONS_DIR . $file;

        $source = (string)file_get_contents(self::MIGRATIONS_DIR . $file);
        if (preg_match('/^class (\w+) extends/m', $source, $match) !== 1) {
            throw new RuntimeException("No migration class in $file");
        }

        /** @var class-string $class */
        $class = $match[1];
        $sqlUp = (new ReflectionClass($class))->getDefaultProperties()['sql_up'];

        foreach ((array)$sqlUp as $sql) {
            $this->pdo->exec($sql);
        }
    }

    /**
     * @param array<string, string>|string $extraParameters
     */
    private function engine(string $classLoad, array|string $extraParameters = []): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO engines (name, type, description, base_url, others, class_load, extra_parameters, penalty, active, uid)
             VALUES ('Migration test engine', 'MT', '', 'http://localhost', '{}', :class_load, :extra, 14, 1, :uid)"
        );
        $stmt->execute([
            'class_load' => $classLoad,
            'extra'      => is_string($extraParameters) ? $extraParameters : (string)json_encode($extraParameters ?: new \stdClass()),
            'uid'        => $this->uid,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    private function template(?string $mt): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO project_templates (name, uid, id_team, mt) VALUES (:name, :uid, 1, :mt)"
        );
        $stmt->execute(['name' => 'migration test ' . bin2hex(random_bytes(4)), 'uid' => $this->uid, 'mt' => $mt]);

        return (int)$this->pdo->lastInsertId();
    }

    private function job(int $idMtEngine): int
    {
        $this->pdo->prepare("INSERT INTO projects (id_customer, create_date) VALUES ('mmt-migration@example.test', NOW())")->execute();
        $idProject = (int)$this->pdo->lastInsertId();

        $stmt = $this->pdo->prepare(
            "INSERT INTO jobs (password, id_project, job_first_segment, job_last_segment, tm_keys, create_date, disabled, source, target, id_mt_engine)
             VALUES (:password, :id_project, 1, 2, '[]', NOW(), 0, 'en-US', 'it-IT', :id_mt_engine)"
        );
        $stmt->execute([
            'password'     => substr(bin2hex(random_bytes(8)), 0, 12),
            'id_project'   => $idProject,
            'id_mt_engine' => $idMtEngine,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    private function templateMt(int $id): ?string
    {
        $value = $this->pdo->query("SELECT mt FROM project_templates WHERE id = $id")->fetchColumn();

        return $value === null ? null : (string)$value;
    }

    private function jobEngine(int $id): int
    {
        return (int)$this->pdo->query("SELECT id_mt_engine FROM jobs WHERE id = $id")->fetchColumn();
    }

    private function engineActive(int $id): int
    {
        return (int)$this->pdo->query("SELECT active FROM engines WHERE id = $id")->fetchColumn();
    }

    private function engineExtra(int $id): string
    {
        return (string)$this->pdo->query("SELECT extra_parameters FROM engines WHERE id = $id")->fetchColumn();
    }
}
