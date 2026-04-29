<?php

namespace unit\Utils;

use Exception;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectStruct;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
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
        $projectStructMock->method('getMetadataValue')->willReturn(false);

        $jobStructMock->method('getProject')->willReturn($projectStructMock);

        $segment = '<g id="1">Hello</g>';
        $target = '<g id="3">Hola</g>';

        /** @noinspection PhpParamsInspection */
        $xliffReplacerCallback = new XliffReplacerCallback(new FeatureSet(), 'en-EN', 'es-ES', $jobStructMock);

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
        $projectStructMock->method('getMetadataValue')->willReturn(false);

        $jobStructMock->method('getProject')->willReturn($projectStructMock);

        $segment = '<ph id="1"/> Hello';
        $target = '<ph id="3"/> Hola';

        /** @noinspection PhpParamsInspection */
        $xliffReplacerCallback = new XliffReplacerCallback(new FeatureSet(), 'en-EN', 'es-ES', $jobStructMock);

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
        $projectStructMock->method('getMetadataValue')->willReturn(false);

        $jobStructMock->method('getProject')->willReturn($projectStructMock);

        $source = '<pc id="source1" dataRefStart="source1">To identify who needs to complete the training, refer to the </pc><pc id="source2" dataRefStart="source2"><pc id="1u" type="fmt" subType="m:u">“</pc></pc><pc id="source3" dataRefStart="source3"><pc id="2u" type="fmt" subType="m:u">Learning – Compliance”</pc></pc>';
        $target = '<pc id="source1" dataRefStart="source1">Para identificar quién tiene que completar la formación, consulta el panel </pc><pc id="source2" dataRefStart="source2"><pc id="1u" type="fmt" subType="m:u">"Learning – Compliance"</pc></pc><pc id="source3" dataRefStart="source3"> (Formación: cumplimiento normativo)<pc id="2u" type="fmt" subType="m:u"></pc></pc>';

        $dataRefMap = array (
            'source3' => '&lt;w:hyperlink r:id="Ra99d4e0303384161"&gt;&lt;/w:hyperlink&gt;',
            'source1' => '&lt;w:r&gt;&lt;w:rPr&gt;&lt;w:b w:val="0"&gt;&lt;/w:b&gt;&lt;/w:rPr&gt;&lt;w:t&gt;&lt;/w:t&gt;&lt;/w:r&gt;',
            'source2' => '&lt;w:hyperlink r:id="R587ffffea7e34f42"&gt;&lt;/w:hyperlink&gt;',
        );

        /** @noinspection PhpParamsInspection */
        $xliffReplacerCallback = new XliffReplacerCallback(new FeatureSet(), 'en-US', 'es-ES', $jobStructMock);

        $this->assertFalse($xliffReplacerCallback->thereAreErrors(1, $source, $target, $dataRefMap), "Should not have errors");
    }
}