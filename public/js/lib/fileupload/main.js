/*
 * jQuery File Upload Plugin JS Example 6.7
 * https://github.com/blueimp/jQuery-File-Upload
 *
 * Copyright 2010, Sebastian Tschan
 * https://blueimp.net
 *
 * Licensed under the MIT license:
 * http://www.opensource.org/licenses/MIT
 */

/*jslint nomen: true, unparam: true, regexp: true */
/*global $, window, document */

UI = null;


UI = {
    init: function () {
        this.conversionBlocked = false;
        this.RTLCheckDone = false;
        this.skipLangDetectArr = {};

        var base = Math.log( config.maxFileSize ) / Math.log( 1024 );
        config.maxFileSizePrint = parseInt( Math.pow( 1024, ( base - Math.floor( base ) ) ) + 0.5 ) + ' MB';

        base = Math.log( config.maxTMXFileSize ) / Math.log( 1024 );
        config.maxTMXSizePrint = parseInt( Math.pow( 1024, ( base - Math.floor( base ) ) ) + 0.5 ) + ' MB';

        if (this.initTM) {
            this.initTM();
        }
        if ( Cookies.get( 'tmpanel-open' ) == '1' ) UI.openLanguageResourcesPanel();
    },
    getPrintableFileSize: function ( filesizeInBytes ) {

        filesizeInBytes = filesizeInBytes / 1024;
        ext = " KB";

        if ( filesizeInBytes > 1024 ) {
            filesizeInBytes = filesizeInBytes / 1024;
            ext = " MB";
        }

        return Math.round( filesizeInBytes * 100, 2 ) / 100 + ext;

    },
    enableAnalyze: function () {
        enableAnalyze();
    },
    disableAnalyze: function () {
        disableAnalyze();
    },
    checkMultilangRTL: function () {
        var direction = "ltr";
        $('.popup-languages li.on input').each(function(){
            if($('#target-lang div[data-value="' + $(this).val() + '"]').attr('data-direction') == 'rtl') direction = "rtl";
        });
        return direction;
    },
    conversionsAreToRestart: function () {
        var num = 0;
        $( '.template-download .name' ).each( function () {
            if ( $( this ).parent( 'tr' ).hasClass( 'failed' ) ) return;
            if ( $( this ).text().split( '.' )[$( this ).text().split( '.' ).length - 1] != 'sdlxliff' ) num++;
        } );
        return num
    },
    checkTMXLangFailure: function () {
        return $( '#source-lang' ).hasClass( 'failed-tmx-lang' ) || $( '#target-lang' ).hasClass( 'failed-tmx-lang' );
    },
    addTMXLangFailure: function () {
        $( '#source-lang' ).addClass( 'failed-tmx-lang' );
        $( '#target-lang' ).addClass( 'failed-tmx-lang' );
    },
    delTMXLangFailure: function () {
        $( '#source-lang' ).removeClass( 'failed-tmx-lang' );
        $( '#target-lang' ).removeClass( 'failed-tmx-lang' );
        $( '.uploadbtn' ).attr( 'value', 'Analyze' ).removeAttr( 'disabled' ).removeClass( 'disabled' );
    },
    confirmRestartConversions: function () {
        UI.restartConversions();
    },
    confirmGDriveRestartConversions: function () {
        APP.restartGDriveConversions();
    },
    errorsFileSize: function ( file ) {

        var ext = file.name.split( '.' ).pop();

        if ( ext == 'tmx' && file.size > config.maxTMXFileSize ) {
            file.error = 'Error during upload. The uploaded TMX file exceed the file size limit of ' + config.maxTMXSizePrint;
        } else if ( ext != 'tmx' && file.size > config.maxFileSize ) {
            file.error = 'Error during upload. The uploaded file exceed the file size limit of ' + config.maxFileSizePrint;
        } else {
            file.error = null;
        }

    },
    errorsBeforeUpload: function ( file ) {
        console.log( 'errorsBeforeUpload' );

        var ext = file.name.split( '.' ).pop();

        console.log( file );

        var msg = '';

        if ( file.type.match( /^image/ ) ) {
            msg = 'Images not allowed in MateCat';
        } else if (
                (
                    //file.type == 'application/zip' ||
                file.type == 'application/x-gzip' ||
                file.type == 'application/x-tar' ||
                file.type == 'application/x-gtar' ||
                file.type == 'application/x-7z-compressed') ||
                ( ext == 'tgz' )
        ) {
            msg = 'GZIP, TAR, GTAR, 7Zip archives not yet supported. Coming soon.';
        } else {
            msg = 'Format not supported. Convert to DOCX and upload the file again.';
        }

        UI.checkFailedConversionsNumber();

        console.log( 'msg: ', msg );

        return msg;

    },
    restartConversions: function () {
        console.log( 'restart conversions' );
        this.conversionBlocked = true;
        $( '.template-download, .template-upload' ).each( function () {
            if ( config.conversionEnabled ) {
                var filerow = $( this );
                var filename = $( '.name', filerow ).text();
                var filesize = ($( '.size span', filerow ).length) ? parseFloat( $( '.size span', filerow ).text().split( ' ' )[0] ) * 1000000 : 0;
                var currentSession = $( filerow ).data( 'session' );
                $.each( UI.conversionRequests, function () {
                    if ( this.session == currentSession ) {
                        $( filerow ).addClass( 'restarting' );
                        console.log( 'session: ' + this.session );
                        this.request.abort();
                    }
                } );

                $( filerow ).data( 'session', '' );
                $( '.operation', filerow ).remove();
                $( '.progress', filerow ).remove();
                console.log( 'ACTION: restartConversions' );
                convertFile( filename, filerow, filesize, true );
            }
        } );
    },

    checkAnalyzability: function () {
        return checkAnalyzability();
    },

    TMXloaded: function () {
        // $( '#disable_tms_engine' ).trigger( 'click' );
        this.createKeyByTMX();
    },

    createKeyByTMX: function (extension) {
        if ( !isTMXAllowed() ) return false;
        if ( $(".mgmt-tm .new .privatekey .btn-ok").hasClass( 'disabled' ) ) return false; //ajax call already running
        if( $( '.mgmt-panel #activetm tbody tr.mine' ).length && $( '.mgmt-panel #activetm tbody tr.mine .update input' ).is(":checked")) return false; //a key is already selected in TMKey management panel

        APP.createTMKey();
        var textToDisplay = '<span>A new resource has been generated for the TMX you uploaded. You can manage your resources in the  <a href="#" class="translation-memory-option-panel">Settings panel</a>.</span>';
        if (extension && extension === "g") {
            textToDisplay = '<span>A new resource has been generated for the glossary you uploaded. You can manage your resources in the  <a href="#" class="translation-memory-option-panel">Settings panel</a>.</span>';
        }


        $( '.warning-message' ).html( textToDisplay ).show();
        $('.warning-message .translation-memory-option-panel').off('click').on('click', function() {
            APP.openOptionsPanel("tm");
        } );
    },

    checkFailedConversionsNumber: function () {

        var n = $( '.template-download.failed, .template-upload.failed, .template-download.has-errors, .template-upload.has-errors' ).length;
        if ( n > 1 ) {
            $( '#delete-failed-conversions' ).show();
        } else {
            $( '#delete-failed-conversions' ).hide();
        }

    },

    addInlineMessage: function ( fileName, message ) {
        var currDeleteDiv = $( '.upload-table td.name:contains("' + fileName + '")' ).next().next().addClass( "file_upload_error" );

        if ( $( currDeleteDiv ).find( ".skiplangdetect" ).length == 0 ) {
            $( currDeleteDiv ).html( "" )
                    .append(
                    '<span class="label label-important">' +
                    message +
                    '</span>' );
        }
    },
    updateTMAddedMsg: function () {
        var numTM = $( '#activetm tr.mine' ).length;
        if ( numTM ) {
            $( '.tm-added .num' ).text( numTM );
            if ( numTM > 1 ) {
                $( '.tm-added .msg' ).text( ' TMs added' );
            } else {
                $( '.tm-added .msg' ).text( ' TM added' );
            }
            $( '.tm-added' ).show();
        } else {
            $( '.tm-added' ).hide();
            $( '.tm-added .num' ).text( '' );
        }
    },
    uploadingTMX: function () {
        return $( '.mgmt-tm td.uploadfile.uploading' ).length;
    }

};

$( function () {
    'use strict';

    // Initialize the jQuery File Upload widget:
    $( '#fileupload' ).fileupload();

    // Enable iframe cross-domain access via redirect option:
    $( '#fileupload' ).fileupload(
            'option',
            'redirect',
            window.location.href.replace(
                    /\/[^\/]*$/,
                    '/cors/result.html?%s'
            )
    );
    var dropzone = $( '#overlay' );
    var langCorrections = [];
    UI.conversionRequests = [];

    $( document ).bind( 'drop dragover', function ( e ) {
        e.preventDefault();
    } );

    $( '#fileupload' ).fileupload(
            'option',
            {
                dropZone: $( '.drag' ),
                autoUpload: true,
                singleFileUploads: true,
                overlayClose: true,
                maxFileSize: config.maxFileSize, // 30MB
                fileInput: $( '#fileupload .multiple-button, .btncontinue .multiple-button' ),
                acceptFileTypes: config.allowedFileTypes,
                dataType: config.blueimp_dataType
            }
    );
    $( '#fileupload' ).bind( 'fileuploaddragover', function ( e ) {
        $( '.upload-files' ).addClass( 'dragging' );
        dropzone.show();
    } ).bind( 'fileuploadadd', function ( e, data ) {

        $( 'body' ).addClass( 'initialized' );

        if ( $( '.upload-table tr' ).length >= (config.maxNumberFiles) ) {
            console.log( 'adding more than config.maxNumberFiles' );
            var jqXHR = data.submit();
            jqXHR.abort();
        }

        disableAnalyze();
        $( '#fileupload table.upload-table tr' ).addClass( 'current' );

    } ).bind( 'fileuploadsend', function ( e, data ) {
        console.log( 'FIRE fileuploadsend' );
        console.log( data.files );
        $( '.progress', $( data.context[0] ) ).before( '<div class="operation">Uploading</div>' );
    } ).bind( 'fileuploadprogress', function ( e, data ) {
        console.log( data.loaded );
    } ).bind( 'fileuploadstart', function ( e ) {
        console.log( 'FIRE fileuploadstart' );
    } ).bind( 'fileuploaddone', function ( e, data ) {

    } ).bind( 'fileuploaddrop', function ( e ) {
        $( '.upload-files' ).addClass( 'uploaded' );
        $( '.upload-files' ).removeClass( 'dragging dnd-hover' );
        dropzone.hide();
    } ).bind( 'fileuploaddone', function ( e, data ) {

    } ).bind( 'fileuploadadded fileuploaddestroyed', function ( e, data ) {
        if ( $( '.upload-table tr' ).length ) {
            $( '.upload-files' ).addClass( 'uploaded' );
            if (APP.hideGDLink)
                APP.hideGDLink();
        } else {
            $( '.upload-files' ).removeClass( 'uploaded' );
            if (APP.showGDLink)
                APP.showGDLink();
        }
    } ).bind( 'fileuploadfail', function ( e ) {
        if ( !($( '.upload-table tr' ).length > 1) ) $( '.upload-files' ).removeClass( 'uploaded' );
        UI.checkFailedConversionsNumber();
    } ).bind( 'fileuploadchange', function ( e ) {
        $( '.upload-files' ).addClass( 'uploaded' );
        console.log( 'FIRE fileuploadchange' );
        UI.checkFailedConversionsNumber();
    } ).bind( 'fileuploaddestroyed', function ( e, data ) {

        var deletedFileName = data.url.match( /file=[^&]*/g );
        if (deletedFileName) {
            deletedFileName = decodeURIComponent( deletedFileName[0].replace( "file=", "" ) );

            if ( typeof( UI.skipLangDetectArr[deletedFileName] ) !== 'undefined' ) {
                delete(UI.skipLangDetectArr[deletedFileName]);
            }
        }

        if ( $( '.wrapper-upload .error-message.no-more' ).length ) {

            if ( $( '.upload-table tr' ).length < (config.maxNumberFiles) ) {

                $( '.error-message' ).find('p').text('');
                $( '.error-message' ).hide();

                $( '#fileupload' ).fileupload( 'option', 'dropZone', $( '.drag' ) );
                $( '#add-files' ).removeClass( 'disabled' );
                $( '#add-files input' ).removeAttr( 'disabled' );
            }

        }
        UI.checkFailedConversionsNumber();

        if ( $( '.upload-table tr:not(.failed)' ).length ) {

            if ( checkAnalyzability( 'fileuploaddestroyed' ) ) {
                enableAnalyze();
            }

        } else {
            disableAnalyze();
        }

    } ).on( 'click', '.template-upload .cancel button', function ( e, data ) {

        console.log( 'file canceled' );
        if ( $( '.wrapper-upload .error-message.no-more' ).length ) {

            if ( $( '.upload-table tr' ).length < (config.maxNumberFiles) ) {

                $( '.error-message' ).find('p').text('');
                $( '.error-message' ).hide();

                $( '#fileupload' ).fileupload( 'option', 'dropZone', $( '.drag' ) );
                $( '#add-files' ).removeClass( 'disabled' );
                $( '#add-files input' ).removeAttr( 'disabled' );
            }

        }
        setTimeout( function () {
            UI.checkFailedConversionsNumber();
        }, 500 );

        if ( $( '.upload-table tr:not(.failed)' ).length ) {

            if ( checkAnalyzability( 'fileuploaddestroyed' ) ) {
                enableAnalyze();
            }

        } else {
            disableAnalyze();
        }

    } ).bind( 'fileuploadcompleted', function ( e, data ) {
        console.log( 'FIRE fileuploadcompleted' );
        if ( !$( 'body' ).hasClass( 'initialized' ) ) {
            $( '#clear-all-files' ).click();
        }
        var maxnum = config.maxNumberFiles;
        if ( $( '.upload-table tr' ).length > (maxnum - 1) ) {
            console.log( '10 files loaded' );
            $( '.wrapper-upload .error-message' ).addClass( 'no-more' ).find('p').text( 'No more files can be loaded (the limit of ' + maxnum + ' has been exceeded).' );
            $( '.wrapper-upload .error-message' ).show()
            $( '#fileupload' ).fileupload( 'option', 'dropZone', null );
            $( '#add-files' ).addClass( 'disabled' );
            $( '#add-files input' ).attr( 'disabled', 'disabled' );

        }

        $( 'body' ).addClass( 'initialized' );
        /*
         * BUG FIXED: UTF16 / UTF8 File name conversion
         * Use Return String From AJAX RESULT ( safe raw url encoded )
         *      and NOT data.files[0].name; ( INPUT TAG content )
         *
         *      fname: data.result[0].name,
         *
         **/
        var fileSpecs;
        if (data.result[0]) {

            fileSpecs = {
                fname: data.result[0].name,
                filesize: data.result[0].size,
                filerow: data.context,
                extension: data.result[0].name.split( '.' )[data.result[0].name.split( '.' ).length - 1],
                error: ( typeof data.result[0].error !== 'undefined' ? data.result[0].error : false ),
                enforceConversion: data.result[0].convert
            };
        } else {
            fileSpecs = {
                error: ( typeof data.result.errors !== 'undefined' ? data.result.errors[0] : false ),
            };
        }

        if ( !fileSpecs.enforceConversion ) {
            if ( checkAnalyzability( 'file upload completed' ) ) {
                enableAnalyze();
            }
        }

        if ( $( 'body' ).hasClass( 'started' ) ) {
            setFileReadiness();
            if ( checkAnalyzability( 'primo caricamento' ) ) {
                enableAnalyze();
            }
        }
        $( 'body' ).removeClass( 'started' );

        $( '.name', fileSpecs.filerow ).text( fileSpecs.fname );

        if ( typeof data.data != 'undefined' && !fileSpecs.error ) {

            //Global
            UI.skipLangDetectArr[fileSpecs.fname] = 'detect';

            if ( config.conversionEnabled ) {

                if ( !fileSpecs.filerow.hasClass( 'converting' ) ) {
                    //console.log( filerow );
                    console.log( 'ACTION: bind fileuploadcompleted' );
                    convertFile( fileSpecs.fname, fileSpecs.filerow, fileSpecs.filesize, fileSpecs.enforceConversion );
                }

            } else {
                enableAnalyze();
            }

            /**
             * Check for TMX file type, we must trigger the creation of a new TM key
             */
            var extension = data.files[0].name.split( '.' )[data.files[0].name.split( '.' ).length - 1];
            if ( ( extension == 'tmx' || extension == 'g' ) && config.conversionEnabled ) {
                UI.createKeyByTMX(extension);
            }

        } else if ( fileSpecs.error ) {
            disableAnalyze();
            $( '.wrapper-upload .error-message' ).addClass( 'no-more' ).find('p').text( 'An error occurred during upload.' );
            $( '.wrapper-upload .error-message' ).show();
            $( '#fileupload' ).fileupload( 'option', 'dropZone', null );
            $( '#add-files' ).addClass( 'disabled' );
            $( '#add-files input' ).attr( 'disabled', 'disabled' );
        }

        if ( $( '.upload-table tr' ).length ) {
            $( '.upload-files' ).addClass( 'uploaded' );
        } else {
            $( '.upload-files' ).removeClass( 'uploaded' );
        }

    } );

    $( '.upload-files' ).bind( 'dragleave', function ( e ) {
        $( this ).removeClass( 'dragging' );
    } );

    $( '[draggable="true"]' ).on( 'dragend', function () {
        dropzone.hide();
    } );

    dropzone.on( 'dragenter', function ( event ) {
        $( '.upload-files' ).addClass( 'dnd-hover' );
    } );

    dropzone.on( 'dragleave', function ( event ) {
        $( '.upload-files' ).removeClass( 'dnd-hover' );
    } );

    $( '#clear-all-files' ).bind( 'click', function ( e ) {
        e.preventDefault();
        $( '.wrapper-upload .error-message' ).hide();
        $( '.template-download .delete button, .template-upload .cancel button' ).click();
    } );

    $( '#delete-failed-conversions' ).bind( 'click', function ( e ) {
        e.preventDefault();
        console.log( $( '.template-download.failed .delete button, .template-download.has-errors .delete button, .template-upload.failed .cancel button, .template-upload.has-errors .cancel button' ) );
        $( '.template-download.failed .delete button, .template-download.has-errors .delete button, .template-upload.failed .cancel button, .template-upload.has-errors .cancel button' ).click();
    } );

    // Load existing files:
    $( '#fileupload' ).each( function () {
        var that = this;
        $.getJSON( this.action, function ( result ) {
            if ( result && result.length ) {
                $( that ).fileupload( 'option', 'done' )
                        .call( that, null, {result: result} );
            }
        } );
    } );

    // Initialize the theme switcher:
    $( '#theme-switcher' ).change( function () {
        var theme = $( '#theme' );
        theme.prop(
                'href',
                theme.prop( 'href' ).replace(
                        /[\w\-]+\/jquery-ui.css/,
                        $( this ).val() + '/jquery-ui.css'
                )
        );
    } );

} );

userLangName = function ( t, userLangCode ) {
    return $( '#' + t + '-lang  option[value=\'' + userLangCode + '\']' ).text();
}

progressBar = function ( filerow, start, filesize ) {
    var ob = $( '.ui-progressbar-value', filerow );
    if ( ob.hasClass( 'completed' ) ) return;

    ob.css( 'width', start + '%' );
    if ( start > 90 ) {
        return;
    }

    if ( !UI.conversionBlocked ) {
        setTimeout( function () {
            progressBar( filerow, start + 1, filesize );
        }, 200 );
    } else {
        UI.conversionBlocked = false;
    }

}

convertFile = function ( fname, filerow, filesize, enforceConversion ) {

    console.log( 'Enforce conversion: ' + enforceConversion );
    firstEnforceConversion = (typeof enforceConversion === "undefined") ? false : enforceConversion;
    enforceConversion = (typeof enforceConversion === "undefined") ? false : enforceConversion;

    if ( enforceConversion === false ) {
        filerow.addClass( 'ready' );
        if ( checkAnalyzability( 'convert file' ) ) {
            enableAnalyze();
        }

        return;
    }
    else {
        disableAnalyze();
    }

    var ses = new Date();
    var session = ses.getTime();

    filerow.removeClass( 'ready' ).addClass( 'converting' ).data( 'session', session );

    var request = $.ajax( {
        url: '?action=convertFile',
        data: {
            action: 'convertFile',
            file_name: fname,
            source_lang: $( '#source-lang' ).dropdown('get value'),
            target_lang: $( '#target-lang' ).dropdown('get value'),
            segmentation_rule: $( '#segm_rule' ).val()
        },
        type: 'POST',
        dataType: 'json',
        context: firstEnforceConversion,
        error: function ( d ) {
            if ( $( filerow ).hasClass( 'restarting' ) ) {
                $( filerow ).removeClass( 'restarting' );
                return;
            }
            filerow.removeClass( 'converting' );
            console.log( 'conversion error' );
            console.log( $( '.progress', filerow ) );
            setTimeout( function () {
                $( '.progress', filerow ).remove();
                $( '.operation', filerow ).remove();
            }, 50 );

            $( 'td.size', filerow ).next().addClass( 'file_upload_error' ).empty().attr( 'colspan', '2' ).append( '<span class="label label-important">Error: </span>Server error, try again.' );
            $( filerow ).addClass( 'has-errors' );
            UI.checkFailedConversionsNumber();
            return false;
        },
        success: function ( d ) {

            falsePositive = (typeof this.context == 'undefined') ? false : true; // old solution
            filerow.removeClass( 'converting' );
            filerow.addClass( 'ready' );
            if ( d.code == 1 || d.code == 2 ) {

                $( '.ui-progressbar-value', filerow ).addClass( 'completed' ).css( 'width', '100%' );

                if ( checkAnalyzability( 'convertfile on success' ) ) {
                    enableAnalyze();
                }
                $( '.operation', filerow ).fadeOut( 'slow', function () {
                    // Animation complete.
                } );
                $( '.progress', filerow ).fadeOut( 'slow', function () {
                    // Animation complete.
                } );

                //if this conversion is related to a Zip File
                if ( typeof d.data != 'undefined' && typeof d.data['zipFiles'] !== 'undefined' ) {
                    //zip files has been loaded
                    //print internal file list

                    var zipFiles = $.parseJSON( d.data['zipFiles'] );

                    //START editing by Roberto Tucci <roberto@translated.net>
                    var rowParent = filerow.parent().first();
                    $.each( zipFiles, function ( i, file ) {

                        //clone the main row and edit its fields
                        var rowClone = filerow.clone();

                        rowClone.removeClass( 'converting' );
                        rowClone.addClass( 'ready' );

                        var rawPath = file['name'].split( "/" );
                        var zipFile = rawPath[0];

                        var fileExt = file['name'].split( "." ).pop();

                        //change name to the file
                        $( rowClone ).find( '.name' ).first()
                                .data( "zipfile", zipFile )
                                .attr( "data-zipfile", zipFile )
                                .html( "<i class='icon-make-group icon'/>" + "<span class=\"zip_internal_file\">" + file['name'].replace(/&/g,"&amp;") + "</span>" );

                        $( rowClone ).find( '.size' ).first().html( UI.getPrintableFileSize( file['size'] ) );

                        var oldDataUrl = $( 'button[data-url]', rowClone ).data( "url" );

                        var newExtClass = getIconClass( fileExt );
                        $( rowClone ).find( '.preview span' ).first().attr( "class", newExtClass );


                        $( rowClone ).find( '.operation' ).first().parent().first().html( '' );

                        var newDataUrl = oldDataUrl.replace( /file=[^&]+/g, "file=" + encodeURI( file['name'] ) );

                        $( 'button[data-url]', rowClone )
                                .data( "url", newDataUrl )
                                .attr( "data-url", newDataUrl )
                                .removeClass( 'zip_row' );

                        for ( var k = 0; k < d.errors.length; k++ ) {

                            if ( d.errors[k].debug == file['name'] ) {
                                $( 'td.size', rowClone )
                                        .html("")
                                        .next()
                                        .addClass( 'file_upload_error' )
                                        .empty().attr( 'colspan', '2' )
                                        .css( {'font-size': '14px'} )
                                        .append(
                                        '<span class="label label-important">' +
                                        d.errors[k].message +
                                        '</span>'
                                );
                                $( rowClone ).addClass( 'failed' );
                                setTimeout( function () {
                                    $( '.progress', filerow ).remove();
                                    $( '.operation', filerow ).remove();
                                }, 50 );
                            }

                        }

                        var thisIsATMXFile       = file['name'].split( "." ).pop().toLowerCase() == 'tmx';
                        var thereIsAKeyInTmPanel = $( '#activetm' ).find( 'tr.mine' ).length;

                        /* c'è almeno un file tmx e non ho già generato la chiave => genera la chiave */
                        if( thisIsATMXFile && !thereIsAKeyInTmPanel ){
                            UI.createKeyByTMX();
                        }

                        $( filerow ).after( rowClone );

                        $( 'button[data-url]', filerow ).addClass( "zip_row" );


                    } );

                    if ( d.errors.length > 0 ) {
                        UI.checkFailedConversionsNumber();
                        disableAnalyze();
                        return false;
                    }
                    //END editing by Roberto Tucci <roberto@translated.net>

                    var notTranslationFileCount = 0;
                    $( ".name" ).each( function () {
                        var currSplitLength = $( this ).text().split( "." ).length - 1;
                        if ( $( this ).text().split( "." )[currSplitLength] == "tmx" ||
                                $( this ).text().split( "." )[currSplitLength - 1] == "tmx" ||
                                $( this ).text().split( "." )[currSplitLength] == "g" ||
                                $( this ).text().split( "." )[currSplitLength - 1] == "g" ||
                                $( this ).text().split( "." )[currSplitLength] == "zip" ) {
                            notTranslationFileCount++;
                        }
                    } );
                    if ( notTranslationFileCount == $( ".name" ).length ) {
                        disableAnalyze();
                    }
                }

            } else if ( d.code <= 0 || d.errors.length > 0 ) {

                console.log( d.errors[0].message );

                $( 'td.size', filerow ).next().addClass( 'file_upload_error' ).empty().attr( 'colspan', '2' ).css( {'font-size': '14px'} ).append( '<span class="label label-important">' + d.errors[0].message + '</span>' );
                $( filerow ).addClass( 'failed' );
                setTimeout( function () {
                    $( '.progress', filerow ).remove();
                    $( '.operation', filerow ).remove();
                }, 50 );
                UI.checkFailedConversionsNumber();

                //filters ocr warning
                if ( d.code == -20 ){
                        enableAnalyze();
                }

            } else {

            }

        }
    } );
    var r = {};
    r.session = session;
    r.request = request;
    UI.conversionRequests.push( r );

    $( '.size', filerow ).next().append( '<div class="operation">Importing</div><div class="converting progress progress-success progress-striped active ui-progressbar ui-widget ui-widget-content ui-corner-all" aria-valuenow="0" aria-valuemax="100" aria-valuemin="0" role="progressbar"><div class="ui-progressbar-value ui-widget-header ui-corner-left" style="width: 0%;"></div></div>' );

    testProgress( filerow, filesize, session, 0 );

};

testProgress = function(filerow,filesize,session,progress) {
    if(session != $(filerow).data('session')) return;

	if(typeof filesize == 'undefined') filesize = 1000000;
	var ob = $('.ui-progressbar-value', filerow);
	if (ob.hasClass('completed')) return;

    var stepWait = Math.pow(1.2,Math.log(filesize/1000)/Math.LN10 - 1)/30;

	progress++;

	ob.css('width', progress+'%');
	if (progress > 98) {
		return;
	}

	setTimeout(function(){
        testProgress(filerow,filesize,session,progress);
    }, Math.round(stepWait*1000));
}

checkInit = function () {
    setTimeout( function () {
        if ( $( 'body' ).hasClass( 'initialized' ) ) {

            UI.checkFailedConversionsNumber();

            checkConversions();
            return;
        } else {
            checkInit();
        }
        ;
    }, 100 );
};

checkAnalyzability = function ( who ) {

    if ( $( '.upload-table tr:not(.failed)' ).length ) {
        var res = true;
        $( '.upload-table tr:not(.failed)' ).each( function () {
            if ( $( this ).hasClass( 'converting' ) ) {
                res = false;
            }
            if ( !$( this ).hasClass( 'ready' ) ) {
                res = false;
            }
            var filename = $( this ).find( '.name' ).text();
            if ( filename.split( '.' )[filename.split( '.' ).length - 1].toLowerCase() == 'tmx' ) {
                $( this ).addClass( 'tmx' );
            }

        } );
        if ( !$( '.upload-table tr:not(.failed, .tmx)' ).length ) {
            return false;
        }
        if ( $( '.upload-table tr.failed' ).length ) res = false;
        if ( UI.uploadingTMX() ) {
            res = false;
        }
        return res;
    } else {
        return false;
    }
    ;
}

isValidFileExtension = function ( filename ) {

    console.log( 'filename: ' + filename );
    ext = filename.split( '.' )[filename.split( '.' ).length - 1];
    res = (!filename.match( config.allowedFileTypes )) ? false : true;

    console.log( res );
    return res;
}

isTMXAllowed = function () {
    filename = "test.tmx";
    res = (!filename.match( config.allowedFileTypes )) ? false : true;

    console.log( "function isTMXAllowed return value: " + res );
    return res;
}

enableAnalyze = function () {
    $( '.uploadbtn' ).removeAttr( 'disabled' ).removeClass( 'disabled' ).focus();
}

disableAnalyze = function () {
    $( '.uploadbtn' ).attr( 'disabled', 'disabled' ).addClass( 'disabled' );
}

setFileReadiness = function () {
    $( '.upload-table tr' ).each( function () {
        if ( !$( this ).hasClass( 'converting' ) ) $( this ).addClass( 'ready' );
    } )
}

checkConversions = function () {
    console.log( 'check conversions' );
    if ( !config.conversionEnabled ) return;
    $( '.upload-table tr:not(.has-errors)' ).each( function () {

        var name = $( '.name', this ).text();
        var extension = name.split( '.' )[name.split( '.' ).length - 1];

        $.ajax( {
            url: '?action=checkFileConversion',
            data: {
                file_name: name
            },
            type: 'POST',
            dataType: 'json',
            context: $( this ),
            success: function ( d ) {
                if ( d.converted == '1' ) {
                    console.log( d.file_name + ' già convertito' );
                } else {
                    console.log( d.file_name + ' non ancora convertito' );
                    var filename = d.file_name;
                    var filerow = this;
                    if ( filerow.hasClass( 'converting' ) ) return;
                    console.log( 'ACTION: success of checkConversions' );
                    convertFile( filename, filerow );


                    if ( $( '.upload-table tr' ).length ) {
                        $( '.upload-files' ).addClass( 'uploaded' );
                    } else {
                        $( '.upload-files' ).removeClass( 'uploaded' );
                    }
                }
            }
        } );

    } )
};

unsupported = function () {
    var jj = $( '<div/>' ).html( config.unsupportedFileTypes ).text();
    return $.parseJSON( jj );
};

function goodbye( e ) {
    if ( $( '.popup-tm .notify' ).length ) {
        var dont_confirm_leave = 0; //set dont_confirm_leave to 1 when you want the user to be able to leave withou confirmation
        var leave_message = 'You have a pending operation. Are you sure you want to quit?';
        if ( dont_confirm_leave !== 1 ) {
            if ( !e ) e = window.event;
            //e.cancelBubble is supported by IE - this will kill the bubbling process.
            e.cancelBubble = true;
            e.returnValue = leave_message;
            //e.stopPropagation works in Firefox.
            if ( e.stopPropagation ) {
                e.stopPropagation();
                e.preventDefault();
            }

        }

        //return works for Chrome and Safari
        clearNotCompletedUploads();

        return leave_message;
    }
}

getIconClass = function ( ext ) {
    c = (
            (ext == 'doc') ||
            (ext == 'dot') ||
            (ext == 'docx') ||
            (ext == 'dotx') ||
            (ext == 'docm') ||
            (ext == 'dotm') ||
            (ext == 'odt') ||
            (ext == 'sxw')
    ) ? 'extdoc' :
            (
            (ext == 'pot') ||
            (ext == 'pps') ||
            (ext == 'ppt') ||
            (ext == 'potm') ||
            (ext == 'potx') ||
            (ext == 'ppsm') ||
            (ext == 'ppsx') ||
            (ext == 'pptm') ||
            (ext == 'pptx') ||
            (ext == 'odp') ||
            (ext == 'sxi')
    ) ? 'extppt' :
            (
                    (ext == 'htm') ||
                    (ext == 'html')
            ) ? 'exthtm' :
                    (ext == 'pdf') ? 'extpdf' :
                            (
            (ext == 'xls') ||
            (ext == 'xlt') ||
            (ext == 'xlsm') ||
            (ext == 'xlsx') ||
            (ext == 'xltx') ||
            (ext == 'ods') ||
            (ext == 'sxc') ||
            (ext == 'csv')
            ) ? 'extxls' :
            (ext == 'txt') ? 'exttxt' :
            (ext == 'ttx') ? 'extttx' :
            (ext == 'itd') ? 'extitd' :
            (ext == 'xlf') ? 'extxlf' :
            (ext == 'mif') ? 'extmif' :
            (ext == 'idml') ? 'extidd' :
            (ext == 'xtg') ? 'extqxp' :
            (ext == 'xml') ? 'extxml' :
            (ext == 'rc') ? 'extrcc' :
            (ext == 'resx') ? 'extres' :
            (ext == 'sgml') ? 'extsgl' :
            (ext == 'sgm') ? 'extsgm' :
            (ext == 'properties') ? 'extpro' :
            (ext == 'zip') ? 'extzip' :
            'extxif';
    return c;
}

window.onbeforeunload = function ( e ) {

    var leave_message = null;
    if ( $( '.popup-tm .notify' ).length ) {

        var dont_confirm_leave = 0; //set dont_confirm_leave to 1 when you want the user to be able to leave withou confirmation
        leave_message = 'You have a pending operation. Are you sure you want to quit?';
        if ( dont_confirm_leave !== 1 ) {
            if ( !e ) e = window.event;
            //e.cancelBubble is supported by IE - this will kill the bubbling process.
            e.cancelBubble = true;
            e.returnValue = leave_message;
            //e.stopPropagation works in Firefox.
            if ( e.stopPropagation ) {
                e.stopPropagation();
                e.preventDefault();
            }

        }

    }

    //return works for Chrome and Safari
    //function in new-project.js this function does an ajax call to clean uploaded files when an user
    // refresh a page without click the analyze method
    clearNotCompletedUploads();

    if ( leave_message != null ) {
        return leave_message;
    }

};

$( document ).ready( function () {
    config.unsupported = unsupported();
    checkInit();
    UI.init();
} );
