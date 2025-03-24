<?php

use TestHelpers\AbstractTest;

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
        $expected  = "This is a simple test. This is nested link(<a href='http://test.com'>http://test.com</a>)";

        $this->assertEquals( $expected, $stripTags );
    }

    public function testCanStripTagsFromJson() {
        $json      = '{"AdditionalInfo":"{}","MobileUsages":"[]","ReferenceLinks":"[]","Component":"[]","DynamicValueExample":"<span>Example</span>","KeyName":"<a href=\"https://text.com\" target=\"_blank\">Test</a><br>","Repo":"<a href =\"https://test2.com\" target=\"_blank\">test2</a>"}';
        $stripTags = Utils::stripTagsPreservingHrefs( $json );
        $expected  = '{"AdditionalInfo":"{}","MobileUsages":"[]","ReferenceLinks":"[]","Component":"[]","DynamicValueExample":"Example","KeyName":"Test(<a href=\'https://text.com\'>https://text.com</a>)","Repo":"test2(<a href=\'https://test2.com\'>https://test2.com</a>)"}';

        $this->assertEquals( $expected, $stripTags );
    }

    public function testCanStripTagsFromMarkdown() {
        $html      = 'This is a simple test. This is nested [link](http://test.com)';
        $stripTags = Utils::stripTagsPreservingHrefs( $html );
        $expected  = "This is a simple test. This is nested link(<a href='http://test.com'>http://test.com</a>)";

        $this->assertEquals( $expected, $stripTags );
    }
}