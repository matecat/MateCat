<?php

class StripTagsPreservingHrefsTest extends PHPUnit_Framework_TestCase
{
    public function testCanStripTagsFromHtml()
    {
        $html = '<p>This is a simple test. <span>This is nested <a href="http://test.com">link</a></span></p>';
        $stripTags = Utils::stripTagsPreservingHrefs($html);
        $expected = 'This is a simple test. This is nested http://test.com';

        $this->assertEquals($expected, $stripTags);
    }

    public function testCanStripTagsFromJson()
    {
        $json = '{"AdditionalInfo":"{}","MobileUsages":"[]","ReferenceLinks":"[]","Component":"[]","DynamicValueExample":"<span>Example</span>","KeyName":"<a href=\"https://text.com\" target=\"_blank\">Test</a><br>","Repo":"<a href =\"https://test2.com\" target=\"_blank\">test2</a>"}';
        $stripTags = Utils::stripTagsPreservingHrefs($json);
        $expected = '{"AdditionalInfo":"{}","MobileUsages":"[]","ReferenceLinks":"[]","Component":"[]","DynamicValueExample":"Example","KeyName":"https://text.com","Repo":"https://test2.com"}';

        $this->assertEquals($expected, $stripTags);
    }
}