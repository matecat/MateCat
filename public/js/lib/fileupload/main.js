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

$(function () {
    'use strict';

    // Initialize the jQuery File Upload widget:
    $('#fileupload').fileupload();

    // Enable iframe cross-domain access via redirect option:
    $('#fileupload').fileupload(
        'option',
        'redirect',
        window.location.href.replace(
            /\/[^\/]*$/,
            '/cors/result.html?%s'
        )
    );
    var dropzone = $('#overlay');

	$(document).bind('drop dragover', function (e) {
	    e.preventDefault();
	});
    $('#fileupload').fileupload(
        'option',
	    {
	        dropZone: $('.drag'),
	        autoUpload: true,
	        singleFileUploads: false, 
	        acceptFileTypes: /(\.|\/)(xliff|sdlxliff)$/i
	    }
    );
	$('#fileupload').bind('fileuploaddragover', function (e) {
		$('.upload-files').addClass('dragging');
        dropzone.show();
	}).bind('fileuploadadd', function (e, data) {
		if(data.files.length > 1) {
			$('#fileupload').bind('fileuploadsend.preventMore', function (e) {
				$('table.upload-table tbody').empty();
				alert('Actually only one file for each project can be uploaded. Please retry.');
				$('#fileupload').unbind('fileuploadsend.preventMore');
				return false;
			});
		};
	}).bind('fileuploadstart', function (e) {
		if(!$.cookie("upload_session")) $.cookie("upload_session",uploadSessionId);
	}).bind('fileuploaddrop', function (e) {
		$('.upload-files').removeClass('dragging dnd-hover');
        dropzone.hide();
	}).bind('fileuploaddone', function (e) {
		$('.upload-files').addClass('uploaded');
	}).bind('fileuploadcompleted fileuploaddestroyed', function (e) {
		if($('.upload-table tr').length) {
			$('.upload-files').addClass('uploaded');
			$('.uploadbtn').removeAttr('disabled').removeClass('disabled');
		} else {
			$('.upload-files').removeClass('uploaded');
			$('.uploadbtn').attr('disabled','disabled').addClass('disabled');
		}
	});

	$('.upload-files').bind('dragleave', function (e) {
		$(this).removeClass('dragging');
	});

/*
    $('[draggable="true"]').on('dragstart', function() {
    	console.log('start');
//        dropzone.show();
    })
*/
/*
    $('[draggable="true"]').on('dragstart', function() {
    	console.log('start');
        dropzone.show();
    }).on('dragend', function() {
    	console.log('stop');
        dropzone.hide();
    });
*/
/*
    dropzone.on('dragenter', function(event) {
        $('.upload-files').addClass('dnd-hover');
    });

    dropzone.on('dragleave', function(event) {
        $('.upload-files').removeClass('dnd-hover');
    });
*/


    $('[draggable="true"]').on('dragend', function() {
        dropzone.hide();
    });

    dropzone.on('dragenter', function(event) {
        $('.upload-files').addClass('dnd-hover');
    });

    dropzone.on('dragleave', function(event) {
        $('.upload-files').removeClass('dnd-hover');
    });




    if (window.location.hostname === 'blueimp.github.com') {
        // Demo settings:
        $('#fileupload').fileupload('option', {
            url: '//jquery-file-upload.appspot.com/',
            maxFileSize: 5000000,
            acceptFileTypes: /(\.|\/)(gif|jpe?g|png)$/i,
            process: [
                {
                    action: 'load',
                    fileTypes: /^image\/(gif|jpeg|png)$/,
                    maxFileSize: 20000000 // 20MB
                },
                {
                    action: 'resize',
                    maxWidth: 1440,
                    maxHeight: 900
                },
                {
                    action: 'save'
                }
            ]
        });
        // Upload server status check for browsers with CORS support:
        if ($.support.cors) {
            $.ajax({
                url: '//jquery-file-upload.appspot.com/',
                type: 'HEAD'
            }).fail(function () {
                $('<span class="alert alert-error"/>')
                    .text('Upload server currently unavailable - ' +
                            new Date())
                    .appendTo('#fileupload');
            });
        }
    } else {
        // Load existing files:
        $('#fileupload').each(function () {
            var that = this;
            $.getJSON(this.action, function (result) {
                if (result && result.length) {
                    $(that).fileupload('option', 'done')
                        .call(that, null, {result: result});
                }
            });
        });
    }

    // Initialize the Image Gallery widget:
//    $('#fileupload .files').imagegallery();

    // Initialize the theme switcher:
    $('#theme-switcher').change(function () {
        var theme = $('#theme');
        theme.prop(
            'href',
            theme.prop('href').replace(
                /[\w\-]+\/jquery-ui.css/,
                $(this).val() + '/jquery-ui.css'
            )
        );
    });

});
