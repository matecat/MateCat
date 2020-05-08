<?php

if( !@include_once __DIR__.'/../../inc/Bootstrap.php')
    header("Location: configMissing");

Bootstrap::start();
include_once __DIR__ . "/../../lib/Utils/xliff.parser.1.3.class.php";

error_reporting(E_ERROR);

$file = "<xliff version='1.2'
       xmlns='urn:oasis:names:tc:xliff:document:1.2'>
    <file original='hello.txt' source-language='en' target-language='fr'
          datatype='plaintext'>
        <body>";

//$file .= "<trans-unit id='1'>
//            <source xml:lang=\"en-en\">Hello world, what time is it ? Where are you from ?<x id=\"1\"/></source>
//            <seg-source>
//                <mrk mid=\"0\" mtype=\"seg\">Hello world, </mrk>
//                <mrk mid=\"1\" mtype=\"seg\">what time is it ? </mrk>
//                <mrk mid=\"2\" mtype=\"seg\">Where are you from ?<x id=\"1\"/></mrk>
//            </seg-source>
//            <target xml:lang=\"it-it\">
//                <mrk mid=\"0\" mtype=\"seg\">Bonjour le monde, </mrk>
//                <mrk mid=\"1\" mtype=\"seg\">quelle heure il est ? </mrk>
//                <mrk mid=\"2\" mtype=\"seg\">d ou etes vous ?<x id=\"1\"/></mrk>
//            </target>
//        </trans-unit>";
$file .= "<trans-unit id=\"NFDBB2FA9-tu1\" xml:space=\"preserve\">
<source xml:lang=\"en-US\"><g id=\"1\">SL: Re: A credible source in uncertain times</g></source>
<seg-source><mrk mid=\"0\" mtype=\"seg\">With over 600 journalists across 50 countries, the Financial Times is best positioned to provide you and your team with accurate global analysis first, during these unprecedented times. </mrk></seg-source>
<target xml:lang=\"it-IT\"><mrk mid=\"0\" mtype=\"seg\">With over 600 journalists across 50 countries, the Financial Times is best positioned to provide you and your team with accurate global analysis first, during these unprecedented times. </mrk></target>
</trans-unit>";


 $file .=       "</body>
    </file>
</xliff>";

$x = new Xliff_Parser();
$a = $x->Xliff2Array($file);



//$refMethod = new ReflectionMethod( 'Xliff_Parser', 'getSegSource' );
//$refMethod->setAccessible(true);
//
//$xliff = [];
//$refMethod->invokeArgs( new Xliff_Parser(), [ &$xliff, 0, 0, $x ] );
//
//var_export($xliff);

