<?php
	
	/*************************************************************************************************/
	/***********************	PHP MAIN INITIALIZATION	**********************************************/
	/*************************************************************************************************/

    set_time_limit(0);

	include '../inc/config.inc.php';

	@INIT::obtain();
	include_once INIT::$UTILS_ROOT . '/Utils.php';
	include_once INIT::$UTILS_ROOT . '/Log.php';
	include_once INIT::$MODEL_ROOT . '/Database.class.php';
	include_once INIT::$MODEL_ROOT . '/queries.php';
	include_once INIT::$UTILS_ROOT . '/engines/engine.class.php';
	include_once INIT::$UTILS_ROOT . '/engines/tms.class.php';
	include_once INIT::$UTILS_ROOT . '/engines/mt.class.php';
	$db = Database::obtain ( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
	$db->debug = INIT::$DEBUG;
	$db->connect ();


	// turn off anything that could cause unwanted output buffering and turn on implicit flush
	@apache_setenv('no-gzip', 1);
	@ini_set('zlib.output_compression', 0);
	@ini_set('implicit_flush', 1);
	for ($i = 0; $i < ob_get_level(); $i++) { ob_end_flush(); }
	ob_implicit_flush(1);
?>

<!--*************************************************************************************************-->
<!--************************	PAGE HTML CODE	*****************************************************-->
<!--*************************************************************************************************-->
<!DOCTYPE html>
<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
		<title>Load Glossary</title>
        <style type="text/css">
            .mandatory { border: 2px solid red; }
            select, input { float: left; margin: 10px; }
            hr { clear:both; }
        </style>
        <script src="//ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js"></script>
        <script type="application/javascript">
            $( document ).ready( function () {

                var elem = [ $( '#mymemory_key' ), $('#trg'), $('input[type=file]') ];

                if ( $( '#loadingArea' ).text().trim().length != 0 ) {
                    $( '#loadingArea' ).html( "<h3>Complete!!</h3>" );
                    $('head').append('<meta http-equiv="refresh" content="3" >');
                }


                $( '#upload' ).on( 'click', function ( e ) {

                    $(elem).each(function(){
                        if ( this.prop( 'value' ).length == 0 ) {
                            e.preventDefault();
                            this.addClass( 'mandatory' );
                        } else {
                            this[0].form.submit();
                        }
                    });

                } );

            } );
        </script>
	</head>
	<body>
		<form action="" id="glossaryForm" method="post" enctype="multipart/form-data">
			<input type="text" name="loadGlossary" value="1" style="display:none" readonly />

			<select name="source_lang" id="src">
				<option value="en-US">English US</option>
				<option value="it-IT">Italian</option>
				<option class="separator" disabled="">---</option>
				<option value="af-ZA">Afrikaans</option>
				<option value="sq-AL">Albanian</option>
				<option value="ar-SA">Arabic</option>
				<option value="hy-AM">Armenian</option>
				<option value="eu-ES">Basque</option>
				<option value="bn-IN">Bengali</option>
				<option value="be-BY">Bielarus</option>
				<option value="bs-BA">Bosnian</option>
				<option value="br-FR">Breton</option>
				<option value="bg-BG">Bulgarian</option>
				<option value="ca-ES">Catalan</option>
				<option value="cb-PH">Cebuano</option>
				<option value="zh-CN">Chinese Simplified</option>
				<option value="zh-TW">Chinese Traditional</option>
				<option value="hr-HR">Croatian</option>
				<option value="cs-CZ">Czech</option>
				<option value="da-DK">Danish</option>
				<option value="nl-NL">Dutch</option>
				<option value="en-GB">English</option>
				<option value="en-US">English US</option>
				<option value="et-EE">Estonian</option>
				<option value="fo-FO">Faroese</option>
				<option value="fi-FI">Finnish</option>
				<option value="nl-BE">Flemish</option>
				<option value="fr-FR">French</option>
				<option value="gl-ES">Galician</option>
				<option value="ka-GE">Georgian</option>
				<option value="de-DE">German</option>
				<option value="el-GR">Greek</option>
				<option value="gu-IN">Gujarati</option>
				<option value="he-IL">Hebrew</option>
				<option value="hi-IN">Hindi</option>
				<option value="hu-HU">Hungarian</option>
				<option value="is-IS">Icelandic</option>
				<option value="id-ID">Indonesian</option>
				<option value="ga-IE">Irish Gaelic</option>
				<option value="it-IT">Italian</option>
				<option value="ja-JP">Japanese</option>
				<option value="kk-KZ">Kazakh</option>
				<option value="km-KH">Khmer</option>
				<option value="ko-KR">Korean</option>
				<option value="lv-LV">Latvian</option>
				<option value="lt-LT">Lithuanian</option>
				<option value="mk-MK">Macedonian</option>
				<option value="ms-MY">Malay</option>
				<option value="mt-MT">Maltese</option>
				<option value="mi-NZ">Maori</option>
				<option value="mn-MN">Mongolian</option>
				<option value="ne-NP">Nepali</option>
				<option value="nb-NO">Norwegian Bokmål</option>
				<option value="nn-NO">Norwegian Nynorsk</option>
				<option value="ur-PK">Pakistani</option>
				<option value="ps-PK">Pashto</option>
				<option value="fa-IR">Persian</option>
				<option value="pl-PL">Polish</option>
				<option value="pt-PT">Portuguese</option>
				<option value="pt-BR">Portuguese Brazil</option>
				<option value="fr-CA">Quebecois</option>
				<option value="qu-XN">Quechua</option>
				<option value="ro-RO">Romanian</option>
				<option value="ru-RU">Russian</option>
				<option value="sr-Latn-RS">Serbian Latin</option>
				<option value="sr-Cyrl-RS">Serbian Cyrillic</option>
				<option value="sk-SK">Slovak</option>
				<option value="sl-SI">Slovenian</option>
				<option value="es-ES">Spanish</option>
				<option value="es-MX">Spanish Latin America</option>
				<option value="sw-SZ">Swahili</option>
				<option value="sv-SE">Swedish</option>
				<option value="de-CH">Swiss German</option>
				<option value="tl-PH">Tagalog</option>
				<option value="ta-IN">Tamil</option>
				<option value="te-IN">Telugu</option>
				<option value="th-TH">Thai</option>
				<option value="tr-TR">Turkish</option>
				<option value="tk-TM">Turkmen</option>
				<option value="uk-UA">Ukrainian</option>
				<option value="vi-VN">Vietnamese</option>
				<option value="cy-GB">Welsh</option>
			</select>

			<select name="target_langs[]" id="trg" multiple="multiple" size="10">
				<option value="en-US">English US</option>
				<option value="it-IT">Italian</option>
				<option class="separator" disabled="">---</option>
				<option value="af-ZA">Afrikaans</option>
				<option value="sq-AL">Albanian</option>
				<option value="ar-SA">Arabic</option>
				<option value="hy-AM">Armenian</option>
				<option value="eu-ES">Basque</option>
				<option value="bn-IN">Bengali</option>
				<option value="be-BY">Bielarus</option>
				<option value="bs-BA">Bosnian</option>
				<option value="br-FR">Breton</option>
				<option value="bg-BG">Bulgarian</option>
				<option value="ca-ES">Catalan</option>
				<option value="cb-PH">Cebuano</option>
				<option value="zh-CN">Chinese Simplified</option>
				<option value="zh-TW">Chinese Traditional</option>
				<option value="hr-HR">Croatian</option>
				<option value="cs-CZ">Czech</option>
				<option value="da-DK">Danish</option>
				<option value="nl-NL">Dutch</option>
				<option value="en-GB">English</option>
				<option value="en-US">English US</option>
				<option value="et-EE">Estonian</option>
				<option value="fo-FO">Faroese</option>
				<option value="fi-FI">Finnish</option>
				<option value="nl-BE">Flemish</option>
				<option value="fr-FR">French</option>
				<option value="gl-ES">Galician</option>
				<option value="ka-GE">Georgian</option>
				<option value="de-DE">German</option>
				<option value="el-GR">Greek</option>
				<option value="gu-IN">Gujarati</option>
				<option value="he-IL">Hebrew</option>
				<option value="hi-IN">Hindi</option>
				<option value="hu-HU">Hungarian</option>
				<option value="is-IS">Icelandic</option>
				<option value="id-ID">Indonesian</option>
				<option value="ga-IE">Irish Gaelic</option>
				<option value="it-IT">Italian</option>
				<option value="ja-JP">Japanese</option>
				<option value="kk-KZ">Kazakh</option>
				<option value="km-KH">Khmer</option>
				<option value="ko-KR">Korean</option>
				<option value="lv-LV">Latvian</option>
				<option value="lt-LT">Lithuanian</option>
				<option value="mk-MK">Macedonian</option>
				<option value="ms-MY">Malay</option>
				<option value="mt-MT">Maltese</option>
				<option value="mi-NZ">Maori</option>
				<option value="mn-MN">Mongolian</option>
				<option value="ne-NP">Nepali</option>
				<option value="nb-NO">Norwegian Bokmål</option>
				<option value="nn-NO">Norwegian Nynorsk</option>
				<option value="ur-PK">Pakistani</option>
				<option value="ps-PK">Pashto</option>
				<option value="fa-IR">Persian</option>
				<option value="pl-PL">Polish</option>
				<option value="pt-PT">Portuguese</option>
				<option value="pt-BR">Portuguese Brazil</option>
				<option value="fr-CA">Quebecois</option>
				<option value="qu-XN">Quechua</option>
				<option value="ro-RO">Romanian</option>
				<option value="ru-RU">Russian</option>
				<option value="sr-Latn-RS">Serbian Latin</option>
				<option value="sr-Cyrl-RS">Serbian Cyrillic</option>
				<option value="sk-SK">Slovak</option>
				<option value="sl-SI">Slovenian</option>
				<option value="es-ES">Spanish</option>
				<option value="es-MX">Spanish Latin America</option>
				<option value="sw-SZ">Swahili</option>
				<option value="sv-SE">Swedish</option>
				<option value="de-CH">Swiss German</option>
				<option value="tl-PH">Tagalog</option>
				<option value="ta-IN">Tamil</option>
				<option value="te-IN">Telugu</option>
				<option value="th-TH">Thai</option>
				<option value="tr-TR">Turkish</option>
				<option value="tk-TM">Turkmen</option>
				<option value="uk-UA">Ukrainian</option>
				<option value="vi-VN">Vietnamese</option>
				<option value="cy-GB">Welsh</option>
			</select>

			<input type="file" name="glossary" />

			<input type="text" name="mymemory_key" id="mymemory_key" placeholder="MyMemory API key" />

			<input type="button" id="upload" value="Upload" />
		</form>

        <hr/>

        <div id="loadingArea" style="height: 650px; overflow: auto;">
		<?php
			/*************************************************************************************************/
			/***********************	PHP UPLOAD AND PARSE GLOSSARY	**************************************/
			/*************************************************************************************************/

			$input = new ArrayObject( array(	"loadGlossary" => ( isset( $_POST[ "loadGlossary" ] ) && !empty( $_POST[ "loadGlossary" ] ) && ( $_POST[ "loadGlossary" ] == 1 ) ),
												"source" => $_POST[ "source_lang" ],
												"targets" => $_POST[ "target_langs" ],
												"myMemoryKey" => $_POST[ "mymemory_key" ],
												"glossaryName" => $_FILES[ "glossary" ][ "name" ]	)	);

			if ( $input[ "loadGlossary" ] )
			{
				$uploadGlossaryResult = uploadGlossary( $input );
				
				echo $uploadGlossaryResult[ "message" ];
				flush();

				if ( $uploadGlossaryResult[ "result" ] == 1 )
				{
					parseGlossary( $input, false, true );
				}
			}




			/*************************************************************************************************/
			/***********************	PHP SUPPORT FUNCTIONS ************************************************/
			/*************************************************************************************************/

			function uploadGlossary( $input )
			{
				if ( in_array( $input[ "source" ],  $input[ "targets" ] ) )
				{
					return array( "result" => 0, "message" => "Error: Source lang is equal to one of targets" );
				}


				try
				{
					$uploadFile = new Upload();
					$uploadResult = $uploadFile->uploadFiles( $_FILES );
					$input[ "glossaryURI" ] = $uploadResult->glossary->file_path;
				} 

				catch( Exception $e )
				{
					$errorData = explode( "->", $e->getMessage() );
					return array( "result" => 0, "message" => "Error: " . trim( $errorData[1] ) );
				}
			
				return array( "result" => 1, "message" => "Glossary '" . $input[ "glossaryName" ] . "' successfully uploaded.<br/><br/>" );
			}


			function parseGlossary( $input, $test, $skip )
			{
				$config = TMS::getConfigStruct();
				$config[ 'source_lang' ] = $input['source'];
				$config[ 'email' ]       = "demo@matecat.com";
				$config[ 'get_mt' ]      = false;
				$config[ 'num_result' ]  = null;
				$config[ 'isGlossary' ]  = true;


				$db = Database::obtain();
				$result = $db->query_first( "SELECT username FROM translators WHERE mymemory_api_key = '" . $db->escape( $input[ "myMemoryKey" ] ) . "'" );

				$config[ 'id_user' ] = $result[ 'username' ];

				if ( empty( $config[ 'id_user' ] ) )
				{
					echo "Error: No translator found associated to MyMemory Key: " . $input[ "myMemoryKey" ];
					return;
				}


				echo "Begin importing the glossary into MyMemory. It might take a loooooong time. Please wait...<br/><br/><pre>";

				foreach( $input[ "targets" ] as $target )
				{
					$config[ 'target_lang' ] = $target;

					$fObject = new SplFileObject( $input[ "glossaryURI" ] );
					$fObject->setFlags( SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE );
					$fObject->setCsvControl( ",", '"' );


					$tms = new TMS( 1 );

					foreach ( $fObject as $k => $row ) {

						if ( $test || $skip ) {
						    if ( !isset( $row[ 1 ] ) || empty( $row[ 1 ] ) ) {
						        echo "<br/>*********************";
						        echo "***** Failed at Row: ";
						        print_r( ( $fObject->key() + 1 ) . "<br/>" );
						        echo $row[ 0 ] . "<br/>";
						        echo "*********************<br/>";
							flush();
						        sleep(1);
                                                        continue;
						    }
						}

						$config[ 'segment' ]     = $row[ 0 ];
						$config[ 'translation' ] = $row[ 1 ];
						$config[ 'tnote' ]       = ( isset( $row[ 2 ] ) ? $row[ 2 ] : null );

						if ( !$test ) {
							$tms->set( $config );
						    echo "SET<br/>";
						}

						echo  "[ " . $config['source_lang'] . "|" . $config['target_lang'] . " ]: " . $config['segment'] . " --> " . $config['translation'] . "<br/>";
						flush();
					}
				}			
				
				echo "</pre>";
			}
		?>

        </div>
        <!-- FINAL PART OF THE HTML CODE -->
    </body>
</html>
