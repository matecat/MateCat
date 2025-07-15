<?php

use TestHelpers\AbstractTest;
use Utils\Tools\Utils;

class StripTagsPreservingHrefsTest extends AbstractTest {
    public function testCanStripTagsFromMdaString() {
        $string    = 'mda:key|¶|172f7f84-0245-485c-b2c6-aaef19bcf0f9';
        $stripTags = Utils::stripTagsPreservingHrefs( $string );
        $expected  = $string;

        $this->assertEquals( $expected, $stripTags );
    }

    public function testCanStripTagsFromString() {
        $string    = 'This is a simple test.';
        $stripTags = Utils::stripTagsPreservingHrefs( $string );
        $expected  = $string;

        $this->assertEquals( $expected, $stripTags );
    }

    public function testCanStripTagsFromHtml() {
        $html      = '<p>This is a simple test. <span>This is nested <a href="http://test.com">link</a></span></p>';
        $stripTags = Utils::stripTagsPreservingHrefs( $html );
        $expected  = 'This is a simple test. This is nested link(http://test.com)';

        $this->assertEquals( $expected, $stripTags );
    }

    public function testCanStripTagsFromJson() {
        $json      = '{"AdditionalInfo":"{}","MobileUsages":"[]","ReferenceLinks":"[]","Component":"[]","DynamicValueExample":"<span>Example</span>","KeyName":"<a href=\"https://text.com\" target=\"_blank\">Test</a><br>","Repo":"<a href =\"https://test2.com\" target=\"_blank\">test2</a>"}';
        $stripTags = Utils::stripTagsPreservingHrefs( $json );
        $expected  = '{"AdditionalInfo":"{}","MobileUsages":"[]","ReferenceLinks":"[]","Component":"[]","DynamicValueExample":"Example","KeyName":"Test(https://text.com)","Repo":"test2(https://test2.com)"}';

        $this->assertEquals( $expected, $stripTags );
    }

    public function testCanPreserveImgSrc() {
        $html      = '<p>This is a simple test. <img src="https://placehold.co/600x400" alt="Test"/></p>';
        $stripTags = Utils::stripTagsPreservingHrefs( $html );
        $expected  = 'This is a simple test. https://placehold.co/600x400';

        $this->assertEquals( $expected, $stripTags );
    }

    public function testCanPreserveMultipleImgSrc() {
        $html      = 'tags: Shift_completed_EWA, plugin.figma.2025-03-11.17-19-52
                    screenshot: <img src="https://d20j2y33fgycdj.cloudfront.net/uploads/screenshots/e291c5153be4b268f0471bbf8fccb7f4/screenshot-2b201adbc23c19e5.jpg">;
                    screenshot: <img src="https://d20j2y33fgycdj.cloudfront.net/uploads/screenshots/724d01f93ba1190dd73c3aafe7359a2c/screenshot-a24a7ac16eea3b9a.jpg">';

        $stripTags = Utils::stripTagsPreservingHrefs( $html );
        $expected  = 'tags: Shift_completed_EWA, plugin.figma.2025-03-11.17-19-52
                    screenshot: https://d20j2y33fgycdj.cloudfront.net/uploads/screenshots/e291c5153be4b268f0471bbf8fccb7f4/screenshot-2b201adbc23c19e5.jpg;
                    screenshot: https://d20j2y33fgycdj.cloudfront.net/uploads/screenshots/724d01f93ba1190dd73c3aafe7359a2c/screenshot-a24a7ac16eea3b9a.jpg';

        $this->assertEquals( $expected, $stripTags );
    }

    public function testWithMoreImages() {
        $html      = '<img src="https://placehold.co/600x400" alt="Test"/> test <img src="https://placehold.co/600x400" alt="Test"/> test <img src="https://placehold.co/600x400" alt="Test"/>';
        $stripTags = Utils::stripTagsPreservingHrefs( $html );
        $expected  = 'https://placehold.co/600x400 test https://placehold.co/600x400 test https://placehold.co/600x400';

        $this->assertEquals( $expected, $stripTags );
    }
}
