<?php

namespace Matecat\Core\Utils\Validation;

use Controller\API\App\CommentController;
use Controller\API\App\EngineController;
use Controller\API\V2\TeamsController;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use ReflectionClass;
use RuntimeException;
use Utils\Tools\CatUtils;
use Utils\TmKeyManagement\TmKeyManager;
use Utils\Validation\UserSuppliedName;

/**
 * Every cap a name is held to is a claim about a column, and the claim was wrong twice: person names
 * were cut to 50 in a varchar(100), and a stored key name over 255 was rewritten on the merge path
 * of a varchar(512). Both wrong values were written down in a comment beside the constant, so a
 * comment is not what catches this.
 *
 * The widths are read out of `tests/inc/unittest_matecat_local.sql` rather than out of
 * `information_schema`, deliberately. That file is what CI loads, so it is the repository's own
 * statement of the schema — and a live database can be behind it, which is what a developer's local
 * copy usually is. Reading the file makes this a test of the code rather than of whoever last
 * reloaded their database, and it still fails the moment an `ALTER TABLE` narrows a column without
 * the constant following it.
 */
#[Group('unit')]
class NameLengthSchemaTest extends AbstractTest
{
    private const string SCHEMA_FILE = __DIR__ . '/../../../../inc/unittest_matecat_local.sql';

    /**
     * Constant => the column it has to fit. A private constant is read by reflection rather than
     * made public for the test: the value is the contract, not the visibility.
     *
     * @return array<string, array{int, string, string}>
     */
    public static function nameLengthProvider(): array
    {
        return [
            'person name' => [CatUtils::PERSON_NAME_MAX_LENGTH, 'users', 'first_name'],
            'person surname' => [CatUtils::PERSON_NAME_MAX_LENGTH, 'users', 'last_name'],
            'project name' => [CatUtils::PROJECT_NAME_MAX_LENGTH, 'projects', 'name'],
            'resource name' => [TmKeyManager::RESOURCE_NAME_MAX_LENGTH, 'memory_keys', 'key_name'],
            'project template name' => [UserSuppliedName::TEMPLATE_NAME_MAX_LENGTH, 'project_templates', 'name'],
            'xliff template name' => [UserSuppliedName::TEMPLATE_NAME_MAX_LENGTH, 'xliff_config_templates', 'name'],
            'filters template name' => [UserSuppliedName::TEMPLATE_NAME_MAX_LENGTH, 'filters_config_templates', 'name'],
            'payable rate template name' => [UserSuppliedName::TEMPLATE_NAME_MAX_LENGTH, 'payable_rate_templates', 'name'],
            'qa model label' => [UserSuppliedName::QA_MODEL_LABEL_MAX_LENGTH, 'qa_model_templates', 'label'],
            'commenter name' => [self::privateConstant(CommentController::class, 'COMMENTER_NAME_MAX_LENGTH'), 'comments', 'full_name'],
            'engine name' => [self::privateConstant(EngineController::class, 'ENGINE_NAME_MAX_LENGTH'), 'engines', 'name'],
            'team name' => [self::privateConstant(TeamsController::class, 'NAME_MAX_STORED_LENGTH'), 'teams', 'name'],
        ];
    }

    #[DataProvider('nameLengthProvider')]
    public function testTheCapMatchesTheColumnItClaims(int $cap, string $table, string $column): void
    {
        self::assertSame(
            self::declaredWidth($table, $column),
            $cap,
            'The cap for ' . $table . '.' . $column . ' does not match the column: a smaller cap'
            . ' silently rewrites a name that fitted, a larger one lets MySQL do the cutting.'
        );
    }

    /**
     * The team name is the one field measured twice — once against the column, once against what the
     * reader sees — so its readable cap has to stay inside the stored one.
     */
    public function testTheTeamsReadableCapFitsInsideTheStoredOne(): void
    {
        self::assertLessThanOrEqual(
            self::privateConstant(TeamsController::class, 'NAME_MAX_STORED_LENGTH'),
            self::privateConstant(TeamsController::class, 'NAME_MAX_LENGTH')
        );
    }

    private static function declaredWidth(string $table, string $column): int
    {
        $schema = file_get_contents(self::SCHEMA_FILE);

        if ($schema === false) {
            throw new RuntimeException('cannot read ' . self::SCHEMA_FILE);
        }

        // The table body only, so a column of the same name in another table cannot answer for this
        // one — `translators`.`first_name` is a varchar(45) and would.
        $body = preg_match(
            '/CREATE TABLE `' . preg_quote($table, '/') . '`\s*\((.*?)\n\)/s',
            $schema,
            $matches
        ) === 1 ? $matches[1] : throw new RuntimeException('no CREATE TABLE for ' . $table);

        return preg_match(
            '/`' . preg_quote($column, '/') . '`\s+varchar\((\d+)\)/i',
            $body,
            $found
        ) === 1 ? (int)$found[1] : throw new RuntimeException('no varchar column ' . $table . '.' . $column);
    }

    private static function privateConstant(string $class, string $name): int
    {
        return (int)(new ReflectionClass($class))->getConstant($name);
    }
}
