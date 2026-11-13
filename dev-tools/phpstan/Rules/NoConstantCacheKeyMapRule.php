<?php

declare(strict_types=1);

namespace Matecat\PhpStan\Rules;

use Model\DataAccess\AbstractDao;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

/**
 * Forbids a cached DAO read that passes a constant key map together with values that change.
 *
 * `AbstractDao::_fetchObjectMap()` stores each result as one field of the Redis hash named by the
 * key map. The field is named after the query and its bound values, so a key map that does not
 * change while the values do puts every call in the same hash, one new field per call. Every
 * write renews the hash TTL, so the hash never expires.
 *
 * Example: `SegmentMetadataDao::getAllInRange()` passed `"...::getAllInRange"` with
 * `[$startSid, $stopSid]`. Each range became one more field of the same hash.
 *
 * Reported: a key map that names the same hash on every call (see isFixedKeyMap()), when the bound
 * values are not all constants. Not reported: a key map built from the values
 * (`static::class . '::fetchById-' . $id`), a null key map (`_fetchObjectMap()` then builds it from
 * the values), and constant bound values, which always produce the same single field.
 *
 * DaoCacheTrait::CACHE_MAP_FIELD_CAP is the runtime backstop for the shapes this rule cannot see:
 * values written into the SQL, a call that unpacks its arguments, and code that writes through
 * `_setInCacheMap()` without going through `_fetchObjectMap()`.
 *
 * @implements Rule<MethodCall>
 */
final class NoConstantCacheKeyMapRule implements Rule
{
    /** The method name, lower case: PHP method names are case-insensitive. */
    private const string METHOD = '_fetchobjectmap';
    private const int BIND_PARAMS_POSITION = 2;
    private const int KEY_MAP_POSITION = 3;

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    /**
     * @return list<RuleError>
     * @throws ShouldNotHappenException
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof MethodCall || !$node->name instanceof Identifier) {
            return [];
        }

        if ($node->name->toLowerString() !== self::METHOD) {
            return [];
        }

        if (!(new ObjectType(AbstractDao::class))->isSuperTypeOf($scope->getType($node->var))->yes()) {
            return [];
        }

        $keyMap = $this->argument($node, self::KEY_MAP_POSITION, 'keyMap');
        $bindParams = $this->argument($node, self::BIND_PARAMS_POSITION, 'bindParams');

        if ($keyMap === null || $bindParams === null) {
            return [];
        }

        if (!$this->isFixedKeyMap($keyMap->value, $scope)) {
            return [];
        }

        if ($this->holdsOnlyConstants($scope->getType($bindParams->value))) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'A constant key map puts every call of this cached read in one Redis hash, one new '
                . 'field per bound value, and the hash never expires. Build the key map from the bound '
                . "values (static::class . '::read-' . \$id), or pass null and let _fetchObjectMap() build it."
            )
                ->identifier('matecat.constantCacheKeyMap')
                ->build(),
        ];
    }

    /**
     * Whether the key map names the same hash on every call.
     *
     * Two ways to tell:
     * - its type is one or more constant strings, null aside: `'Dao::read'`, `self::KEY`,
     *   `self::class . '::read'`, or `$flag ? 'Dao::read' : null`;
     * - it is built only from literals and class constants: `static::class . '::read'`. PHPStan
     *   types `static::class` as `class-string<static>` in a class that is not final, but it is
     *   one name per DAO class.
     *
     * An empty string or '0' does not count: `_fetchObjectMap()` treats it as no key map and builds
     * one from the bound values.
     */
    private function isFixedKeyMap(Expr $keyMap, Scope $scope): bool
    {
        $type = TypeCombinator::removeNull($scope->getType($keyMap));
        $strings = $type->getConstantStrings();
        $isConstant = $strings !== [] && $type->isString()->yes();

        if (!$isConstant && !$this->isBuiltFromConstants($keyMap)) {
            return false;
        }

        foreach ($strings as $string) {
            if (empty($string->getValue())) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether an expression is made only of string literals and class constants, joined with `.`.
     */
    private function isBuiltFromConstants(Expr $expr): bool
    {
        return match (true) {
            $expr instanceof String_, $expr instanceof ClassConstFetch => true,
            $expr instanceof Concat => $this->isBuiltFromConstants($expr->left) && $this->isBuiltFromConstants($expr->right),
            default => false,
        };
    }

    /**
     * Whether every value of the bound array is a constant, such as `['active']` or `[]`.
     *
     * `Type::isConstantValue()` is not enough: it answers yes for `array{int, int}`, because the
     * shape of the array is fixed even though its values are not.
     */
    private function holdsOnlyConstants(Type $bindParams): bool
    {
        $arrays = $bindParams->getConstantArrays();
        if ($arrays === []) {
            return false;
        }

        foreach ($arrays as $array) {
            foreach ($array->getValueTypes() as $value) {
                if (!$value->isConstantValue()->yes()) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * The argument at a position, or the named argument that replaces it. Null when the call
     * unpacks an array (`...$args`), because then positions cannot be matched.
     */
    private function argument(MethodCall $call, int $position, string $name): ?Arg
    {
        foreach ($call->getArgs() as $i => $arg) {
            if ($arg->unpack) {
                return null;
            }

            if ($arg->name !== null) {
                if ($arg->name->name === $name) {
                    return $arg;
                }
                continue;
            }

            if ($i === $position) {
                return $arg;
            }
        }

        return null;
    }
}
