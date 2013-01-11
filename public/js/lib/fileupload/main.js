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
    var langCorrections = [];

	$(document).bind('drop dragover', function (e) {
	    e.preventDefault();
	});
    $('#fileupload').fileupload(
        'option',
	    {
	        dropZone: $('.drag'),
	        autoUpload: true,
	        singleFileUploads: true,
	        overlayClose: true,
	        fileInput: $('#fileupload .multiple-button, .btncontinue .multiple-button'),
	        acceptFileTypes: /(\.|\/)(xliff|sdlxliff|xlf)$/i
	    }
    );
	$('#fileupload').bind('fileuploaddragover', function (e) {
		$('.upload-files').addClass('dragging');
        dropzone.show();
	}).bind('fileuploadadd', function (e, data) {
		$('#fileupload table.upload-table tr').addClass('current');

/*
		if(data.files.length > 1) {
			$('#fileupload').bind('fileuploadsend.preventMore', function (e) {
				$('table.upload-table tbody').empty();
				alert('Actually only one file for each project can be uploaded. Please retry.');
				$('#fileupload').unbind('fileuploadsend.preventMore');
				return false;
			});
		};
*/
	}).bind('fileuploadstart', function (e) {
//		if(!$.cookie("upload_session")) $.cookie("upload_session",uploadSessionId);
	}).bind('fileuploadcomplete', function (e) {
//		if(!$.cookie("upload_session")) $.cookie("upload_session",uploadSessionId);
	}).bind('fileuploaddrop', function (e) {
		$('.upload-files').addClass('uploaded');
		$('.upload-files').removeClass('dragging dnd-hover');
        dropzone.hide();
	}).bind('fileuploaddone', function (e,data) {

/*
		$('.upload-files').addClass('uploaded');
//		console.log(data.result);

		var userSourceLang = $('#source-lang').val();
		var userTargetLang = $('#target-lang').val();
		var sourceMismatch = [];
		var targetMismatch = [];

		$.each(data.result, function () {
			if(this.internal_source_lang != userSourceLang) {
				sourceMismatch.push({name: this.name, source_lang: this.internal_source_lang});
			};
			if(this.internal_target_lang != userTargetLang) {
				targetMismatch.push({name: this.name, target_lang: this.internal_target_lang});
			};
        });

        if((sourceMismatch.length)||(targetMismatch.length)) {
	        var mismatchResponse = '<form action=""><h2>Language mismatch. Please select the correct language pair.</h2>';

	        if(sourceMismatch.length) {
	        	mismatchResponse += '<div class="sourceColumn"><h3 class="sourcemessage">Source</h3><div class="sourcelist"><div class="lang user selected"><input type="radio" checked="checked" name="sourcelang"><span class="title">' + userLangName('source', userSourceLang) + ' <span class="code">(' + userSourceLang + ')</span></span><ul><li>User defined</li></ul></div>';
	        	
//	        	mismatchResponse += '<h2>Language mismatch. Please select the correct language pair.</h2><div class="sourceColumn"><h3 class="sourcemessage">Source</h3><ul class="sourcelist"><li><a class="user lang" href="#" data-type="source">' + userSourceLang + '</a>, as you specified?</li>';

	        	$.each(sourceMismatch, function () {
	        		mismatchResponse += '<div class="lang" data-langcode="' + this.source_lang + '"><input type="radio" name="sourcelang"><span class="title">' + userLangName('source', this.source_lang) + ' <span class="code">(' + this.source_lang + ')</span></span><ul><li>' + this.name + '</li></ul></div>';
	        	});

//	        	$.each(sourceMismatch, function () {
//	        		mismatchResponse += '<li><a class="file" href="#" data-type="source" data-file="' + this.name + '">' + this.source_lang + '</a>, as internally reported in the file <strong>'+ this.name + '</strong>?</li>';
//	        	});

	        	mismatchResponse += '</div></div>';
	        }

	        if(targetMismatch.length) {
	        	mismatchResponse += '<div class="targetColumn"><h3 class="targetmessage">Target</h3><div class="targetlist"><div class="lang user selected"><input type="radio" checked="checked" name="targetlang"><span class="title">' + userLangName('target', userTargetLang) + ' <span class="code">(' + userTargetLang + ')</span></span><ul><li>User defined</li></ul></div>';       	
//	        	mismatchResponse += '<div class="targetColumn"><h3 class="targetmessage">Target</h3><ul class="targetlist"><li><a class="user" href="#" data-type="target">' + userTargetLang + '</a>, as you specified?</li>';

	        	$.each(targetMismatch, function () {
	        		mismatchResponse += '<div class="lang" data-langcode="' + this.target_lang + '"><input type="radio" name="targetlang"><span class="title">' + userLangName('target', this.target_lang) + ' <span class="code">(' + this.target_lang + ')</span></span><ul><li>' + this.name + '</li></ul></div>';
	        	});

//	        	$.each(targetMismatch, function () {
//	        		mismatchResponse += '<li><a class="file" href="#" data-type="target" data-file="' + this.name + '">' + this.target_lang + '</a>, as internally reported in the file <strong>'+ this.name + '</strong>?</li>';
//	        	});


	        	mismatchResponse += '</ul></div>';
	        }

	        mismatchResponse += '<p><input id="changeLanguage" type="button" value="Apply" name=""></p></form>';

	        $.colorbox({open: true, width:"50%", html:mismatchResponse});

	        $('#cboxLoadedContent .sourcelist input[name=sourcelang]:radio').bind('change', function () {
		        	$('#cboxLoadedContent .sourcelist .selected').removeClass('selected');
		        	$(this).parents('div.lang').addClass('selected');
	        });

	        $('#cboxLoadedContent .targetlist input[name=targetlang]:radio').bind('change', function () {
		        	$('#cboxLoadedContent .targetlist .selected').removeClass('selected');
		        	$(this).parents('div.lang').addClass('selected');
	        });

	        $('#cboxLoadedContent a').bind('click', function (e) {
            	e.preventDefault();
            	if($(this).hasClass('user')) {
            		if($(this).data('type') == 'source') {
	            		// aggiungi in coda di modifica tutti i file elencati nel mismatch di source, se non sono già presenti
	            		$('#cboxLoadedContent .sourcelist a.file').each(function () {
	            			if($.inArray($(this).data('file'),langCorrections) < 0) {
	            				langCorrections.push($(this).data('file'));
	            			};
				        });
	            		$('#cboxLoadedContent .sourcelist').remove();
	            		$('#cboxLoadedContent .sourcemessage').after('<p>The Source Language is now set to <strong>' + $(this).text() + '</strong><p>');
            		} else if ($(this).data('type') == 'target') {

	            		// aggiungi in coda di modifica tutti i file elencati nel mismatch di target, se non sono già presenti
	            		$('#cboxLoadedContent .targetlist a.file').each(function () {
	            			if($.inArray($(this).data('file'),langCorrections) < 0) {
	            				langCorrections.push($(this).data('file'));
	            			};
				        });
				        $('#cboxLoadedContent .targetlist').remove();
	            		$('#cboxLoadedContent .targetmessage').after('<p>The Target Language is now set to <strong>' + $(this).text() + '</strong><p>');
            		}
            	} else {
            		if($(this).data('type') == 'source') {
	            		// modifica il valore della select di source
	            		$('#source-lang').val($(this).text());
	            		// aggiungi in coda di modifica tutti gli altri file elencati nel mismatch di source, se non sono già presenti
	            		var clickedLang = $(this).text();
	            		var clickedFile = $(this).data('file');
	            		$('#cboxLoadedContent .sourcelist a.file').each(function () {
	            			if($(this).text() != clickedLang) {
		            			if($.inArray($(this).data('file'),langCorrections) < 0) {
		            				langCorrections.push($(this).data('file'));
		            			};
	            			};
				        });
	            		// aggiungi in coda di modifica tutti gli altri file eventualmente caricati in precedenza, se non sono già presenti	
	            		$('#fileupload table.upload-table tr:not(.current) td.name').each(function () {
	            			if($(this).text() != clickedFile) {
		            			if($.inArray($(this).text(),langCorrections) < 0) {
		            				langCorrections.push($(this).text());
		            			};
	            			};
				        });

	            		$('#cboxLoadedContent .sourcelist').remove();
	            		$('#cboxLoadedContent .sourcemessage').after('<p>The Source Language is now set to <strong>' + $(this).text() + '</strong><p>');

            		} else if ($(this).data('type') == 'target') {
	            		// modifica il valore della select di target
	            		$('#target-lang').val($(this).text());
	            		// aggiungi in coda di modifica tutti gli altri file elencati nel mismatch di target, se non sono già presenti
	            		var clickedLang = $(this).text();
	            		$('#cboxLoadedContent .targetlist a.file').each(function () {
	            			if($(this).text() != clickedLang) {
		            			if($.inArray($(this).data('file'),langCorrections) < 0) {
		            				langCorrections.push($(this).data('file'));
		            			};
		            		};
				        });
	            		// aggiungi in coda di modifica tutti gli altri file eventualmente caricati in precedenza, se non sono già presenti
	            		$('#fileupload table.upload-table tr:not(.current) td.name').each(function () {
	            			if($(this).text() != clickedFile) {
		            			if($.inArray($(this).text(),langCorrections) < 0) {
		            				langCorrections.push($(this).text());
		            			};
	            			};
				        });

	            		$('#cboxLoadedContent .targetlist').remove();
	            		$('#cboxLoadedContent .targetmessage').after('<p>The Target Language is now set to <strong>' + $(this).text() + '</strong><p>');
            		}
            	}
            	console.log(langCorrections);
            	$('#fileupload table.upload-table tr.current').removeClass('current');
            	// applica la coda di modifiche




			})

			$('#changeLanguage').bind('click', function (e) {
            	$.each(langCorrections, function () {
			        $.ajax({
			            url: window.location.href,
			            data: {
			                action: 'changeInternalLanguage',
			                file_name: this,
			                source_language: $('#source-lang').val(),
			                target_language: $('#target-lang').val()
			            },
			            type: 'POST',            
			            dataType: 'json',
			            beforeSend: function (){
//			            	$('.uploadbtn').attr('value','Analizing...').attr('disabled','disabled');
			            },
			            complete: function (){
			            },
			            success: function(d){
			         		if(d.data == 'OK') {
			            		$.colorbox.close();
			            		langCorrections = [];
			         		};

//			            	if(d.code == 1) {
//			            		$.colorbox.close();
//			            	}

//			            	console.log(d);
//		            		console.log(d.password + ' - ' + d.job_id);
			            }
			        });

		        });
			})

        }

*/
/*        
        if((sourceMismatch.length)||(targetMismatch.length)) {
	        var mismatchResponse = '<p>The uploaded files have the following language mismatches:</p>';
	        if(sourceMismatch.length) {
	        	mismatchResponse += '<br><p>You specified "' + userSourceLang + '" as Source language, while:</p><ul>';
	        	$.each(sourceMismatch, function () {
	        		mismatchResponse += '<li>The file '+ this.name + ' has internally set "' + this.source_lang + '" as its source language</li>';
	        	});
	        	mismatchResponse += '</ul>';
	        }
	        if(targetMismatch.length) {
	        	mismatchResponse += '<br><p>You specified "' + userTargetLang + '" as Source language, while:</p><ul>';
	        	$.each(sourceMismatch, function () {
	        		mismatchResponse += '<li>The file '+ this.name + ' has internally set "' + this.target_lang + '" as its source language</li>';
	        	});
	        	mismatchResponse += '</ul>';
	        }
	        $.colorbox({open: true, width:"50%", html:mismatchResponse});        	
        }
*/


	}).bind('fileuploadadded fileuploaddestroyed', function (e) {
		if($('.upload-table tr').length) {
			$('.upload-files').addClass('uploaded');
		} else {
			$('.upload-files').removeClass('uploaded');
		}
	}).bind('fileuploadfail', function (e) {
		if(!($('.upload-table tr').length > 1)) $('.upload-files').removeClass('uploaded');
	}).bind('fileuploadcompleted fileuploaddestroyed', function (e,data) {
//		var err = $.parseJSON(data.jqXHR.responseText)[0].error;
		if($('.upload-table tr').length) {
			$('.uploadbtn').removeAttr('disabled').removeClass('disabled').focus();
//			if(typeof err == 'undefined') $('.uploadbtn').removeAttr('disabled').removeClass('disabled').focus();
		} else {
			$('.uploadbtn').attr('disabled','disabled').addClass('disabled');
		}
	}).bind('fileuploadcompleted', function (e) {
		if($('.upload-table tr').length) {
			$('.upload-files').addClass('uploaded');
		} else {
			$('.upload-files').removeClass('uploaded');
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

userLangName = function(t, userLangCode) {
	return $('#' + t + '-lang  option[value=\'' + userLangCode + '\']').text();
}



