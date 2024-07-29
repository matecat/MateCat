<?php

use LQA\BxExG\Validator;
use LQA\QA;
use TestHelpers\AbstractTest;


class BxExGValidatorTest extends AbstractTest {

    /**
     * @test
     */
    public function noErrors() {
        $source = '<bx ="23"/><g id="1">Ciao</g> questa è una stringa';
        $target = '<bx ="23"/><g id="1">Hi</g> this is a string';

        $qa        = new QA( $source, $target );
        $validator = new Validator( $qa );

        $this->assertEmpty( $validator->validate() );
    }

    /**
     * @test
     */
    public function nestedBxInTargetNotInSource() {
        $source = '<bx ="23"/><g id="1">Ciao</g> questa è una stringa';
        $target = '<g id="1"><bx ="23"/>Hi</g> this is a string';

        $qa        = new QA( $source, $target );
        $validator = new Validator( $qa );

        $this->assertCount( 1, $validator->validate() );
        $this->assertEquals( 1300, $validator->validate()[ 0 ] );
    }

    /**
     * @test
     */
    public function nestedBxInSourceNotInTarget() {
        $source = '<g id="1"><bx ="23"/>Ciao</g> questa è una stringa';
        $target = '<bx ="23"/><g id="1">Hi</g> this is a string';

        $qa        = new QA( $source, $target );
        $validator = new Validator( $qa );

        $this->assertCount( 1, $validator->validate() );
        $this->assertEquals( 1301, $validator->validate()[ 0 ] );
    }

    /**
     * @test
     */
    public function nestedBxInTargetNotInSourceWithNestedStrings() {
        $source = '<bx ="23"/><g id="1"><g id="2"><g id="3">Ciao</g> questa è una stringa</g></g>';
        $target = '<g id="1"><g id="2"><g id="3"><bx ="23"/>Hi</g> this is a string</g></g>';

        $qa        = new QA( $source, $target );
        $validator = new Validator( $qa );

        $this->assertCount( 1, $validator->validate() );
        $this->assertEquals( 1300, $validator->validate()[ 0 ] );
    }

    /**
     * @test
     */
    public function noErrorsIfGArePlacedInDifferentOrder() {

        $source = 'Click <g id="1">si puà connettere un encoder magnetico</g> per la saldatura a quota';
        $target = '<g id="1">Se puede conectar un codificador magnético</g> a WELDAUTO para soldadura en altura. ';

        $qa        = new QA( $source, $target );
        $validator = new Validator( $qa );

        $this->assertEmpty( $validator->validate() );
    }

    /**
     * @test
     */
    public function noErrorsIfGArePlacedInDifferentPositions() {

        $source = '<g id="1">Grafik 2.3 – </g>2021 yılı finansal <g id="2">fizibilite ve planlanan, bileşen 2.3</g>';
        $target = 'Graph 2.3 - Financial appraisal vs. planned for 2021, component 2.3<g id="1"></g><g id="2"></g> ';

        $qa        = new QA( $source, $target );
        $validator = new Validator( $qa );

        $this->assertEmpty( $validator->validate() );

        // test string with tags in reverse order
        $source = 'Graph 2.3 - Financial appraisal vs. planned for 2021, component 2.3<g id="1"></g><g id="2"></g> ';
        $target = '<g id="1">Grafik 2.3 – </g>2021 yılı finansal <g id="2">fizibilite ve planlanan, bileşen 2.3</g>';

        $qa        = new QA( $source, $target );
        $validator = new Validator( $qa );

        $this->assertEmpty( $validator->validate() );
    }

    /**
     * @test
     */
    public function noErrorsIfGArePlacedInEscapedTags() {

        $source = '&lt;div&gt; <g id="1">La mamma è andata a fare la spesa</g> &lt;/div&gt;';
        $target = '&lt;div&gt; La mamma è andata a fare <g id="1">la spesa</g> &lt;/div&gt;';

        $qa        = new QA( $source, $target );
        $validator = new Validator( $qa );

        $this->assertEmpty( $validator->validate() );
    }

    /**
     * @test
     */
    public function mismatchCountBetweenSourceAndTarget() {
        $source = '<g id="1"><bx ="23"/>Ciao</g> questa è una stringa<g id="2"></g>';
        $target = '<g id="1"><bx ="23"/>Hi</g> this is a string';

        $qa        = new QA( $source, $target );
        $validator = new Validator( $qa );

        $this->assertCount( 1, $validator->validate() );
        $this->assertEquals( 1302, $validator->validate()[ 0 ] );
    }

    /**
     * @test
     */
    public function noBxOrExTags() {
        $source = '<g id="1"><g id="2"><g id="3">Ciao</g> questa è una stringa</g></g><g id="5"></g>';
        $target = '<g id="1"><g id="2"><g id="3">Hi</g> this is a string</g></g><g id="4"></g>';

        $qa        = new QA( $source, $target );
        $validator = new Validator( $qa );

        $this->assertEmpty( $validator->validate() );
    }
}


