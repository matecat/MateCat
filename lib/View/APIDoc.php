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
    <link href="/public/css/style.css" rel="stylesheet" type="text/css"/>
    <link href="/public/css/legacy-misc.css" rel="stylesheet" type="text/css"/>
    <link href="/public/css/build/common.css" rel="stylesheet" type="text/css"/>

    <link rel="stylesheet" type="text/css" href="/public/api/dist/lib/swagger-ui.css">
    <link rel="icon" href="/public/img/favicon.ico"/>

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

            // Build a system
            window.swaggerUi = SwaggerUIBundle( {
                spec: spec,
                dom_id: '#swagger-ui-container',
                supportedSubmitMethods: ['get',
                    'post',
                    'put',
                    'delete'],
                docExpansion: 'none',
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIStandalonePreset
                ],
                plugins: [
                    SwaggerUIBundle.plugins.DownloadUrl
                ],
                layout: "StandaloneLayout"
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

        } );

        /*]]>*/
    </script>
</head>
<body class="api swagger-section">


<header>
    <div class="wrapper ">
        <a href="/" class="logo"></a>
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
            <a name="api-swagger"><h3 class="method-title">List of commands</h3></a>
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
                            <li><span class="extdoc">dot</span></li>
                            <li><span class="extdoc">docx</span></li>
                            <li><span class="extdoc">dotx</span></li>
                            <li><span class="extdoc">docm</span></li>
                            <li><span class="extdoc">dotm</span></li>
                            <li><span class="extdoc">rtf</span></li>
                            <li><span class="extdoc">odt</span></li>
                            <li><span class="extdoc">sxw</span></li>
                            <li><span class="exttxt">txt</span></li>
                            <li><span class="extpdf">pdf</span></li>
                            <li><span class="extxls">xls</span></li>
                            <li><span class="extxls">xlt</span></li>
                            <li><span class="extxls">xlsm</span></li>
                            <li><span class="extxls">xlsx</span></li>
                            <li><span class="extxls">xltx</span></li>
                            <li><span class="extxls">ods</span></li>
                            <li><span class="extxls">sxc</span></li>
                            <li><span class="extxls">csv</span></li>
                            <li><span class="extppt">pot</span></li>
                            <li><span class="extppt">pps</span></li>
                            <li><span class="extppt">ppt</span></li>
                            <li><span class="extppt">potm</span></li>
                            <li><span class="extppt">potx</span></li>
                            <li><span class="extppt">ppsm</span></li>
                            <li><span class="extppt">ppsx</span></li>
                            <li><span class="extppt">pptm</span></li>
                            <li><span class="extppt">pptx</span></li>
                            <li><span class="extppt">odp</span></li>
                            <li><span class="extppt">sxi</span></li>
                        </ul>
                    </td>
                    <td>
                        <ul>
                            <li><span class="exthtm">htm</span></li>
                            <li><span class="exthtm">html</span></li>
                            <li><span class="exthtm">xhtml</span></li>
                            <li><span class="extxml">xml</span></li>
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
                            <li><span class="extidd">inx</span></li>
                            <li><span class="extidd">idml</span></li>
                            <li><span class="extidd">icml</span></li>
                            <li><span class="extqxp">xtg</span></li>
                            <li><span class="exttag">tag</span></li>
                            <li><span class="extxml">xml</span></li>
                            <li><span class="extdit">dita</span></li>
                        </ul>
                    </td>
                    <td>
                        <ul>
                            <li><span class="extpro">properties</span></li>
                            <li><span class="extrcc">rc</span></li>
                            <li><span class="extres">resx</span></li>
                            <li><span class="extxml">xml</span></li>
                            <li><span class="extdit">dita</span></li>
                            <li><span class="extsgl">sgml</span></li>
                            <li><span class="extsgm">sgm</span></li>
                            <li><span class="extxml">Android xml</span></li>
                            <li><span class="extstr">strings</span></li>
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

</body>
</html>
