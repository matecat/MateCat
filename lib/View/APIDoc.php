<?php
require_once '../../inc/Bootstrap.php';
Bootstrap::start();

$count = 0;
foreach ( INIT::$SUPPORTED_FILE_TYPES as $key => $value ) {
    $count += count( $value );
}

$nr_supoported_files = $count;

$max_file_size_in_MB = INIT::$MAX_UPLOAD_FILE_SIZE / (1024 * 1024);
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>API - Matecat</title>
    <link href="/public/css/style.css" rel="stylesheet" type="text/css" />
    <link href="/public/css/legacy-misc.css" rel="stylesheet" type="text/css" />
    <link href="/public/css/build/common.css" rel="stylesheet" type="text/css" />
    <script src="/public/js/lib/jquery.js"></script>
      <link rel="icon" type="image/png" href="images/favicon-32x32.png" sizes="32x32" />
  <link rel="icon" type="image/png" href="images/favicon-16x16.png" sizes="16x16" />
  <link href='/public/api/dist/css/screen.css' media='screen' rel='stylesheet' type='text/css'/>
  <link href='/public/api/dist/css/print.css' media='print' rel='stylesheet' type='text/css'/>

    <script>
        /*<![CDATA[*/
        config = {} ;
        config.swagger_host = '<?php echo $_SERVER[ 'HTTP_HOST' ] ?>';
        /*]]>*/
    </script>

  <script src='/public/api/dist/lib/object-assign-pollyfill.js' type='text/javascript'></script>
  <script src='/public/api/dist/lib/jquery-1.8.0.min.js' type='text/javascript'></script>
  <script src='/public/api/dist/lib/jquery.slideto.min.js' type='text/javascript'></script>
  <script src='/public/api/dist/lib/jquery.wiggle.min.js' type='text/javascript'></script>
  <script src='/public/api/dist/lib/jquery.ba-bbq.min.js' type='text/javascript'></script>
  <script src='/public/api/dist/lib/handlebars-4.0.5.js' type='text/javascript'></script>
  <script src='/public/api/dist/lib/lodash.min.js' type='text/javascript'></script>
  <script src='/public/api/dist/lib/backbone-min.js' type='text/javascript'></script>
  <script src='/public/api/dist/swagger-ui.js' type='text/javascript'></script>
  <script src='/public/api/dist/lib/highlight.9.1.0.pack.js' type='text/javascript'></script>
  <script src='/public/api/dist/lib/highlight.9.1.0.pack_extended.js' type='text/javascript'></script>
  <script src='/public/api/dist/lib/jsoneditor.min.js' type='text/javascript'></script>
  <script src='/public/api/dist/lib/marked.js' type='text/javascript'></script>
  <script src='/public/api/dist/lib/swagger-oauth.js' type='text/javascript'></script>

  <script src='/public/api/swagger-source.js' type='text/javascript'></script>
</head>
<body class="api swagger-section pippo">



<header>
    <div class="wrapper ">
        <a href="/" class="logo"></a>
    </div>
</header>
<div id="contentBox" class="wrapper">
    <div class="colsx">
        <a href="#top"><span class="logosmall"></span></a>
        <h1>API</h1>
        <ul class="menu">
              
            
                <li data-id="Project"><a class="anchor_api">Project</a></li>

              
                <li data-id="Comments"><a class="anchor_api">Comments</a></li>
                <li data-id="Quality_Report"><a class="anchor_api">Quality Report</a></li>
                <li data-id="Teams"><a class="anchor_api">Teams</a></li>
                <li data-id="Translation_Issues"><a class="anchor_api">Translation Issues</a></li>
                <li data-id="Translation_Versions"><a class="anchor_api">Translation Versions</a></li>
                <li data-id="Job"><a class="anchor_api">Job</a></li>
                <li data-id="Options"><a class="anchor_api">Options</a></li>
                <li data-id="Glossary"><a class="anchor_api">Glossary</a></li>
              
            
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
            <a name="file-format"><h3 class="method-title">Supported file formats</h3></a>


            <table class="tablestats fileformat" width="100%" border="0" cellspacing="0" cellpadding="0">

                <thead>
                <tr><th width="40%">Office</th>
                    <th width="15%">Web</th>
                    <th width="15%">Interchange Formats</th>
                    <th width="15%">Desktop Publishing</th>
                    <th width="15%">Localization</th>
                </tr></thead>
                <tbody><tr>
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
                </tbody></table>


            <a class="gototop" href="#top">Go to top</a>
        </div>

        <div class="block block-api">
            <a name="languages"><h3 class="method-title">Supported languages</h3></a>

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
                            <?php foreach( Langs_Languages::getInstance()->getEnabledLanguages() as $lang ): ?>
                            <li><?=$lang['name'] . " (" . $lang['code']  . ")"?></li>
                            <?php endforeach; ?>
                        </ul>
                    </td>
                </tr>
                </tbody>
            </table>
            <a class="gototop" href="#top">Go to top</a>
        </div>

        <div class="block block-api">
            <a name="subjects"><h3 class="method-title">Supported subjects</h3></a>


            <table class="tablestats" width="100%" border="0" cellspacing="0" cellpadding="0">
                <thead>
                <tr>
                    <th>Subject name</th>
                    <th>Code</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach( Langs_LanguageDomains::getInstance()->getEnabledDomains() as $domains ): ?>
                <tr><td><?=$domains['display']?></td><td><?=$domains['key']?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <a class="gototop" href="#top">Go to top</a>
        </div>


        <div class="block block-api">
            <a name="seg-rules"><h3 class="method-title">Supported segmentation rules</h3></a>


            <table class="tablestats" width="100%" border="0" cellspacing="0" cellpadding="0">
                <thead>
                <tr>
                    <th>Segmentation rule name</th>
                    <th>Code</th>
                </tr>
                </thead>
                <tbody>
                <tr><td>General </td><td><code>empty</code></td></tr>
                <tr><td>Patent</td><td>patent</td></tr>

                </tbody>
            </table>
            <a class="last gototop" href="#top">Go to top</a>
        </div>

        <div class="block block-api">
        </div>
        
    </div>
</div>
<script type="text/javascript">
// add active class to menu

  $(".menu a").click(function() {
        if ($(this).hasClass('active')) {
          console.log('active');
          
        }
        else {
          $(".menu a").removeClass('active');
           $(this).addClass('active');
           console.log('inactive');
        }
       
    });
// anchor menu when scrolling

    $(window).scroll(function() {
        var scroll = $(window).scrollTop();

        if (scroll >= 30) {
            $(".colsx").addClass("menuscroll");
        }
        else {
            $(".colsx").removeClass("menuscroll");
        }

    });
  

// smooth scrolling

    $(function() {
        $('a[href*=#]:not([href=#])').click(function() {
            if (location.pathname.replace(/^\//,'') == this.pathname.replace(/^\//,'') && location.hostname == this.hostname) {
                var target = $(this.hash);
                target = target.length ? target : $('[name=' + this.hash.slice(1) +']');
                if (target.length) {
                    $('html,body').animate({
                        scrollTop: target.offset().top
                    }, 1000);
                    return false;
                }
            }
        });
    });

// scroll to id + add active on menu

 $(".anchor_api").click(function() {
                    var name = $(this).closest("li").attr("data-id");
                      $('html, body').animate({
                          scrollTop: $("#resource_"+name).offset().top
                      }, 500,function() {
                        
                    });  
                       if ($(this).hasClass("selected") && $("#resource_"+name).hasClass('active')) {
                            console.log("selected");
                        }
                        else {
                          if (!$("#resource_"+name).hasClass('active')) {
                            console.log("selected");
                            $(this).addClass('selected');
                          $("#resource_"+name+ " #endpointListTogger_"+name).click();
                            console.log("selected");
                        }
                          
                        }
                  });
</script>
</body>
</html>
