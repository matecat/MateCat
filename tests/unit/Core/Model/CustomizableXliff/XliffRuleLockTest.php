<?php

namespace Matecat\Core\Model\CustomizableXliff;

use DomainException;
use Exception;
use Matecat\TestHelpers\AbstractTest;
use Model\Xliff\DTO\AbstractXliffRule;
use Model\Xliff\DTO\DefaultRule;
use Model\Xliff\DTO\Xliff12Rule;
use Model\Xliff\DTO\Xliff20Rule;
use Model\Xliff\DTO\XliffRulesModel;
use Model\Xliff\XliffConfigTemplateStruct;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Utils\Validator\JSONSchema\Errors\JSONValidatorException;
use Utils\Validator\JSONSchema\JSONValidator;
use Utils\Validator\JSONSchema\JSONValidatorObject;

/**
 * The per-rule `lock` option of an XLIFF rule.
 */
class XliffRuleLockTest extends AbstractTest
{
    #[Test]
    public function aRuleIsUnlockedByDefault(): void
    {
        $rule = new Xliff12Rule(['translated'], 'pre-translated', 'translated', 'ice');

        $this->assertFalse($rule->isLocked());
        $this->assertArrayNotHasKey('lock', $rule->jsonSerialize());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function lockableEditorValues(): array
    {
        return [
            'translated' => ['translated'],
            'approved'   => ['approved'],
            'approved2'  => ['approved2'],
        ];
    }

    #[Test]
    #[DataProvider('lockableEditorValues')]
    public function aRuleWithAReviewableEditorStatusCanBeLocked(string $editor): void
    {
        $rule12 = new Xliff12Rule(['translated'], 'pre-translated', $editor, 'ice', true);
        $rule20 = Xliff20Rule::fromArray(['states' => ['final'], 'analysis' => 'pre-translated', 'editor' => $editor, 'lock' => true]);

        $this->assertTrue($rule12->isLocked());
        $this->assertTrue($rule20->isLocked());
    }

    #[Test]
    public function aDraftRuleCanNotBeLocked(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('A rule with editor status DRAFT can not be locked.');

        new Xliff12Rule(['translated'], 'pre-translated', 'draft', 'ice', true);
    }

    #[Test]
    public function aNewRuleCanNotBeLocked(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('A rule with editor status NEW can not be locked.');

        Xliff20Rule::fromArray(['states' => ['initial'], 'analysis' => 'new', 'lock' => true]);
    }

    #[Test]
    public function aNoStateRuleTakesTheLockToo(): void
    {
        $rule = Xliff12Rule::fromArray(['states' => ['no-state'], 'analysis' => 'pre-translated', 'editor' => 'approved', 'lock' => true]);

        $this->assertTrue($rule->isNoStateRule());
        $this->assertTrue($rule->isLocked());
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function theFallbackRuleIsNeverLocked(): void
    {
        $model = XliffRulesModel::fromArray([
            'xliff12' => [['states' => ['translated'], 'analysis' => 'pre-translated', 'editor' => 'approved', 'lock' => true]],
        ]);

        $fallback = $model->getMatchingRule(1, 'signed-off');

        $this->assertInstanceOf(DefaultRule::class, $fallback);
        $this->assertFalse($fallback->isLocked());
        $this->assertFalse((new DefaultRule(['final'], AbstractXliffRule::_ANALYSIS_PRE_TRANSLATED))->isLocked());
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function rulesWithoutLockSerializeByteIdentical(): void
    {
        $json = '{"xliff12":[{"states":["translated","exact-match"],"analysis":"pre-translated","editor":"approved","match_category":"ice"},'
            . '{"states":["new"],"analysis":"new"}],'
            . '"xliff20":[{"states":["final"],"analysis":"pre-translated","editor":"approved2","match_category":"tm_100"}]}';

        $this->assertSame($json, json_encode(XliffRulesModel::fromArray(json_decode($json, true))));
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function aLockedRuleSurvivesTheRoundTrip(): void
    {
        $json = '{"xliff12":[{"states":["final"],"analysis":"pre-translated","editor":"approved2","match_category":"ice","lock":true}],"xliff20":[]}';

        $model = XliffRulesModel::fromArray(json_decode($json, true));

        $this->assertSame($json, json_encode($model));
        $this->assertSame($json, json_encode(XliffRulesModel::fromArray($model->getArrayCopy())));
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function aTemplateKeepsTheLockThroughHydrationAndSerialization(): void
    {
        $struct = (new XliffConfigTemplateStruct())->hydrateFromJSON(json_encode([
            'name'  => 'locked rules',
            'rules' => [
                'xliff12' => [['states' => ['final'], 'analysis' => 'pre-translated', 'editor' => 'approved2', 'lock' => true]],
                'xliff20' => [['states' => ['translated'], 'analysis' => 'pre-translated', 'editor' => 'translated']],
            ],
        ]), 7);

        $rules = $struct->rules?->getArrayCopy() ?? [];
        $this->assertTrue($rules['xliff12'][0]['lock']);
        $this->assertArrayNotHasKey('lock', $rules['xliff20'][0]);

        // the stored column is the serialized model: reading it back keeps the lock
        $reloaded = (new XliffConfigTemplateStruct())->hydrateRulesFromJson((string)$struct->rules);
        $this->assertTrue($reloaded->rules?->getMatchingRule(1, 'final')->isLocked());
        $this->assertFalse($reloaded->rules?->getMatchingRule(2, 'translated')->isLocked());
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function aTemplateWithALockedDraftIsRefused(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionCode(400);

        (new XliffConfigTemplateStruct())->hydrateFromJSON(json_encode([
            'name'  => 'locked draft',
            'rules' => ['xliff20' => [['states' => ['translated'], 'analysis' => 'pre-translated', 'editor' => 'draft', 'lock' => true]]],
        ]), 7);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function theSchemaAcceptsABooleanLock(): void
    {
        $validator = new JSONValidator('xliff_parameters_rules_content.json', true);

        foreach (['xliff12' => 'translated', 'xliff20' => 'final'] as $version => $state) {
            $object = new JSONValidatorObject(json_encode([
                $version => [['states' => [$state], 'analysis' => 'pre-translated', 'editor' => 'approved', 'lock' => true]],
            ]));

            $this->assertSame($object, $validator->validate($object));
        }
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function ruleVersions(): array
    {
        return [
            'xliff12' => ['xliff12', 'translated'],
            'xliff20' => ['xliff20', 'final'],
        ];
    }

    /**
     * @throws Exception
     */
    #[Test]
    #[DataProvider('ruleVersions')]
    public function theSchemaRejectsANonBooleanLock(string $version, string $state): void
    {
        $validator = new JSONValidator('xliff_parameters_rules_content.json', true);
        $object = new JSONValidatorObject(json_encode([
            $version => [['states' => [$state], 'analysis' => 'pre-translated', 'editor' => 'approved', 'lock' => 'yes']],
        ]));

        $this->expectException(JSONValidatorException::class);
        $validator->validate($object);
    }
}
