<?php

class PhAnaliserTest {

    public function fdsfds() {
        $source      = "it-IT";
        $target      = "en-US";
        $segment     = 'Lorem ipsum dolor <ph id="mtc_1" equiv-text="base64:JSU="/>';
        $translation = 'Lorem ipsum dolor facium <ph id="mtc_1" equiv-text="base64:JSU="/>';

        $phAnaliser = new PhAnaliser( $source, $target, $segment, $translation );



    }
}