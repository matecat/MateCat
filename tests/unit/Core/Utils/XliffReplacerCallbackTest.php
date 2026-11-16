<?php

namespace Matecat\Core\Utils;

use Exception;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\Database;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectStruct;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Utils\XliffReplacer\XliffReplacerCallback;

class XliffReplacerCallbackTest extends AbstractTest
{
    /**
     * @throws Exception
     */
    #[Test]
    public function testSegmentsWithTagG()
    {
        $jobStructMock = $this->getStubBuilder(JobStruct::class)
            ->setConstructorArgs([
                [
                    'id' => '1',
                    'password' => 'password',
                    'source' => 'en-US',
                    'target' => 'it-IT'
                ]
            ])->getStub();

        $projectStructMock = $this->getStubBuilder(ProjectStruct::class)->getStub();
        $jobStructMock->method('getProject')->willReturn($projectStructMock);

        $segment = '<g id="1">Hello</g>';
        $target = '<g id="3">Hola</g>';

        /** @noinspection PhpParamsInspection */
        $xliffReplacerCallback = new XliffReplacerCallback(new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class)), 'en-EN', 'es-ES', $jobStructMock, obtainTestDatabase());

        $this->assertTrue($xliffReplacerCallback->thereAreErrors(1, $segment, $target));
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testSegmentsWithTagPh()
    {
        $jobStructMock = $this->getStubBuilder(JobStruct::class)
            ->setConstructorArgs([
                [
                    'id' => '1',
                    'password' => 'password',
                    'source' => 'en-US',
                    'target' => 'it-IT'
                ]
            ])->getStub();

        $projectStructMock = $this->getStubBuilder(ProjectStruct::class)->getStub();
        $jobStructMock->method('getProject')->willReturn($projectStructMock);

        $segment = '<ph id="1"/> Hello';
        $target = '<ph id="3"/> Hola';

        /** @noinspection PhpParamsInspection */
        $xliffReplacerCallback = new XliffReplacerCallback(new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class)), 'en-EN', 'es-ES', $jobStructMock, obtainTestDatabase());

        $this->assertTrue($xliffReplacerCallback->thereAreErrors(1, $segment, $target));
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testSegmentsWithDifferentTagPh()
    {
        $jobStructMock = $this->getStubBuilder(JobStruct::class)
            ->setConstructorArgs([
                [
                    'id' => '1',
                    'password' => 'password',
                    'source' => 'en-US',
                    'target' => 'it-IT'
                ]
            ])->getStub();

        $projectStructMock = $this->getStubBuilder(ProjectStruct::class)->getStub();

        $jobStructMock->method('getProject')->willReturn($projectStructMock);

        $segment = '<ph id="1"/> Hello';
        $target = '<ph x="test"/> Hola';

        /** @noinspection PhpParamsInspection */
        $xliffReplacerCallback = new XliffReplacerCallback(new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class)), 'en-EN', 'es-ES', $jobStructMock, obtainTestDatabase());

        $this->assertTrue($xliffReplacerCallback->thereAreErrors(1, $segment, $target));
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testAMoreComplexCase()
    {
        $jobStructMock = $this->getStubBuilder(JobStruct::class)
            ->setConstructorArgs([
                [
                    'id' => '1',
                    'password' => 'password',
                    'source' => 'en-US',
                    'target' => 'es-ES'
                ]
            ])->getStub();

        $projectStructMock = $this->getStubBuilder(ProjectStruct::class)->getStub();
        $jobStructMock->method('getProject')->willReturn($projectStructMock);

        $source = '<pc id="source1" dataRefStart="source1">To identify who needs to complete the training, refer to the </pc><pc id="source2" dataRefStart="source2"><pc id="1u" type="fmt" subType="m:u">"</pc></pc><pc id="source3" dataRefStart="source3"><pc id="2u" type="fmt" subType="m:u">Learning – Compliance"</pc></pc>';
        $target = '<pc id="source1" dataRefStart="source1">Para identificar quién tiene que completar la formación, consulta el panel </pc><pc id="source2" dataRefStart="source2"><pc id="1u" type="fmt" subType="m:u">"Learning – Compliance"</pc></pc><pc id="source3" dataRefStart="source3"> (Formación: cumplimiento normativo)<pc id="2u" type="fmt" subType="m:u"></pc></pc>';

        $dataRefMap = array (
            'source3' => '&lt;w:hyperlink r:id="Ra99d4e0303384161"&gt;&lt;/w:hyperlink&gt;',
            'source1' => '&lt;w:r&gt;&lt;w:rPr&gt;&lt;w:b w:val="0"&gt;&lt;/w:b&gt;&lt;/w:rPr&gt;&lt;w:t&gt;&lt;/w:t&gt;&lt;/w:r&gt;',
            'source2' => '&lt;w:hyperlink r:id="R587ffffea7e34f42"&gt;&lt;/w:hyperlink&gt;',
        );

        /** @noinspection PhpParamsInspection */
        $xliffReplacerCallback = new XliffReplacerCallback(new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class)), 'en-US', 'es-ES', $jobStructMock, obtainTestDatabase());

        $this->assertFalse($xliffReplacerCallback->thereAreErrors(1, $source, $target, $dataRefMap), "Should not have errors");
    }

    /**
     * XLIFF 2.0 inline codes are compared by their original data, not by their disp/equiv display values:
     * a target tag whose disp is missing or localized is still the same tag. Before this, the export wrote the
     * source in place of these translations.
     *
     * @throws Exception
     */
    #[Test]
    #[DataProvider('xliff20DisplayValuesProvider')]
    public function testXliff20DisplayValuesDoNotCauseATagMismatch(string $source, string $target): void
    {
        $jobStructMock = $this->getStubBuilder(JobStruct::class)
            ->setConstructorArgs([
                [
                    'id' => '1',
                    'password' => 'password',
                    'source' => 'en-US',
                    'target' => 'it-IT'
                ]
            ])->getStub();

        $projectStructMock = $this->getStubBuilder(ProjectStruct::class)->getStub();
        $jobStructMock->method('getProject')->willReturn($projectStructMock);

        $dataRefMap = [
            'd1' => '{name}',
            'd2' => '&lt;b&gt;',
            'd3' => '&lt;/b&gt;',
        ];

        /** @noinspection PhpParamsInspection */
        $xliffReplacerCallback = new XliffReplacerCallback(new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class)), 'en-US', 'it-IT', $jobStructMock, obtainTestDatabase());

        $this->assertFalse($xliffReplacerCallback->thereAreErrors(1, $source, $target, $dataRefMap));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function xliff20DisplayValuesProvider(): array
    {
        return [
            'ph with the same disp' => [
                'Hello <ph id="1" dataRef="d1" disp="[Name]"/>!',
                'Ciao <ph id="1" dataRef="d1" disp="[Name]"/>!',
            ],
            'ph whose disp is missing in the target' => [
                'Hello <ph id="1" dataRef="d1" disp="[Name]"/>!',
                'Ciao <ph id="1" dataRef="d1"/>!',
            ],
            'ph whose disp is localized in the target' => [
                'Hello <ph id="1" dataRef="d1" disp="[Name]"/>!',
                'Ciao <ph id="1" dataRef="d1" disp="[Nome]"/>!',
            ],
            'identical pc with a slash in an attribute' => [
                'A <pc id="2" dataRefStart="d2" dataRefEnd="d3" dispStart="[b]" dispEnd="[/b]">bold</pc> word',
                'Una parola <pc id="2" dataRefStart="d2" dataRefEnd="d3" dispStart="[b]" dispEnd="[/b]">grassetto</pc>',
            ],
            'pc whose disp is missing in the target' => [
                'A <pc id="2" dataRefStart="d2" dataRefEnd="d3" dispStart="[b]" dispEnd="[/b]">bold</pc> word',
                'Una parola <pc id="2" dataRefStart="d2" dataRefEnd="d3">grassetto</pc>',
            ],
        ];
    }
}
