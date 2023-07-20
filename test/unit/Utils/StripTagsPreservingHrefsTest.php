<?php

class StripTagsPreservingHrefsTest extends PHPUnit_Framework_TestCase
{
    public function testCanStripTagsFromString()
    {
        $string = 'This is a simple test.';
        $stripTags = Utils::stripTagsPreservingHrefs($string);
        $expected = $string;

        $this->assertEquals($expected, $stripTags);
    }

    public function testCanStripTagsFromHtml()
    {
        $html = '<p>This is a simple test. <span>This is nested <a href="http://test.com">link</a></span></p>';
        $stripTags = Utils::stripTagsPreservingHrefs($html);
        $expected = 'This is a simple test. This is nested link(http://test.com)';

        $this->assertEquals($expected, $stripTags);
    }

    public function testCanStripTagsFromJson()
    {
        $json = '{"AdditionalInfo":"{}","MobileUsages":"[]","ReferenceLinks":"[]","Component":"[]","DynamicValueExample":"<span>Example</span>","KeyName":"<a href=\"https://text.com\" target=\"_blank\">Test</a><br>","Repo":"<a href =\"https://test2.com\" target=\"_blank\">test2</a>"}';
        $stripTags = Utils::stripTagsPreservingHrefs($json);
        $expected = '{"AdditionalInfo":"{}","MobileUsages":"[]","ReferenceLinks":"[]","Component":"[]","DynamicValueExample":"Example","KeyName":"Test(https://text.com)","Repo":"test2(https://test2.com)"}';

        $this->assertEquals($expected, $stripTags);
    }
}