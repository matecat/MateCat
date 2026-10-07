<?php

namespace Matecat\Core\Model\LQA\QAModelTemplate;

use InvalidArgumentException;
use Matecat\TestHelpers\AbstractTest;
use Model\LQA\QAModelTemplate\QAModelTemplateStruct;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Utils\Validation\UserSuppliedName;
use Utils\Validator\JSONSchema\Errors\JSONValidatorException;
use Utils\Validator\JSONSchema\JSONValidator;
use Utils\Validator\JSONSchema\JSONValidatorObject;

/**
 * `qa_model_templates`.`label` was a varchar(45), and the schema and the struct both refused a
 * longer label with a 400 — so a 51-character name, ordinary for a quality framework, could not be
 * saved at all. The column is 255 now, like every other template name, and both gates follow it.
 */
#[Group('unit')]
class QAModelTemplateLabelLengthTest extends AbstractTest
{
    /** The name the report was filed with: 51 characters, markup included. */
    private const string REPORTED_LABEL = '<script>alert("sono un codice malevolo!");</script>';

    private static function payload(string $label): string
    {
        return json_encode([
            'model' => [
                'version' => 1,
                'label' => $label,
                'categories' => [
                    ['code' => 'ACC', 'label' => 'Accuracy', 'sort' => 1, 'severities' => [['code' => 'MIN', 'label' => 'Minor', 'penalty' => 1, 'sort' => 1]]],
                ],
                'passfail' => [
                    'type' => 'points_per_thousand',
                    'thresholds' => [['label' => 'R1', 'value' => 0], ['label' => 'R2', 'value' => 10]],
                ],
            ],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    private static function validateAgainstSchema(string $json): void
    {
        (new JSONValidator('qa_model.json', true))->validate(new JSONValidatorObject($json));
    }

    #[Test]
    public function theLabelCapIsTheSameAsEveryOtherTemplateName(): void
    {
        self::assertSame(UserSuppliedName::TEMPLATE_NAME_MAX_LENGTH, UserSuppliedName::QA_MODEL_LABEL_MAX_LENGTH);
    }

    #[Test]
    public function theSchemaAcceptsALabelAtTheCap(): void
    {
        self::validateAgainstSchema(self::payload(str_repeat('a', UserSuppliedName::QA_MODEL_LABEL_MAX_LENGTH)));

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function theSchemaRefusesALabelOverTheCap(): void
    {
        $this->expectException(JSONValidatorException::class);

        self::validateAgainstSchema(self::payload(str_repeat('a', UserSuppliedName::QA_MODEL_LABEL_MAX_LENGTH + 1)));
    }

    #[Test]
    public function theReportedLabelPassesTheSchemaAndIsStoredAsTyped(): void
    {
        $json = self::payload(self::REPORTED_LABEL);
        self::validateAgainstSchema($json);

        $struct = (new QAModelTemplateStruct())->hydrateFromJSON($json);

        self::assertSame(self::REPORTED_LABEL, $struct->label);
    }

    #[Test]
    public function theStructRefusesALabelOverTheCap(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new QAModelTemplateStruct())->hydrateFromJSON(
            self::payload(str_repeat('a', UserSuppliedName::QA_MODEL_LABEL_MAX_LENGTH + 1))
        );
    }
}
