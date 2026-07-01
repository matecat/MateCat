<?php

declare(strict_types=1);

namespace Matecat\PhpStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Forbids direct calls to Bootstrap::getDatabase() outside the small allow-list of
 * composition-root entry points.
 *
 * The database connection is built once at the composition root (Bootstrap) and must
 * be injected downstream as an IDatabase. This mirrors the removal of
 * Database::obtain(): business-logic classes (DAOs, services, models, controllers)
 * receive an injected connection rather than reaching for a global accessor. Only
 * entry points (router, daemons, workers, CLI task bootstraps) may read it once.
 *
 * @implements Rule<StaticCall>
 */
final class NoDirectBootstrapGetDatabaseRule implements Rule
{
    /** @var list<string> normalized ("/"-separated, leading-slash) path suffixes allowed to call the method */
    private array $allowedFileSuffixes;

    /**
     * @param list<string> $allowedFiles project-relative paths permitted to call Bootstrap::getDatabase()
     */
    public function __construct(array $allowedFiles)
    {
        $this->allowedFileSuffixes = array_map(
            static fn(string $p): string => '/' . ltrim(str_replace('\\', '/', $p), '/'),
            $allowedFiles
        );
    }

    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    /**
     * @return list<\PHPStan\Rules\RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof StaticCall) {
            return [];
        }

        // Method must be the literal identifier getDatabase().
        if (!$node->name instanceof Identifier || strtolower($node->name->name) !== 'getdatabase') {
            return [];
        }

        // Class must resolve to the global Bootstrap class (handles \Bootstrap, self::, static::).
        if (!$node->class instanceof Name) {
            return [];
        }
        if (ltrim($scope->resolveName($node->class), '\\') !== 'Bootstrap') {
            return [];
        }

        $file = str_replace('\\', '/', $scope->getFile());
        foreach ($this->allowedFileSuffixes as $suffix) {
            if (str_ends_with($file, $suffix)) {
                return [];
            }
        }

        return [
            RuleErrorBuilder::message(
                'Direct call to Bootstrap::getDatabase() is not allowed here. The database connection is built once '
                . 'at the composition root and must be injected as an IDatabase. Only entry points (router, daemons, '
                . 'workers, CLI task bootstraps) listed in the rule allow-list may read it.'
            )
                ->identifier('matecat.bootstrapGetDatabase')
                ->build(),
        ];
    }
}
