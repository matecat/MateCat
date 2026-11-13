<?php

declare(strict_types=1);

namespace Matecat\PhpStan\Rules;

use Model\DataAccess\DaoCacheTrait;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\ShouldNotHappenException;

/**
 * Forbids a class that uses DaoCacheTrait from declaring its own cache write method.
 *
 * `_setInCacheMap()` and `_setManyInCacheMap()` enforce DaoCacheTrait::CACHE_MAP_FIELD_CAP: past
 * 10,000 fields they drop the hash, so no hash can grow until it fills Redis. Both are final, which
 * stops a subclass from replacing them. PHP does not apply that to the class that uses the trait:
 * when that class declares a method with the same name, its own method silently wins, and the cap
 * is gone with no error.
 *
 * Example: `class Pager { use DaoCacheTrait; protected function _setInCacheMap(...) { ... } }`
 * is reported.
 *
 * @implements Rule<ClassMethod>
 */
final class NoCacheMapWriteOverrideRule implements Rule
{
    /** @var list<string> lower-case names of the guarded methods */
    private const array METHODS = ['_setincachemap', '_setmanyincachemap'];

    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    /**
     * @return list<RuleError>
     * @throws ShouldNotHappenException
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof ClassMethod || !in_array($node->name->toLowerString(), self::METHODS, true)) {
            return [];
        }

        // The trait's own declaration of the method.
        if ($scope->isInTrait()) {
            return [];
        }

        $class = $scope->getClassReflection();
        if ($class === null || !$class->hasTraitUse(DaoCacheTrait::class)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                $class->getName() . '::' . $node->name->toString() . '() replaces the DaoCacheTrait cache write, '
                . 'which drops a hash past 10,000 fields. Without it a hash can grow until it fills Redis. '
                . 'Remove the method and use the one from the trait.'
            )
                ->identifier('matecat.cacheMapWriteOverride')
                ->build(),
        ];
    }
}
