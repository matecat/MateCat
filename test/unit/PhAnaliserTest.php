<?php

use Ph\PhAnaliser;

class PhAnaliserTest extends AbstractTest {

    /**
     * @test
     */
    public function removeDoublePercent() {

        // JSU= === %%

        $source      = "it-IT";
        $target      = "en-US";
        $segment     = 'Lorem ipsum dolor <ph id="mtc_1" equiv-text="base64:JSU="/>';
        $translation = 'Lorem ipsum dolor facium <ph id="mtc_1" equiv-text="base64:JSU="/> <ph id="mtc_1" equiv-text="base64:JSU="/>';

        $phAnaliser = new PhAnaliser( $source, $target, $segment, $translation );

        $this->assertEquals( 'Lorem ipsum dolor %%', $phAnaliser->getSegment()->getAfter() );
        $this->assertEquals( 'Lorem ipsum dolor facium %% %%', $phAnaliser->getTranslation()->getAfter() );

        $translation = '&lt;ph id="mtc_1" equiv-text="base64:JTEkcw=="/&gt;: For groups of &lt;ph id="mtc_2" equiv-text="base64:JTIkcw=="/&gt; to &lt;ph id="mtc_3" equiv-text="base64:JTMkcw=="/&gt; guests, guests will receive a &lt;ph id="mtc_4" equiv-text="base64:JTQkcw=="/&gt; percent discount &lt;ph id="mtc_5" equiv-text="base64:JSU="/&gt;';

        $phAnaliser = new PhAnaliser( $source, $target, $segment, $translation );

        $this->assertEquals( '&lt;ph id="mtc_1" equiv-text="base64:JTEkcw=="/&gt;: For groups of &lt;ph id="mtc_2" equiv-text="base64:JTIkcw=="/&gt; to &lt;ph id="mtc_3" equiv-text="base64:JTMkcw=="/&gt; guests, guests will receive a &lt;ph id="mtc_4" equiv-text="base64:JTQkcw=="/&gt; percent discount %%', $phAnaliser->getTranslation()->getAfter() );
    }

    /**
     * @test
     */
    public function removePercentIge() {

        // JWk= === %i

        $source      = "de-DE";
        $target      = "en-US";
        $segment     = 'Lorem ipsum dolor 100 <ph id="mtc_1" equiv-text="base64:JWk="/>ge';
        $translation = 'Lorem ipsum dolor facium 100 <ph id="mtc_1" equiv-text="base64:JWk="/>ge';

        $phAnaliser = new PhAnaliser( $source, $target, $segment, $translation );

        $this->assertEquals( 'Lorem ipsum dolor 100 %ige', $phAnaliser->getSegment()->getAfter() );
        $this->assertEquals( 'Lorem ipsum dolor facium 100 %ige', $phAnaliser->getTranslation()->getAfter() );

        $source      = "en-US";
        $target      = "de-DE";
        $segment     = 'Lorem ipsum dolor 100 <ph id="mtc_1" equiv-text="base64:JWk="/>ge';
        $translation = 'Lorem ipsum dolor facium 100 <ph id="mtc_1" equiv-text="base64:JWk="/>ge';

        $phAnaliser = new PhAnaliser( $source, $target, $segment, $translation );

        $this->assertEquals( 'Lorem ipsum dolor 100 %ige', $phAnaliser->getSegment()->getAfter() );
        $this->assertEquals( 'Lorem ipsum dolor facium 100 %ige', $phAnaliser->getTranslation()->getAfter() );
    }

    /**
     * @test
     */
    public function doNotRemovePercentIge() {

        // JWk= === %i

        $source      = "it-IT";
        $target      = "en-US";
        $segment     = 'Lorem ipsum dolor 100 <ph id="mtc_1" equiv-text="base64:JWk="/>ge';
        $translation = 'Lorem ipsum dolor facium 100 <ph id="mtc_1" equiv-text="base64:JWk="/>ge';

        $phAnaliser = new PhAnaliser( $source, $target, $segment, $translation );

        $this->assertEquals( 'Lorem ipsum dolor 100 <ph id="mtc_1" equiv-text="base64:JWk="/>ge', $phAnaliser->getSegment()->getAfter() );
        $this->assertEquals( 'Lorem ipsum dolor facium 100 <ph id="mtc_1" equiv-text="base64:JWk="/>ge', $phAnaliser->getTranslation()->getAfter() );

        $source      = "de-DE";
        $target      = "en-US";
        $segment     = 'Lorem ipsum dolor 100 <ph id="mtc_1" equiv-text="base64:JWk="/> another text.';
        $translation = 'Lorem ipsum dolor facium 100 <ph id="mtc_1" equiv-text="base64:JWk="/> another text.';

        $phAnaliser = new PhAnaliser( $source, $target, $segment, $translation );

        $this->assertEquals( 'Lorem ipsum dolor 100 <ph id="mtc_1" equiv-text="base64:JWk="/> another text.', $phAnaliser->getSegment()->getAfter() );
        $this->assertEquals( 'Lorem ipsum dolor facium 100 <ph id="mtc_1" equiv-text="base64:JWk="/> another text.', $phAnaliser->getTranslation()->getAfter() );
    }

    /**
     * @test
     */
    public function removePercentBan() {

        // JS1iYW4= === %%

        $source      = "az-AZ";
        $target      = "en-US";
        $segment     = 'Lorem ipsum dolor 100<ph id="mtc_1" equiv-text="base64:JS1iYW4"/>';
        $translation = 'Lorem ipsum dolor facium 100<ph id="mtc_1" equiv-text="base64:JS1iYW4"/>';

        $phAnaliser = new PhAnaliser( $source, $target, $segment, $translation );

        $this->assertEquals( 'Lorem ipsum dolor 100%-ban', $phAnaliser->getSegment()->getAfter() );
        $this->assertEquals( 'Lorem ipsum dolor facium 100%-ban', $phAnaliser->getTranslation()->getAfter() );

        $source      = "en-US";
        $target      = "az-AZ";
        $segment     = 'Lorem ipsum dolor facium 100<ph id="mtc_1" equiv-text="base64:JS1iYW4"/>';
        $translation = 'Lorem ipsum dolor 100<ph id="mtc_1" equiv-text="base64:JS1iYW4"/>';

        $phAnaliser = new PhAnaliser( $source, $target, $segment, $translation );

        $this->assertEquals( 'Lorem ipsum dolor facium 100%-ban', $phAnaliser->getSegment()->getAfter() );
        $this->assertEquals( 'Lorem ipsum dolor 100%-ban', $phAnaliser->getTranslation()->getAfter() );
    }

    /**
     * @test
     */
    public function doNotRemovePercentBan() {

        // JS1iYW4= === %%

        $source      = "it-IT";
        $target      = "en-US";
        $segment     = 'Lorem ipsum dolor 100<ph id="mtc_1" equiv-text="base64:JS1iYW4"/>';
        $translation = 'Lorem ipsum dolor facium 100<ph id="mtc_1" equiv-text="base64:JS1iYW4"/>';

        $phAnaliser = new PhAnaliser( $source, $target, $segment, $translation );

        $this->assertEquals( 'Lorem ipsum dolor 100<ph id="mtc_1" equiv-text="base64:JS1iYW4"/>', $phAnaliser->getSegment()->getAfter() );
        $this->assertEquals( 'Lorem ipsum dolor facium 100<ph id="mtc_1" equiv-text="base64:JS1iYW4"/>', $phAnaliser->getTranslation()->getAfter() );
    }
}