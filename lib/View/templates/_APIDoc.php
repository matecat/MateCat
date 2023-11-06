<?php
require_once '../../inc/Bootstrap.php';
Bootstrap::start();

$count = 0;
foreach ( INIT::$SUPPORTED_FILE_TYPES as $key => $value ) {
    $count += count( $value );
}

$nr_supoported_files = $count;

$max_file_size_in_MB = INIT::$MAX_UPLOAD_FILE_SIZE / ( 1024 * 1024 );
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>API - Matecat</title>
    <link rel="stylesheet" type="text/css" href="/public/api/dist/lib/swagger-ui.css">
    <link rel="icon" type="image/png" sizes="32x32" href="/public/img/meta/favicon-32x32.svg"/>
    <link rel="icon" type="image/png" sizes="16x16" href="/public/img/meta/favicon-16x16.svg"/>

    <script>
        /*<![CDATA[*/
        config = {};
        config.swagger_host = '<?php echo $_SERVER[ 'HTTP_HOST' ] ?>';
        /*]]>*/
    </script>

    <script src='/public/api/dist/lib/jquery-3.3.1.min.js' type='text/javascript'></script>
    <script src="/public/api/dist/lib/swagger-ui-bundle.js"></script>
    <script src="/public/api/dist/lib/swagger-ui-standalone-preset.js"></script>

    <script src='/public/api/swagger-source.js' type='text/javascript'></script>
    <?php

    $reflect  = new ReflectionClass( 'CustomPage' );
    $instance = $reflect->newInstanceArgs( [] );

    $featureSet = new FeatureSet();
    $featureSet->loadFromUserEmail( $instance->getUser()->email );
    $appendJS = $featureSet->filter( 'overloadAPIDocs', [] );
    echo implode( "\n", $appendJS );

    ?>
    <script type="application/javascript">
        /*<![CDATA[*/

        // add active class to menu
        function setActivesMenuLink( element ) {
            if ( !$( element ).hasClass( 'active' ) ) {
                location.hash = element.hash;
                $( ".menu a" ).removeClass( 'active' );
                $( element ).addClass( 'active' );
            }
        }

        function hideSwaggerElements() {
            var swaggerElements = $( ".is-open" );
            swaggerElements.each( function () {
                //close tags
                $( this ).children( 'h4' ).click();
            } );
        }

        function generateSwaggerMenu() {

            var tags = $( '.opblock-tag' );
            var elements = $();
            tags.each( function () {
                var name = $( this ).prop( 'id' ).replace( 'operations-tag-', '' );
                elements = elements.add( '<li><a id="' + name + '" href="#' + name + '">' + name.replace( '_', ' ' ) + '</a></li>' );
            } );

            $( '#menuElements' ).prepend( elements );

        }

        $( document ).ready( function () {

            var hash = location.hash;

            // Build a system
            window.swaggerUi = SwaggerUIBundle( {
                url: spec,
                spec: spec,
                dom_id: '#swagger-ui-container',
                supportedSubmitMethods: ['get',
                    'post',
                    'put',
                    'delete'],
                docExpansion: 'none',
                deepLinking: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIStandalonePreset
                ],
                plugins: [
                    SwaggerUIBundle.plugins.DownloadUrl
                ],
                //layout: "StandaloneLayout"

            } );

            generateSwaggerMenu();

            // smooth scrolling for normal links
            $( '#menuElements li a' ).click( function () {

                //menu href
                var anchorName = this.hash.slice( 1 );

                //exists an element with anchor in the page?
                var domAnchorList = $( '[name="' + anchorName + '"]' );

                //exist a swagger tag element?
                var swaggerTagList = $( "#operations-tag-" + anchorName );

                hideSwaggerElements();

                var target = null;
                if ( domAnchorList.length ) {
                    target = domAnchorList;
                } else if ( swaggerTagList.length ) {
                    target = swaggerTagList;
                    //open Swagger Menu
                    $( target[0] ).trigger( 'click' );
                }

                setActivesMenuLink( this );
                $( 'html,body' ).animate( {
                    scrollTop: target.offset().top
                }, 500 );

                return false;

            } );
            $( '#menuElements li a[href="'+ hash.replace('/', '') +'"]' ).trigger('click');
        } );

        /*]]>*/
    </script>
</head>
<body class="api swagger-section">


<header>
    <div class="wrapper ">

        <div class="logo-menu">
            <a href="/" class="logo"></a>
        </div> <!-- .logo-menu -->
    </div>
</header>
<div id="contentBox" class="wrapper">
    <div class="colsx">
        <a href="#top"><span class="logosmall"></span></a>
        <h1>API</h1>
        <ul id="menuElements" class="menu">

            <!-- Swagger anchors -->

            <li><a href="#file-format">Supported file format</a></li>
            <li><a href="#languages">Supported languages</a></li>
            <li><a href="#subjects">Supported subjects</a></li>
            <li><a href="#seg-rules">Supported segmentation rules</a></li>
        </ul>
    </div>
    <a name="top" class="top"></a>
    <div class="coldx">

        <div class="block block-api block-swagger">
            <div id="swagger-ui-container" class="swagger-ui-wrap">
                <div id="message-bar" class="swagger-ui-wrap" data-sw-translate>&nbsp;</div>
            </div>
            <a class="gototop" href="#top">Go to top</a>
        </div>

        <div class="block block-api">
            <h3 name="file-format" class="method-title">Supported file formats</h3>
            <table class="tablestats fileformat" width="100%" border="0" cellspacing="0" cellpadding="0">

                <thead>
                <tr>
                    <th width="40%">Office</th>
                    <th width="15%">Web</th>
                    <th width="15%">Scanned Files</th>
                    <th width="15%">Interchange Formats</th>
                    <th width="15%">Desktop Publishing</th>
                    <th width="15%">Localization</th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td>
                        <ul class="office">
                            <li><span class="extdoc">doc</span></li>
                            <li><span class="extdoc">pages</span></li>
                            <li><span class="extdoc">dot</span></li>
                            <li><span class="extdoc">docx</span></li>
                            <li><span class="extdoc">dotx</span></li>
                            <li><span class="extdoc">docm</span></li>
                            <li><span class="extdoc">dotm</span></li>
                            <li><span class="extdoc">rtf</span></li>
                            <li><span class="extdoc">odt</span></li>
                            <li><span class="extdoc">ott</span></li>
                            <li><span class="extpdf">pdf</span></li>
                            <li><span class="extxls">numbers</span></li>
                            <li><span class="exttxt">txt</span></li>
                            <li><span class="extxls">xls</span></li>
                            <li><span class="extxls">xlt</span></li>
                            <li><span class="extxls">xlsm</span></li>
                            <li><span class="extxls">xlsx</span></li>
                            <li><span class="extxls">xltx</span></li>
                            <li><span class="extxls">xltm</span></li>
                            <li><span class="extxls">ods</span></li>
                            <li><span class="extxls">ots</span></li>
                            <li><span class="extxls">tsv</span></li>
                            <li><span class="extppt">key</span></li>
                            <li><span class="extppt">ppt</span></li>
                            <li><span class="extppt">pps</span></li>
                            <li><span class="extppt">pot</span></li>
                            <li><span class="extppt">pptx</span></li>
                            <li><span class="extppt">pptm</span></li>
                            <li><span class="extppt">ppsx</span></li>
                            <li><span class="extppt">ppsm</span></li>
                            <li><span class="extppt">potx</span></li>
                            <li><span class="extppt">potm</span></li>
                            <li><span class="extppt">odp</span></li>
                            <li><span class="extppt">otp</span></li>
                            <li><span class="extxml">xml</span></li>
                            <li><span class="extzip">zip</span></li>
                        </ul>
                    </td>
                    <td>
                        <ul>
                            <li><span class="exthtm">htm</span></li>
                            <li><span class="exthtm">html</span></li>
                            <li><span class="exthtm">xhtml</span></li>
                            <li><span class="extxml">xml</span></li>
                            <li><span class="extxml">dtd</span></li>
                            <li><span class="extxml">json</span></li>
                            <li><span class="extxml">jsont</span></li>
                            <li><span class="extxml">jsont2</span></li>
                            <li><span class="extxml">yaml</span></li>
                            <li><span class="extxml">yml</span></li>
                            <li><span class="extxml">md</span></li>
                        </ul>
                    </td>
                    <td>
                        <ul>
                            <li><span class="extpdf">pdf</span></li>
                            <li><span class="extimg">bmp</span></li>
                            <li><span class="extimg">png</span></li>
                            <li><span class="extimg">gif</span></li>
                            <li><span class="extimg">jpeg</span></li>
                            <li><span class="extimg">jpg</span></li>
                            <li><span class="extimg">jfif</span></li>
                            <li><span class="extimg">tiff</span></li>
                        </ul>
                    </td>
                    <td>
                        <ul>
                            <li><span class="extxif">xliff</span></li>
                            <li><span class="extxif">sdlxliff</span></li>
                            <li><span class="exttmx">tmx</span></li>
                            <li><span class="extttx">ttx</span></li>
                            <li><span class="extitd">itd</span></li>
                            <li><span class="extxlf">xlf</span></li>
                        </ul>
                    </td>
                    <td>
                        <ul>
                            <li><span class="extmif">mif</span></li>
                            <li><span class="extidd">idml</span></li>
                            <li><span class="extidd">icml</span></li>
                            <li><span class="extxml">xml</span></li>
                            <li><span class="extdit">dita</span></li>
                        </ul>
                    </td>
                    <td>
                        <ul>
                            <li><span class="extpro">properties</span></li>
                            <li><span class="extres">resx</span></li>
                            <li><span class="extxml">xml</span></li>
                            <li><span class="extxml">sxml</span></li>
                            <li><span class="extxml">txml</span></li>
                            <li><span class="extdit">dita</span></li>
                            <li><span class="extxml">Android xml</span></li>
                            <li><span class="extstr">strings</span></li>
                            <li><span class="extsbv">sbv</span></li>
                            <li><span class="extsrt">srt</span></li>
                            <li><span class="extvtt">vtt</span></li>
                            <li><span class="extwix">wix</span></li>
                            <li><span class="extpo">po</span></li>
                            <li><span class="extg">g</span></li>
                            <li><span class="exts">QT linguist ts</span></li>
                        </ul>
                    </td>
                </tr>
                </tbody>
            </table>
            <a class="gototop" href="#top">Go to top</a>
        </div>

        <div class="block block-api">
            <h3 name="languages" class="method-title">Supported languages</h3>
            <table class="tablestats" width="100%" border="0" cellspacing="0" cellpadding="0">
                <thead>
                <th>
                    Language ( Code )
                </th>
                </thead>
                <tbody>
                <tr>
                    <td>
                        <ul class="lang-list">
                            <?php foreach ( Langs_Languages::getInstance()->getEnabledLanguages() as $lang ): ?>
                                <li><?= $lang[ 'name' ] . " (" . $lang[ 'code' ] . ")" ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </td>
                </tr>
                </tbody>
            </table>
            <a class="gototop" href="#top">Go to top</a>
        </div>

        <div class="block block-api">
            <h3 name="subjects" class="method-title">Supported subjects</h3>
            <table class="tablestats" width="100%" border="0" cellspacing="0" cellpadding="0">
                <thead>
                <tr>
                    <th>Subject name</th>
                    <th>Code</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ( Langs_LanguageDomains::getInstance()->getEnabledDomains() as $domains ): ?>
                    <tr>
                        <td><?= $domains[ 'display' ] ?></td>
                        <td><?= $domains[ 'key' ] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <a class="gototop" href="#top">Go to top</a>
        </div>

        <div class="block block-api">
            <h3 name="seg-rules" class="method-title">Supported segmentation rules</h3>
            <table class="tablestats" width="100%" border="0" cellspacing="0" cellpadding="0">
                <thead>
                <tr>
                    <th>Segmentation rule name</th>
                    <th>Code</th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td>General</td>
                    <td><code>empty</code></td>
                </tr>
                <tr>
                    <td>Patent</td>
                    <td>patent</td>
                </tr>
                </tbody>
            </table>
            <a class="last gototop" href="#top">Go to top</a>
        </div>

        <div class="block block-api"></div>

    </div>
</div>


<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" style="position:absolute;width:0;height:0">
    <defs>
        <symbol viewBox="0 0 20 20" id="unlocked">
            <path d="M15.8 8H14V5.6C14 2.703 12.665 1 10 1 7.334 1 6 2.703 6 5.6V6h2v-.801C8 3.754 8.797 3 10 3c1.203 0 2 .754 2 2.199V8H4c-.553 0-1 .646-1 1.199V17c0 .549.428 1.139.951 1.307l1.197.387C5.672 18.861 6.55 19 7.1 19h5.8c.549 0 1.428-.139 1.951-.307l1.196-.387c.524-.167.953-.757.953-1.306V9.199C17 8.646 16.352 8 15.8 8z"></path>
        </symbol>

        <symbol viewBox="0 0 20 20" id="locked">
            <path d="M15.8 8H14V5.6C14 2.703 12.665 1 10 1 7.334 1 6 2.703 6 5.6V8H4c-.553 0-1 .646-1 1.199V17c0 .549.428 1.139.951 1.307l1.197.387C5.672 18.861 6.55 19 7.1 19h5.8c.549 0 1.428-.139 1.951-.307l1.196-.387c.524-.167.953-.757.953-1.306V9.199C17 8.646 16.352 8 15.8 8zM12 8H8V5.199C8 3.754 8.797 3 10 3c1.203 0 2 .754 2 2.199V8z"/>
        </symbol>

        <symbol viewBox="0 0 20 20" id="close">
            <path d="M14.348 14.849c-.469.469-1.229.469-1.697 0L10 11.819l-2.651 3.029c-.469.469-1.229.469-1.697 0-.469-.469-.469-1.229 0-1.697l2.758-3.15-2.759-3.152c-.469-.469-.469-1.228 0-1.697.469-.469 1.228-.469 1.697 0L10 8.183l2.651-3.031c.469-.469 1.228-.469 1.697 0 .469.469.469 1.229 0 1.697l-2.758 3.152 2.758 3.15c.469.469.469 1.229 0 1.698z"/>
        </symbol>

        <symbol viewBox="0 0 20 20" id="large-arrow">
            <path d="M13.25 10L6.109 2.58c-.268-.27-.268-.707 0-.979.268-.27.701-.27.969 0l7.83 7.908c.268.271.268.709 0 .979l-7.83 7.908c-.268.271-.701.27-.969 0-.268-.269-.268-.707 0-.979L13.25 10z"/>
        </symbol>

        <symbol viewBox="0 0 20 20" id="large-arrow-down">
            <path d="M17.418 6.109c.272-.268.709-.268.979 0s.271.701 0 .969l-7.908 7.83c-.27.268-.707.268-.979 0l-7.908-7.83c-.27-.268-.27-.701 0-.969.271-.268.709-.268.979 0L10 13.25l7.418-7.141z"/>
        </symbol>

        <symbol viewBox="0 0 24 24" id="jump-to">
            <path d="M19 7v4H5.83l3.58-3.59L8 6l-6 6 6 6 1.41-1.41L5.83 13H21V7z"/>
        </symbol>

        <symbol viewBox="0 0 24 24" id="expand">
            <path d="M10 18h4v-2h-4v2zM3 6v2h18V6H3zm3 7h12v-2H6v2z"/>
        </symbol>

    </defs>
</svg>

</body>
</html>
