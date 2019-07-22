<?php

/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 20/10/2016
 * Time: 11:43
 */
class ProjectOptionSanitizerTest extends AbstractTest {

    function testSpeech2TextIsTrue () {
        $sanitizer = new ProjectOptionsSanitizer(['speech2text' => TRUE]);
        $result = $sanitizer->sanitize();
        $this->assertEquals( array('speech2text' => 1), $result ) ;
    }

    function testSpeech2TextIsNotTrue () {
        $sanitizer = new ProjectOptionsSanitizer(['speech2text' => FALSE]);
        $result = $sanitizer->sanitize();
        $this->assertEquals( array('speech2text' => 0), $result ) ;
    }

    function testLexiQaIsLeftTrueForValidLanguageCombinations () {
        $sanitizer = new ProjectOptionsSanitizer(['lexiqa' => TRUE]);
        $sanitizer->setLanguages('en-US', 'en-GB') ;
        $result = $sanitizer->sanitize();
        $this->assertEquals( array('lexiqa' => 1), $result ) ;
    }

    function testLexiQaIsSanitizedForOddLanguages () {
        $sanitizer = new ProjectOptionsSanitizer(['lexiqa' => TRUE]);
        $sanitizer->setLanguages('en-US', 'es-MX') ;
        $result = $sanitizer->sanitize();
        $this->assertEquals( array('lexiqa' => 1), $result ) ;
    }

    function testTagProjecttionIsTrueForValidLanguages () {
        $sanitizer = new ProjectOptionsSanitizer(['tag_projection' => TRUE]);
        $sanitizer->setLanguages('en-US', 'es-MX') ;
        $result = $sanitizer->sanitize();
        $this->assertEquals( array('tag_projection' => 1), $result ) ;
    }

    function testTagProjecttionIsSanitizedForOddLanguages () {
        $sanitizer = new ProjectOptionsSanitizer(['tag_projection' => TRUE]);
        $sanitizer->setLanguages('en-US', 'ru-RU') ;
        $result = $sanitizer->sanitize();
        $this->assertEquals( array('tag_projection' => 1), $result ) ;
    }

    function testSegmentationRuleIsSetWhenValid () {
        $sanitizer = new ProjectOptionsSanitizer(['segmentation_rule' => 'patent']);
        $result = $sanitizer->sanitize();
        $this->assertEquals( array('segmentation_rule' => 'patent'), $result ) ;
    }

    function testSegmentationRuleIsSanitized () {
        $sanitizer = new ProjectOptionsSanitizer(['segmentation_rule' => 'invalid']);
        $result = $sanitizer->sanitize();
        $this->assertEquals( array(), $result ) ;
    }

    function testUnknownKeysPassThrough() {
        $sanitizer = new ProjectOptionsSanitizer(array(
            'tag_projection' => TRUE,
            'lexiqa' => FALSE,
            'another_key_set_by_plugin' => 42
        ));
        $sanitizer->setLanguages('en-US', 'es-MX');

        $this->assertEquals( array(
            'tag_projection' => 1,
            'lexiqa' => 0,
            'another_key_set_by_plugin' => 42
        ), $sanitizer->sanitize() ) ;
    }

    function testLexiQaWorksWithRecursiveArrayObject() {
        $sanitizer = new ProjectOptionsSanitizer(['lexiqa' => TRUE]);
        $sanitizer->setLanguages('en-US', new RecursiveArrayObject( ['en-GB'] ) ) ;
        $result = $sanitizer->sanitize();
        $this->assertEquals( array('lexiqa' => 1), $result ) ;
    }


}