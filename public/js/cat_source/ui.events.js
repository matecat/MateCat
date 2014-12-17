/*
	Component: ui.events 
 */
$.extend(UI, {
	bindShortcuts: function() {
		$("body").removeClass('shortcutsDisabled');
		$("body").on('keydown.shortcuts', null, UI.shortcuts.translate.keystrokes.standard, function(e) {
			e.preventDefault();
			$('.editor .translated').click();
//		}).bind('keydown', 'Meta+return', function(e) {
		}).on('keydown.shortcuts', null, UI.shortcuts.translate.keystrokes.mac, function(e) {
			e.preventDefault();
			console.log('funziona');
			$('.editor .translated').click();
		}).on('keydown.shortcuts', null, UI.shortcuts.translate_nextUntranslated.keystrokes.standard, function(e) {
			e.preventDefault();
			$('.editor .next-untranslated').click();
		}).on('keydown.shortcuts', null, UI.shortcuts.translate_nextUntranslated.keystrokes.mac, function(e) {
			e.preventDefault();
			$('.editor .next-untranslated').click();
		}).on('keydown.shortcuts', null, 'Ctrl+pageup', function(e) {
			e.preventDefault();
		}).on('keydown.shortcuts', null, UI.shortcuts.openNext.keystrokes.standard, function(e) {
			e.preventDefault();
			e.stopPropagation();
			UI.gotoNextSegment();
		}).on('keydown.shortcuts', null, UI.shortcuts.openNext.keystrokes.mac, function(e) {
			e.preventDefault();
			e.stopPropagation();
			UI.gotoNextSegment();
		}).on('keydown.shortcuts', null, UI.shortcuts.openPrevious.keystrokes.standard, function(e) {
			e.preventDefault();
			e.stopPropagation();
			UI.gotoPreviousSegment();
		}).on('keydown.shortcuts', null, UI.shortcuts.openPrevious.keystrokes.mac, function(e) {
			e.preventDefault();
			e.stopPropagation();
			UI.gotoPreviousSegment();
		}).on('keydown.shortcuts', null, UI.shortcuts.gotoCurrent.keystrokes.standard, function(e) {
			e.preventDefault();
			UI.pointToOpenSegment();
		}).on('keydown.shortcuts', null, UI.shortcuts.gotoCurrent.keystrokes.mac, function(e) {
			e.preventDefault();
			UI.pointToOpenSegment();
		}).on('keydown.shortcuts', null, UI.shortcuts.copySource.keystrokes.standard, function(e) {
			e.preventDefault();
			UI.copySource();
		}).on('keydown.shortcuts', null, UI.shortcuts.undoInSegment.keystrokes.standard, function(e) {
			e.preventDefault();
			UI.undoInSegment(segment);
			UI.closeTagAutocompletePanel();
		}).on('keydown.shortcuts', null, UI.shortcuts.undoInSegment.keystrokes.mac, function(e) {
			e.preventDefault();
			UI.undoInSegment(segment);
			UI.closeTagAutocompletePanel();
		}).on('keydown.shortcuts', null, UI.shortcuts.redoInSegment.keystrokes.standard, function(e) {
			e.preventDefault();
			UI.redoInSegment(segment);
		}).on('keydown.shortcuts', null, UI.shortcuts.redoInSegment.keystrokes.mac, function(e) {
			e.preventDefault();
			UI.redoInSegment(segment);
		}).on('keydown.shortcuts', null, UI.shortcuts.openSearch.keystrokes.standard, function(e) {
            if((UI.searchEnabled)&&($('#filterSwitch').length)) UI.toggleSearch(e);
		}).on('keydown.shortcuts', null, UI.shortcuts.openSearch.keystrokes.mac, function(e) {
            if((UI.searchEnabled)&&($('#filterSwitch').length)) UI.toggleSearch(e);
		});
	},
	unbindShortcuts: function() {
		$("body").off(".shortcuts").addClass('shortcutsDisabled');
	},
	setEvents: function() {
		this.bindShortcuts();
		$("body").on('keydown', null, 'ctrl+1', function(e) {
			e.preventDefault();
			active = $('.editor .submenu li.active');
			if(active.hasClass('tab-switcher-tm')) {
				tab = 'matches';
				$('.editor .tab.' + tab + ' .graysmall[data-item=1]').trigger('dblclick');
			} else if(active.hasClass('tab-switcher-al')) {
				tab = 'alternatives';								
				$('.editor .tab.' + tab + ' .graysmall[data-item=1]').trigger('dblclick');
			}
		}).on('keydown', null, 'ctrl+2', function(e) {
			e.preventDefault();
			active = $('.editor .submenu li.active');
			if(active.hasClass('tab-switcher-tm')) {
				tab = 'matches';
				$('.editor .tab.' + tab + ' .graysmall[data-item=2]').trigger('dblclick');		
			} else if(active.hasClass('tab-switcher-al')) {
				tab = 'alternatives';								
				$('.editor .tab.' + tab + ' .graysmall[data-item=2]').trigger('dblclick');
			}
		}).on('keydown', null, 'ctrl+3', function(e) {
			e.preventDefault();
			active = $('.editor .submenu li.active');
			if(active.hasClass('tab-switcher-tm')) {
				tab = 'matches';
				$('.editor .tab.' + tab + ' .graysmall[data-item=3]').trigger('dblclick');		
			} else if(active.hasClass('.tab-switcher-al')) {
				tab = 'alternatives';								
				$('.editor .tab.' + tab + ' .graysmall[data-item=3]').trigger('dblclick');
			}
		}).on('keydown', '.editor .editarea', 'shift+return', function(e) {
            UI.handleReturn(e);
        }).on('keydown', '.editor .editarea', 'return', function(e) {
            UI.handleReturn(e);
		}).on('keydown', '.editor .editarea', 'space', function(e) {
            if(UI.markSpacesEnabled) {
                if(!UI.hiddenTextEnabled) return;
                e.preventDefault();
                UI.editarea.find('.lastInserted').removeClass('lastInserted');
//			console.log('space');
                var node = document.createElement("span");
                node.setAttribute('class', 'marker monad space-marker lastInserted');
                node.setAttribute('contenteditable', 'false');
                node.textContent = htmlDecode(" ");
//			node.textContent = "&nbsp;";
                insertNodeAtCursor(node);
                UI.unnestMarkers();
            }

		}).on('keydown', '.editor .editarea', 'ctrl+shift+space', function(e) {
            if(!UI.hiddenTextEnabled) return;
			e.preventDefault();
            UI.editarea.find('.lastInserted').removeClass('lastInserted');
//			console.log('nbsp');
//			config.nbspPlaceholderClass = '_NBSP';
			var node = document.createElement("span");
			node.setAttribute('class', 'marker monad nbsp-marker lastInserted ' + config.nbspPlaceholderClass);
			node.setAttribute('contenteditable', 'false');
			node.textContent = htmlDecode("&nbsp;");
			insertNodeAtCursor(node);
			UI.unnestMarkers();
/*
			setCursorPosition($('.editor .editarea .lastInserted')[0]);
			console.log('a: ', UI.editarea.html());
			$('.editor .editarea .lastInserted').after($('.editor .editarea .undoCursorPlaceholder'));
			console.log('b: ', UI.editarea.html());
			$('.editor .editarea .lastInserted').removeClass('lastInserted');
			console.log('c: ', UI.editarea.html());
*/
        });
		$("body").bind('keydown', 'Ctrl+c', function() {
			UI.tagSelection = false;
		}).bind('keydown', 'Meta+shift+l', function() {
            UI.openLanguageResourcesPanel();
        }).bind('keydown', 'Meta+c', function() {
			UI.tagSelection = false;
        }).bind('keydown', 'Meta+shift+s', function(e) {
//            e.preventDefault();
            UI.body.toggleClass('tagmode-default-extended');
        }).on('click', '.tagModeToggle', function(e) {
            e.preventDefault();
            console.log('click su tagMode toggle');
            $(this).toggleClass('active');
            UI.body.toggleClass('tagmode-default-extended');
            console.log(typeof UI.currentSegment);
            if(typeof UI.currentSegment != 'undefined') UI.pointToOpenSegment(true);

//		}).bind('keydown', 'Backspace', function(e) {

//		}).on('click', '#messageBar .close', function(e) {
//			e.preventDefault();
//			$('body').removeClass('incomingMsg');
//			var expireDate = new Date($('#messageBar').attr('data-expire'));
//			$.cookie($('#messageBar').attr('data-token'), '', { expires: expireDate });		
					
//		}).on('change', '#hideAlertConfirmTranslation', function(e) {
//			console.log($(this).prop('checked'));
//			if ($(this).prop('checked')) {
//				console.log('checked');
//				UI.alertConfirmTranslationEnabled = false;
//				$.cookie('noAlertConfirmTranslation', true, {expires: 1000});
//			} else {
//				console.log('unchecked');
//				UI.alertConfirmTranslationEnabled = true;
//				$.removeCookie('noAlertConfirmTranslation');
//			}
		}).on('click', '#settingsSwitcher', function(e) {
			e.preventDefault();
			UI.unbindShortcuts();
			$('.popup-settings').show();

        // start addtmx
        }).on('click', '.open-popup-addtm-tr', function(e) {
            e.preventDefault();
            UI.openLanguageResourcesPanel();
//            $('.popup-addtm-tr').show();
        }).on('click', '#addtm-create-key', function(e) {
            e.preventDefault();
            //prevent double click
            if($(this).hasClass('disabled')) return false;
            $(this).addClass('disabled');
            $(this).attr('disabled','');
//            $.get("https://mymemory.translated.net/api/createranduser",function(data){
//                //parse to appropriate type
//                //this is to avoid a curious bug in Chrome, that causes 'data' to be already an Object and not a json string
//                if(typeof data == 'string'){
//                    data=jQuery.parseJSON(data);
//                }
//                //put value into input field
//                $('#addtm-tr-key').val(data.key);
//                $('#addtm-create-key').removeClass('disabled');
//                setTimeout(function() {
//                    UI.checkAddTMEnable();
//                    UI.checkManageTMEnable();
//                }, 100);
////                $('#private-tm-user').val(data.id);
////                $('#private-tm-pass').val(data.pass);
////                $('#create_private_tm_btn').attr('data-key', data.key);
//                return false;
//            });

            //call API
            APP.doRequest({
                data: {
                    action: 'createRandUser'
                },
                success: function(d) {
                    //put value into input field
                    $('#addtm-tr-key').val( d.data.key);
                    $('#addtm-create-key').removeClass('disabled');
                    setTimeout(function() {
                        UI.checkAddTMEnable();
                        UI.checkManageTMEnable();
                    }, 100);
                    //$('#private-tm-user').val(data.id);
                    //$('#private-tm-pass').val(data.pass);
                    //$('#create_private_tm_btn').attr('data-key', data.key);
                    return false;
                }
            });

        }).on('change', '#addtm-tr-read, #addtm-tr-write', function() {
            if(UI.checkTMgrants($('.addtm-tr'))) {
                $('.addtm-tr .error-message').hide();
            }
        }).on('change', '#addtm-tr-key-read, #addtm-tr-key-write', function() {
            if(UI.checkTMgrants($('.addtm-tr-key'))) {
                $('.addtm-tr-key .error-message').hide();
            }
        }).on('change', '.addtm-select-file', function() {
/*
            $('.addtm-tr .warning-message').hide();
            if($('#addtm-tr-key').val() == '') {
                $('#addtm-create-key').click();
                $('.addtm-tr .warning-message').show();
                setTimeout(function() {
                    UI.checkAddTMEnable();
                }, 500);
            }
*/
/*
        }).on('click', '.addtm-tr-key .btn-ok', function() {
            if(!UI.checkTMgrants($('.addtm-tr-key'))) {
                return false;
            } else {
                $('.addtm-tr-key .error-message').text('').hide();
            }
            UI.checkTMKey($('#addtm-tr-key-key').val(), 'key');
*/
        }).on('click', '#addtm-select-file', function() {
            $('.addtm-select-file').click();
        }).on('change', '.addtm-select-file', function() {
            console.log($(this).val());
            if($(this).val() !== '') {
                $('#uploadTMX').html($(this).val().split('\\')[$(this).val().split('\\').length - 1] + '<a class="delete"></a>').show();
            } else {
                $('#uploadTMX').hide();
            }
        }).on('change', '#addtm-tr-key', function() {
            $('.addtm-tr .warning-message').hide();
        }).on('input', '#addtm-tr-key', function() {
            UI.checkAddTMEnable();
            UI.checkManageTMEnable();
        }).on('change', '#addtm-tr-key, .addtm-select-file, #addtm-tr-read, #addtm-tr-write', function() {
            UI.checkAddTMEnable();
/*
        }).on('change', '#addtm-tr-key, .addtm-tr input:file, .addtm-tr input.r, .addtm-tr input.w', function(e) {
            UI.checkAddTMEnable($('#addtm-add'));
        }).on('change', '#addtm-tr-key-key', function(e) {
            UI.checkAddTMEnable($('.addtm-tr-key .btn-ok'));
        }).on('click', '#addtm-tr-key-read, #addtm-tr-key-write', function(e) {
            UI.checkAddTMEnable($('.addtm-tr-key .btn-ok'));
*/
        }).on('click', '#uploadTMX .delete', function(e) {
            e.preventDefault();
            $('#uploadTMX').html('');
            $('.addtm-select-file').val('');
        }).on('click', '#addtm-add', function(e) {
            e.preventDefault();
            if(!UI.checkTMgrants($('.addtm-tr'))) {
                return false;
            } else {
                console.log('vediamo qui');
                $('.addtm-tr .error-message').text('').hide();
                console.log('CONTROLLO: ', $('#uploadTMX').text());
                operation = ($('#uploadTMX').text() === '')? 'key' : 'tm';
                UI.checkTMKey($('#addtm-tr-key').val(), operation);
//                if(UI.checkTMKey($('#addtm-tr-key').val(), 'tm')) fileUpload($('#addtm-upload-form')[0],'http://matecat.local/?action=addTM','upload');

            }


/*
// web worker implementation

            if(typeof(Worker) !== "undefined") {
                // Yes! Web worker support!

                var worker = new Worker('http://matecat.local/public/js/addtm.js');
                worker.onmessage = function(e) {
                    alert(e.data);
                }
                worker.onerror =werror;

                // Setup the dnd listeners.
                var dropZone = document.getElementById('drop_zone');
                dropZone.addEventListener('dragover', handleDragOver, false);
                dropZone.addEventListener('drop', handleFileSelect, false);
                document.getElementById('files').addEventListener('change', handleFileSelect, false);
            } else {
                // Sorry! No Web Worker support..
            }
*/

/*
            $('#addtm-add').addClass('disabled');

            //create an iFrame element
            var iFrameAddTM = $( document.createElement( 'iframe' ) ).hide().prop({
                id: 'iFrameAddTM',
                src: ''
            });

            //append iFrame to the DOM
            $("body").append( iFrameAddTM );
*/

/*
            //generate a token addTM
            var addTMToken = new Date().getTime();

            //set event listner, on ready, attach an interval that check for finished download
            iFrameAddTM.ready(function () {

                //create a GLOBAL setInterval so in anonymous function it can be disabled
                addTMTimer = window.setInterval(function () {

                    //check for cookie
                    var token = $.cookie('addTMToken');
                    console.log('TOKEN: ', token);

                    //if the cookie is found, download is completed
                    //remove iframe an re-enable download button
                    if ( token == addTMToken ) {
                        $('#addtm-add').removeClass('disabled').val( $('#addtm-add' ).data('oldValue') ).removeData('oldValue');
                        window.clearInterval( addTMTimer );
                        $.cookie('addTMToken', null, { path: '/', expires: -1 });
                        iFrameAddTM.remove();
                    }

                }, 2000);

            });

            //clone the html form and append a token for download
            var iFrameAddTMForm = $("#addTMForm").clone().append(
                $( document.createElement( 'input' ) ).prop({
                    type: 'hidden',
                    name: 'addTMToken',
                    value: addTMToken
                })
            );
*/
/*
            var iFrameAddTMForm = $("#addTMForm").clone();
            //append from to newly created iFrame and submit form post
            iFrameAddTM.contents().find('body').append( iFrameAddTMForm );
            console.log('vediamo:', iFrameAddTM.contents().find("#addTMForm"));
            iFrameAddTM.contents().find("#addTMForm").submit();
*/
            /*
                        //check if we are in download status
                        if ( !$('#downloadProject').hasClass('disabled') ) {

                            //disable download button
                            $('#downloadProject').addClass('disabled' ).data( 'oldValue', $('#downloadProject' ).val() ).val('DOWNLOADING...');

                            //create an iFrame element
                            var iFrameDownload = $( document.createElement( 'iframe' ) ).hide().prop({
                                id:'iframeDownload',
                                src: ''
                            });

                            //append iFrame to the DOM
                            $("body").append( iFrameDownload );

                            //generate a token download
                            var downloadToken = new Date().getTime();

                            //set event listner, on ready, attach an interval that check for finished download
                            iFrameDownload.ready(function () {

                                //create a GLOBAL setInterval so in anonymous function it can be disabled
                                downloadTimer = window.setInterval(function () {

                                    //check for cookie
                                    var token = $.cookie('downloadToken');

                                    //if the cookie is found, download is completed
                                    //remove iframe an re-enable download button
                                    if ( token == downloadToken ) {
                                        $('#downloadProject').removeClass('disabled').val( $('#downloadProject' ).data('oldValue') ).removeData('oldValue');
                                        window.clearInterval( downloadTimer );
                                        $.cookie('downloadToken', null, { path: '/', expires: -1 });
                                        iFrameDownload.remove();
                                    }

                                }, 2000);

                            });

                            //clone the html form and append a token for download
                            var iFrameForm = $("#fileDownload").clone().append(
                                $( document.createElement( 'input' ) ).prop({
                                    type:'hidden',
                                    name:'downloadToken',
                                    value: downloadToken
                                })
                            );

                            //append from to newly created iFrame and submit form post
                            iFrameDownload.contents().find('body').append( iFrameForm );
                            iFrameDownload.contents().find("#fileDownload").submit();

                        } else {
                            //we are in download status
                        }
             */
 /*
            APP.doRequest({
                data: {
                    action: 'addTM',
                    job_id: config.job_id,
                    job_pass: config.password,
                    tm_key: $('#addtm-tr-key').val(),
                    name: $('#addtm-tr-name').val(),
                    tmx_file: ''
                },
                error: function() {
                    console.log('addTM error!!');
                },
                success: function(d) {
                    console.log('addTM success!!');
                }
            });
*/
        // end addtmx

		}).on('click', '.popup-settings #settings-restore', function(e) {
			e.preventDefault();
			APP.closePopup();
		}).on('click', '.popup-settings #settings-save', function(e) {
			e.preventDefault();
			APP.closePopup();
        }).on('click', '.modal .x-popup', function() {
			if($('body').hasClass('shortcutsDisabled')) {
				UI.bindShortcuts();
			}
		}).on('click', '.popup-settings .x-popup', function() {
			console.log('close');
		}).on('click', '.popup-settings .submenu li', function(e) {
			e.preventDefault();
			$('.popup-settings .submenu li.active').removeClass('active');
			$(this).addClass('active');
			$('.popup-settings .tab').hide();
			$('#' + $(this).attr('data-tab')).show();
//			console.log($(this).attr('data-tab'));
		}).on('click', '.popup-settings .submenu li a', function(e) {
			e.preventDefault();
		}).on('click', '#settings-shortcuts .list .combination .keystroke', function() {
			$('#settings-shortcuts .list .combination .msg').remove();
			$('#settings-shortcuts .list .combination .keystroke.changing').removeClass('changing');
			$(this).toggleClass('changing').after('<span class="msg">New: </span>');
			$('#settings-shortcuts').addClass('modifying');
		}).on('click', '#settings-shortcuts #default-shortcuts', function(e) {
			e.preventDefault();
			$('#settings-shortcuts .list').remove();
			UI.setShortcuts();
			$('.popup-settings .submenu li[data-tab="settings-shortcuts"]').removeClass('modified');	
		}).on('click', '#spellCheck .words', function(e) {
			e.preventDefault();
			UI.selectedMisspelledElement.replaceWith($(this).text());
			UI.closeContextMenu();
		}).on('click', '#spellCheck .add', function(e) {
			e.preventDefault();
			UI.closeContextMenu();
			UI.addWord(UI.selectedMisspelledElement.text());
		}).on('click', '.reloadPage', function() {
			location.reload(true);
		}).on('click', '.tag-autocomplete li', function(e) {
			e.preventDefault();
//			UI.editarea.html(UI.editarea.html().replace(/&lt;[&;"\w\s\/=]*?(\<span class="tag-autocomplete-endcursor"\>)/gi, '$1'));
//			UI.editarea.html(UI.editarea.html().replace(/&lt;(?:[a-z]*&nbsp;*(["\w\s\/=]*?))?(\<span class="tag-autocomplete-endcursor"\>)/gi, '$2'));

            UI.editarea.find('.rangySelectionBoundary').before(UI.editarea.find('.rangySelectionBoundary + .tag-autocomplete-endcursor'));
            UI.editarea.html(UI.editarea.html().replace(/&lt;(?:[a-z]*(?:&nbsp;)*["<\->\w\s\/=]*)?(<span class="tag-autocomplete-endcursor">)/gi, '$1'));
//            UI.editarea.html(UI.editarea.html().replace(/&lt;(?:[a-z]*(?:&nbsp;)*["\w\s\/=]*)?(<span class="tag-autocomplete-endcursor"\>)/gi, '$1'));
            UI.editarea.html(UI.editarea.html().replace(/&lt;(?:[a-z]*(?:&nbsp;)*["\w\s\/=]*)?(<span class="undoCursorPlaceholder monad" contenteditable="false"><\/span><span class="tag-autocomplete-endcursor"\>)/gi, '$1'));
			saveSelection();
			if(!$('.rangySelectionBoundary', UI.editarea).length) { // click, not keypress
//				console.log('qui: ', document.getElementsByClassName("tag-autocomplete-endcursor")[0]);
				setCursorPosition(document.getElementsByClassName("tag-autocomplete-endcursor")[0]);
				saveSelection();
			}
//			console.log($('.rangySelectionBoundary', UI.editarea)[0]);
//			console.log('c: ', UI.editarea.html());
			var ph = $('.rangySelectionBoundary', UI.editarea)[0].outerHTML;
//			console.log('ph: ', ph);
			$('.rangySelectionBoundary', UI.editarea).remove();
//			console.log('d: ', UI.editarea.html());
//			console.log($('.tag-autocomplete-endcursor', UI.editarea));
			$('.tag-autocomplete-endcursor', UI.editarea).after(ph);
//			setCursorPosition(document.getElementsByClassName("tag-autocomplete-endcursor")[0]);
//			console.log('e: ', UI.editarea.html());
			$('.tag-autocomplete-endcursor').before(htmlEncode($(this).text()));
//			console.log('f: ', UI.editarea.html());
			restoreSelection();
			UI.closeTagAutocompletePanel();
			UI.lockTags(UI.editarea);
			UI.currentSegmentQA();
		}).on('click', '.modal.survey .x-popup', function() {
			UI.surveyDisplayed = true;
			if(typeof $.cookie('surveyedJobs') != 'undefined') {
				var c = $.cookie('surveyedJobs');
				surv = c.split('||')[0];
				if(config.survey === surv) {
					$.cookie('surveyedJobs', c + config.job_id + ',');
				}
			} else {
				$.cookie('surveyedJobs', config.survey + '||' + config.job_id + ',', { expires: 20, path: '/' });
			}
			$('.modal.survey').remove();
		}).on('click', '.modal.survey .popup-outer', function() {
			$('.modal.survey').hide().remove();
		}).on('keydown', '#settings-shortcuts.modifying .keystroke', function(e) {
			e.preventDefault();
			var n = e.which;
			var c = $(this).parents('.combination');
			if(!(c.find('.new').length)) {
				$(c).append('<span class="new"></span>');
			}
			var s = $('.new', c);
			console.log(n);
			if((n == '16')||(n == '17')||(n == '18')||(n == '91')) { // is a control key

				if($('.control', s).length > 1) {
					console.log('troppi tasti control: ', $('span', s).length);
					return false;
				}
			
				k = (n == '16')? 'shift' : (n == '17')? 'ctrl' : (n == '18')? 'alt' : (n == '91')? 'meta' : '';
				s.html(s.html() + '<span class="control">' + UI.viewShortcutSymbols(k) + '</span>' + '+');
			} else {
				console.log(n);
				symbol = (n == '8')? '9003' :
						(n == '9')? '8682' :
						(n == '13')? '8629' :
						(n == '37')? '8592' :
						(n == '38')? '8593' :
						(n == '39')? '8594' :
						(n == '40')? '8595' : n;
				console.log('symbol: ', symbol);
//				pref = ($.inArray(n, [37, 38, 39, 40]))? '#' : '';
				s.html(s.html() + '<span class="char">' + UI.viewShortcutSymbols('&#' + symbol) + '</span>' + '+');
				console.log(s.html());
			}
			if($('span', s).length > 2) {
//				console.log('numero span: ', $('span', s).length);
				UI.writeNewShortcut(c, s, this);

//				$(this).html(s.html().substring(0, s.html().length - 1)).removeClass('changing').addClass('modified').blur();
//				$(s).remove();
//				$('.msg', c).remove();
//				$('#settings-shortcuts.modifying').removeClass('modifying');
//				$('.popup-settings .submenu li[data-tab="settings-shortcuts"]').addClass('modified');
			}				
		}).on('keyup', '#settings-shortcuts.modifying .keystroke', function() {
			console.log('keyup');
			var c = $(this).parents('.combination');
			var s = $('.new', c);
			if(($('.control', s).length)&&($('.char', s).length)) {
				UI.writeNewShortcut(c, s, this);
			}
			$(s).remove();
		} ).on('click', '.authLink', function(e){
            e.preventDefault();

            $(".login-google").show();

            return false;
        } ).on('click', '#sign-in', function(e){
            e.preventDefault();

            var url = $(this).data('oauth');

            var newWindow = window.open(url, 'name', 'height=600,width=900');
            if (window.focus) {
                newWindow.focus();
            }
        });
		
		$(window).on('scroll', function() {
			UI.browserScrollPositionRestoreCorrection();
		}).on('allTranslated', function() {
			if(config.survey) UI.displaySurvey(config.survey);
		}).on('mousedown', function() {

            //when the catoool is not loaded because of the job is archived,
            // saveSelection leads to a javascript error
            //so, add a check to see if the cattool page is really created/loaded
            if( $('body' ).hasClass( '.job_archived' ) || $('body' ).hasClass( '.job_cancelled' ) ){
                return false;
            }

            if(!$('.editor .rangySelectionBoundary.focusOut').length) {
                if(!UI.isSafari) saveSelection();
            }
            $('.editor .rangySelectionBoundary').addClass('focusOut');
            hasFocusBefore = UI.editarea.is(":focus");
            setTimeout(function() {
                hasFocusAfter = UI.editarea.is(":focus");
                if(hasFocusBefore && hasFocusAfter){
                    $('.editor .rangySelectionBoundary.focusOut').remove();
                }
            }, 600);
        });
//		window.onbeforeunload = goodbye;

		window.onbeforeunload = function(e) {
			goodbye(e);
			UI.clearStorage('contribution');
			
//			localStorage.clear();
		};

	
// no more used:
		$("header .filter").click(function(e) {
			e.preventDefault();
			UI.body.toggleClass('filtering');
		});
		$("#filterSwitch").bind('click', function(e) {
			UI.toggleSearch(e);
		});
		$("#segmentPointer").click(function(e) {
			e.preventDefault();
			UI.pointToOpenSegment();
		});

		$(".replace").click(function(e) {
			e.preventDefault();
			UI.body.toggleClass('replace-box');
		});

		jQuery('.editarea').trigger('update');

		$("div.notification-box").mouseup(function() {
			return false;
		});

		$(".search-icon, .search-on").click(function(e) {
			e.preventDefault();
			$("#search").toggle();
		});
		$('.download-chrome a.close').bind('click', function(e) {
			e.preventDefault();
			$('.download-chrome').removeClass('d-open');
		});

		//overlay

		$(".x-stats").click(function() {
			$(".stats").toggle();
		});

//		$(window).on('sourceCopied', function(event) {
//		});

		$("#outer").on('click', 'a.sid', function(e) {
			e.preventDefault();
			e.stopPropagation();
			return false;
		}).on('click', 'a.status', function(e) {
			e.preventDefault();
			e.stopPropagation();
		}).on('click', 'section:not(.readonly) a.status', function() {
//			console.log('status');
			var segment = $(this).parents("section");
			var statusMenu = $("ul.statusmenu", segment);

			UI.createStatusMenu(statusMenu);
			statusMenu.show();
			$('html').bind("click.outOfStatusMenu", function() {
				$("ul.statusmenu").hide();
				$('html').unbind('click.outOfStatusMenu');
				UI.removeStatusMenu(statusMenu);
			});
		}).on('click', 'section.readonly, section.readonly a.status', function(e) {
			e.preventDefault();
			if (UI.justSelecting('readonly'))
				return;
			if (UI.someUserSelection)
				return;
			var msg = (UI.body.hasClass('archived'))? 'Job has been archived and cannot be edited.' : 'This part has not been assigned to you.';
			UI.selectingReadonly = setTimeout(function() {
				APP.alert({msg: msg});
			}, 200);

		}).on('mousedown', 'section.readonly, section.readonly a.status', function() {
			sel = window.getSelection();
			UI.someUserSelection = (sel.type == 'Range') ? true : false;
		}).on('dblclick', 'section.readonly', function() {
			clearTimeout(UI.selectingReadonly);
		}).on('dblclick', '.matches .graysmall', function() {
			UI.chooseSuggestion($(this).attr('data-item'));
		}).on('dblclick', '.alternatives .graysmall', function() {
			UI.chooseAlternative($(this));
        }).on('dblclick', '.glossary .sugg-target', function() {
            UI.copyGlossaryItemInEditarea($(this));
		}).on('blur', '.graysmall .translation', function(e) {
			e.preventDefault();
			UI.closeInplaceEditor($(this));
		}).on('click', '.graysmall .edit-buttons .cancel', function(e) {
			e.preventDefault();
			UI.closeInplaceEditor($(this).parents('.graysmall').find('.translation'));
		}).on('click', '.graysmall .edit-buttons .save', function(e) {
			e.preventDefault();
			console.log('save');
			ed = $(this).parents('.graysmall').find('.translation');
			UI.editContribution(UI.currentSegment, $(this).parents('.graysmall'));
			UI.closeInplaceEditor(ed);
		}).on('click', '.tab.alternatives .graysmall .goto a', function(e) {
			e.preventDefault();
			UI.scrollSegment($('#segment-' + $(this).attr('data-goto')), true);
			UI.highlightEditarea($('#segment-' + $(this).attr('data-goto')));
		});

		$(".joblink").click(function(e) {
			e.preventDefault();
			$(".joblist").toggle();
			return false;
		});

		$(".statslink").click(function(e) {
			e.preventDefault();
			e.stopPropagation();
			$(".stats").toggle();
		});

		$(".getoriginal").click(function(e) {
			e.preventDefault();
			$('#originalDownload').submit();
		});
		$("form#fileDownload").bind('submit', function(e) {
			e.preventDefault();
		});

		$('html').click(function() {
			$(".menucolor").hide();
		}).on('click', '#downloadProject', function(e) {
            e.preventDefault();
            if( $('#downloadProject').hasClass('disabled') ) return false;
            //the translation mismatches are not a severe Error, but only a warn, so don't display Error Popup
            if ( $("#notifbox").hasClass("warningbox") && UI.translationMismatches.total != UI.globalWarnings.length ) {
                APP.confirm({
                    name: 'confirmDownload',
                    cancelTxt: 'Fix errors',
                    onCancel: 'goToFirstError',
                    callback: 'continueDownload',
                    okTxt: 'Continue',
                    msg: "Potential errors (missing tags, numbers etc.) found in the text. <br>If you continue, part of the content could be untranslated - look for the string \"UNTRANSLATED_CONTENT\" in the downloaded file(s).<br><br>Continue downloading or fix the error in MateCat:"
                });
            } else {
                UI.continueDownload();
            }
		}).on('click', '.alert .close', function(e) {
			e.preventDefault();
			$('.alert').remove();
		}).on('click', '.downloadtr-button .draft', function() {
			if (UI.isChrome) {
				$('.download-chrome').addClass('d-open');
				setTimeout(function() {
					$('.download-chrome').removeClass('d-open');
				}, 7000);
			}
		}).on('click', '#contextMenu #searchConcordance', function() {
			if ($('#contextMenu').attr('data-sid') == UI.currentSegmentId) {
				UI.openConcordance();
			} else {
				$('#segment-' + $('#contextMenu').attr('data-sid') + ' .editarea').trigger('click', ['clicking', 'openConcordance']);
			}
		}).on('click', '#checkConnection', function(e) {
			e.preventDefault();
			UI.checkConnection();
		}).on('click', '#statistics .meter a', function(e) {
			e.preventDefault();
			UI.gotoNextUntranslatedSegment();
		});

		$("#outer").on('click', 'a.percentuage', function(e) {
			e.preventDefault();
			e.stopPropagation();			
		}).on('mouseup', '.editarea', function() { //mouseupeditarea
            if(!$(window.getSelection().getRangeAt(0))[0].collapsed) { // there's something selected
                if(!UI.isFirefox) UI.showEditToolbar();
            }
		}).on('mousedown', '.editarea', function(e) {
            if(e.which == 3) {
                e.preventDefault();
                return false;
            }
			UI.hideEditToolbar();
		}).on('mousedown', '.editToolbar .uppercase', function() {
			UI.formatSelection('uppercase');
		}).on('mousedown', '.editToolbar .lowercase', function() {
			UI.formatSelection('lowercase');
		}).on('mousedown', '.editToolbar .capitalize', function() {
			UI.formatSelection('capitalize');
		}).on('mouseup', '.editToolbar li', function() {
			restoreSelection();
		}).on('click', '.editarea', function(e, operation, action) { //clickeditarea
            if (typeof operation == 'undefined')
				operation = 'clicking';
            UI.saveInUndoStack('click');
            this.onclickEditarea = new Date();
			UI.notYetOpened = false;
			UI.closeTagAutocompletePanel();
            UI.removeHighlightCorrespondingTags();

            if ((!$(this).is(UI.editarea)) || (UI.editarea === '') || (!UI.body.hasClass('editing'))) {
				if (operation == 'moving') {
					if ((UI.lastOperation == 'moving') && (UI.recentMoving)) {
						UI.segmentToOpen = segment;
						UI.blockOpenSegment = true;

						console.log('ctrl+down troppo vicini');
					} else {
						UI.blockOpenSegment = false;
					}

					UI.recentMoving = true;
					clearTimeout(UI.recentMovingTimeout);
					UI.recentMovingTimeout = setTimeout(function() {
						UI.recentMoving = false;
					}, 1000);

				} else {
					UI.blockOpenSegment = false;
				}
				UI.lastOperation = operation;

				UI.openSegment(this, operation);
				if (action == 'openConcordance')
					UI.openConcordance();

				if (operation != 'moving')
					UI.scrollSegment($('#segment-' + $(this).data('sid')));
			}
            UI.lockTags(UI.editarea);

            if (UI.debug)
				console.log('Total onclick Editarea: ' + ((new Date()) - this.onclickEditarea));
		}).on('keydown', '.editor .source, .editor .editarea', UI.shortcuts.searchInConcordance.keystrokes.mac, function(e) {
			e.preventDefault();
			UI.preOpenConcordance();
		}).on('keydown', '.editor .source, .editor .editarea', UI.shortcuts.searchInConcordance.keystrokes.standard, function(e) {
			e.preventDefault();
			UI.preOpenConcordance();
        }).on('keyup', '.editor .editarea', 'return', function(e) {
            console.log('UI.defaultBRmanagement: ', UI.defaultBRmanagement);

 //           if(!UI.defaultBRmanagement) {
                console.log( 'Enter key is disabled!' );
                e.preventDefault();
                return false;
 //           };

//            if(!UI.defaultBRmanagement) {
//                range = window.getSelection().getRangeAt(0);
////                $('.returnTempPlaceholder', UI.editarea).after('<span class="br"><br /><span class="startRow">&nbsp;</span></span>');
//                $('.returnTempPlaceholder', UI.editarea).after('<br />');
////                $('.returnTempPlaceholder', UI.editarea).after('<br /><span class="startRow">&nbsp;</span>');
////                console.log('qua');
////                $('.returnTempPlaceholder', UI.editarea).after('<br /><img>');
//
////                node = $('.returnTempPlaceholder + br', UI.editarea)[0];
////                setCursorAfterNode(range, node);
//                saveSelection();
//                $('.returnTempPlaceholder', UI.editarea).remove();
//                restoreSelection();
//            } else {
////                 $('.returnTempPlaceholder', UI.editarea).after('<br /><span class="startRow">&nbsp;</span>');
//            }

        }).on('keydown', '.editor .editarea', 'return', function(e) {
            e.preventDefault();
/*
            UI.defaultBRmanagement = false;
            if(!$('br', UI.editarea).length) {
                UI.defaultBRmanagement = true;
            } else {
                saveSelection();
                $('.rangySelectionBoundary', UI.editarea).after('<span class="returnTempPlaceholder" contenteditable="false"></span>');
                restoreSelection();
                e.preventDefault();
            }
*/
        }).on('keypress', '.editor .editarea', function(e) {
//			console.log('keypress: ', UI.editarea.html());

			if((e.which == 60)&&(UI.taglockEnabled)) { // opening tag sign
//				console.log('KEYPRESS SU EDITAREA: ', UI.editarea.html());
				if($('.tag-autocomplete').length) {
					e.preventDefault();
					return false;
				}
				UI.openTagAutocompletePanel();
//				console.log('Q: ', UI.editarea.html());
			}
			if((e.which == 62)&&(UI.taglockEnabled)) { // closing tag sign
				if($('.tag-autocomplete').length) {
					e.preventDefault();
					return false;
				}
			}
			setTimeout(function() {
				if($('.tag-autocomplete').length) {
//					console.log('ecco');
//					console.log('prima del replace: ', UI.editarea.html());
                    // if tag-autocomplete-endcursor is inserted before the &lt; then it is moved after it

                    tempStr = UI.editarea.html().match(/<span class="tag-autocomplete-endcursor"\><\/span>&lt;/gi);
                    UI.stripAngular = (!tempStr)? false : (!tempStr.length)? false : true;

//                    UI.stripAngular = (UI.editarea.html().match(/<span class="tag-autocomplete-endcursor"\><\/span>&lt;/gi).length)? true : false;
//                    UI.editarea.html(UI.editarea.html().replace(/<span class="tag-autocomplete-endcursor"\><\/span>&lt;/gi, '&lt;<span class="tag-autocomplete-endcursor"></span>'));
//                    console.log(UI.editarea.html().replace(/&lt;<span class="tag-autocomplete-endcursor"\><\/span>/gi, '<span class="tag-autocomplete-endcursor"\>XXX/span>&lt;'));
//                    console.log(UI.editarea.html().replace(/<span class="tag-autocomplete-endcursor"\><\/span>&lt;/gi, '&lt;<span class="tag-autocomplete-endcursor"\>XXX/span>'));

//					console.log(UI.editarea.html().match(/^(<span class="tag-autocomplete-endcursor"\><\/span>&lt;)/gi) != null);
					if(UI.editarea.html().match(/^(<span class="tag-autocomplete-endcursor"\><\/span>&lt;)/gi) !== null) {
						UI.editarea.html(UI.editarea.html().replace(/^(<span class="tag-autocomplete-endcursor"\><\/span>&lt;)/gi, '&lt;<span class="tag-autocomplete-endcursor"><\/span>'));
//						console.log('dopo del replace: ', UI.editarea.html());
					}
					UI.checkAutocompleteTags();
				}
			}, 50);
            if (!UI.body.hasClass('searchActive')) {
//                console.log('vediamo: ', e.which);
                if(UI.isCJK && ( (e.which == '60') || (e.which == '62') ) ) {
                } else {
                    setTimeout(function() {
                        UI.lockTags(UI.editarea);
                    }, 10);
                }
            }
		}).on('keydown', '.editor .editarea', function(e) {
//			console.log('keydown: ', UI.editarea.html());
/*
			var special = event.type !== "keypress" && jQuery.hotkeys.specialKeys[ event.which ];
			if ((event.metaKey && !event.ctrlKey && special !== "meta") || (event.ctrlKey)) {
				if (event.which == 88) { // ctrl+x
					if ($('.selected', $(this)).length) {console.log('VEDIAMO');
						event.preventDefault();
						UI.tagSelection = getSelectionHtml();
						$('.selected', $(this)).remove();
					}
				}
			}
*/

//			console.log(e.which); 

            if ((e.which == 8)&&(!UI.body.hasClass('tagmode-default-extended'))) {
//                console.log(window.getSelection().getRangeAt(0).endContainer.previousElementSibling);
                if($(window.getSelection().getRangeAt(0).endContainer.previousElementSibling).hasClass('locked')) {
                    e.preventDefault();
                    $(window.getSelection().getRangeAt(0).endContainer.previousElementSibling).remove();
                }
            }

			if ((e.which == 8) || (e.which == 46)) { // backspace e canc(mac)
				if ($('.selected', $(this)).length) {
					e.preventDefault();
					$('.selected', $(this)).remove();
					UI.saveInUndoStack('cancel');
					UI.currentSegmentQA();
				} else {
					var numTagsBefore = (UI.editarea.text().match(/<.*?\>/gi) !== null)? UI.editarea.text().match(/<.*?\>/gi).length : 0;
                    console.log('numTagsBefore: ', numTagsBefore);
                    var numSpacesBefore = $('.space-marker', UI.editarea).length;
//                    var numSpacesBefore = UI.editarea.text().match(/\s/gi).length;
//					console.log('a: ', UI.editarea.html());
					saveSelection();

					parentTag = $('span.locked', UI.editarea).has('.rangySelectionBoundary');
					isInsideTag = $('span.locked .rangySelectionBoundary', UI.editarea).length;
					parentMark = $('.searchMarker', UI.editarea).has('.rangySelectionBoundary');
					isInsideMark = $('.searchMarker .rangySelectionBoundary', UI.editarea).length;
//					console.log('c: ', UI.editarea.html());

                    sbIndex = 0;
                    var translation = $.parseHTML(UI.editarea.html());
                    $.each(translation, function(index) {
                        if($(this).hasClass('rangySelectionBoundary')) sbIndex = index;
                    });

                    var undeletableMonad = (($(translation[sbIndex-1]).hasClass('monad'))&&($(translation[sbIndex-2]).prop("tagName") == 'BR'))? true : false;
                    var selBound = $('.rangySelectionBoundary', UI.editarea);
                    if(undeletableMonad) selBound.prev().remove();
                    if(e.which == 8) { // backspace
                        var undeletableTag = (($(translation[sbIndex-1]).hasClass('locked'))&&($(translation[sbIndex-2]).prop("tagName") == 'BR'))? true : false;
                        if(undeletableTag) selBound.prev().remove();
                    }

                    restoreSelection();

					// insideTag management
					if ((e.which == 8)&&(isInsideTag)) {
//							console.log('AA: ', UI.editarea.html());
						parentTag.remove();
						e.preventDefault();
//							console.log('BB: ', UI.editarea.html());
					}

//					console.log(e.which + ' - ' + isInsideTag);
					setTimeout(function() {
//						if ((e.which == 46)&&(isInsideTag)) {
//							console.log('inside tag');
//						}
//						console.log(e.which + ' - ' + isInsideTag);
                        saveSelection();
                        // detect if selection ph is inside a monad tag
  //                      console.log('sel placeholders inside a monad', $('.monad .rangySelectionBoundary', UI.editarea).length);
                        if($('.monad .rangySelectionBoundary', UI.editarea).length) {
    //                        console.log($('.monad:has(.rangySelectionBoundary)', UI.editarea));
                            $('.monad:has(.rangySelectionBoundary)', UI.editarea).after($('.monad .rangySelectionBoundary', UI.editarea));
                            // move selboundary after the
                        }
  //                      console.log('CC: ', UI.editarea.html());
                        restoreSelection();
//							console.log('DD: ', UI.editarea.html());
						var numTagsAfter = (UI.editarea.text().match(/<.*?\>/gi) !== null)? UI.editarea.text().match(/<.*?\>/gi).length : 0;
						var numSpacesAfter = $('.space-marker', UI.editarea).length;
//                        var numSpacesAfter = (UI.editarea.text())? UI.editarea.text().match(/\s/gi).length : 0;
						if (numTagsAfter < numTagsBefore) UI.saveInUndoStack('cancel');
						if (numSpacesAfter < numSpacesBefore) UI.saveInUndoStack('cancel');
//                        console.log('EE: ', UI.editarea.html());
//                        console.log($(':focus'));


					}, 50);

					// insideMark management
					if ((e.which == 8)&&(isInsideMark)) {
						console.log('inside mark'); 
					}



				}
			}
			
			if (e.which == 8) { // backspace
				if($('.tag-autocomplete').length) {
					UI.closeTagAutocompletePanel();
					setTimeout(function() {
						UI.openTagAutocompletePanel();
						added = UI.getPartialTagAutocomplete();
						if(added === '') UI.closeTagAutocompletePanel();
					}, 10);		
				}
			}
			if (e.which == 9) { // tab
                if(!UI.hiddenTextEnabled) return;

				e.preventDefault();
				var node = document.createElement("span");
				node.setAttribute('class', 'marker monad tab-marker ' + config.tabPlaceholderClass);
				node.setAttribute('contenteditable', 'false');
				node.textContent = htmlDecode("&#8677;");
				insertNodeAtCursor(node);
				UI.unnestMarkers();
			}
			if (e.which == 37) { // left arrow
				selection = window.getSelection();
				range = selection.getRangeAt(0);
                UI.checkTagProximity('left', range);

//                console.log('range: ', range);
				if (range.startOffset != range.endOffset) { // if something is selected when the left button is pressed...
					r = range.startContainer.innerText;
//                    r = range.startContainer.data;
					if ((r[0] == '<') && (r[r.length - 1] == '>')) { // if a tag is selected
                        e.preventDefault();

                        /*
                                                console.log('1: ', UI.editarea.html());
                                                $('.rangySelectionBoundary', UI.editarea).remove();
                                                saveSelection();
                                                if($('span .rangySelectionBoundary', UI.editarea).length) {
                                                    $('span:has(.rangySelectionBoundary)', UI.editarea).before($('.rangySelectionBoundary', UI.editarea));
                                                }
                                                console.log('2: ', UI.editarea.html());
                                                restoreSelection();
                        */

                        saveSelection();
//                        console.log(UI.editarea.html());
                        rr = document.createRange();
                        referenceNode = $('.rangySelectionBoundary', UI.editarea).first().get(0);
                        rr.setStartBefore(referenceNode);
                        rr.setEndBefore(referenceNode);
                        $('.rangySelectionBoundary', UI.editarea).remove();

					}
				} else { // there's nothing selected
                    console.log('nothing selected when left is pressed');
/*
                    saveSelection();
                    sbIndex = 0;
                    translation = $.parseHTML(UI.editarea.html());
                    $.each(translation, function(index) {
                        if($(this).hasClass('rangySelectionBoundary')) sbIndex = index;
                    });
//                    console.log('$(translation[sbIndex-1]).prop("tagName"): ', $(translation[sbIndex-1]).prop("tagName"));
//                    console.log('$(translation[sbIndex-2]).prop("tagName"): ', $(translation[sbIndex-2]).prop("tagName"));
                    if(($(translation[sbIndex-1]).prop("tagName") == 'SPAN')&&($(translation[sbIndex-2]).prop("tagName") == 'BR')) {
                        console.log('agire');
                        console.log(UI.editarea.html());
                        ph = $('.rangySelectionBoundary', UI.editarea);
                        console.log(ph);
                        console.log(ph.prev());
                        prev = ph.prev();
                        prev.before('<span class="toDestroy" style="width: 0px; float: left;">&nbsp;</span>');
//                        prev.before(ph);
                        console.log(UI.editarea.html());
                        restoreSelection();

//                        $('.toDestroy', UI.editarea).remove();

                    }
*/
                }
				UI.closeTagAutocompletePanel();
//				UI.jumpTag('start');
			}

			if (e.which == 38) { // top arrow
				if($('.tag-autocomplete').length) {
					if(!$('.tag-autocomplete li.current').is($('.tag-autocomplete li:first'))) {
						$('.tag-autocomplete li.current:not(:first-child)').removeClass('current').prevAll(':not(.hidden)').first().addClass('current');
						return false;
					}	
				}
				selection = window.getSelection();
				range = selection.getRangeAt(0);
				if (range.startOffset != range.endOffset) {
					r = range.startContainer.data;
					if ((r[0] == '<') && (r[r.length - 1] == '>')) {
						saveSelection();
						rr = document.createRange();
						referenceNode = $('.rangySelectionBoundary', UI.editarea).last().get(0);
						rr.setStartAfter(referenceNode);
						rr.setEndAfter(referenceNode);
						$('.rangySelectionBoundary', UI.editarea).remove();
					}
				}
			}
			if (e.which == 39) { // right arrow
				selection = window.getSelection();
				range = selection.getRangeAt(0);
//                console.log('range when pressing right arrow key: ', range);
                UI.checkTagProximity('right', range);

				if (range.startOffset != range.endOffset) {
					r = range.startContainer.innerText;
					if ((r[0] == '<') && (r[r.length - 1] == '>')) {
						saveSelection();
						rr = document.createRange();
						referenceNode = $('.rangySelectionBoundary', UI.editarea).last().get(0);
						rr.setStartAfter(referenceNode);
						rr.setEndAfter(referenceNode);
						$('.rangySelectionBoundary', UI.editarea).remove();
					}
				}
				UI.closeTagAutocompletePanel();
				UI.jumpTag(range, 'end');
			}

			if (e.which == 40) { // down arrow
				if($('.tag-autocomplete').length) {
					$('.tag-autocomplete li.current:not(:last-child)').removeClass('current').nextAll(':not(.hidden)').first().addClass('current');	
					return false;
				}
				selection = window.getSelection();
				range = selection.getRangeAt(0);
				if (range.startOffset != range.endOffset) {
					r = range.startContainer.data;
					if ((r[0] == '<') && (r[r.length - 1] == '>')) {
						saveSelection();
						rr = document.createRange();
						referenceNode = $('.rangySelectionBoundary', UI.editarea).last().get(0);
						rr.setStartAfter(referenceNode);
						rr.setEndAfter(referenceNode);
						$('.rangySelectionBoundary', UI.editarea).remove();
					}
				}
//                console.log($(':focus'));
                //              return false;
			}

            if (((e.which == 37) || (e.which == 38) || (e.which == 39) || (e.which == 40) || (e.which == 8))) { // not arrows, backspace, canc
                UI.saveInUndoStack('arrow');
            }

			if (!((e.which == 37) || (e.which == 38) || (e.which == 39) || (e.which == 40) || (e.which == 8) || (e.which == 46) || (e.which == 91))) { // not arrows, backspace, canc or cmd
				if (UI.body.hasClass('searchActive')) {
					UI.resetSearch();
				}
			}
			if (e.which == 32) { // space
				setTimeout(function() {
					UI.saveInUndoStack('space');
				}, 100);
			}

			if (e.which == 13) { // return
				if($('.tag-autocomplete').length) {
//                    console.log('QQQQQQ: ', UI.editarea.html());
                    e.preventDefault();
                    $('.tag-autocomplete li.current').click();
					return false;
				}
			}

			if (
					(e.which == 13) || // return
					(e.which == 32) || // space
					(e.which == 49) || // semicomma
					(e.which == 188) || // comma
					(e.which == 186) || // semicomma
					(e.which == 190) || // mark
					(e.which == 191) || // question mark
					(e.which == 222)) { // apostrophe
				UI.spellCheck();
			}

		}).on('input', '.editarea', function() {
			console.log('input in editarea');
//			DA SPOSTARE IN DROP E PASTE
//			if (UI.body.hasClass('searchActive')) {
//				console.log('on input');
//				UI.resetSearch();
//			}
			UI.currentSegment.addClass('modified').removeClass('waiting_for_check_result');
			if (UI.draggingInsideEditarea) {
				$(UI.tagToDelete).remove();
				UI.draggingInsideEditarea = false;
				UI.tagToDelete = null;
			}
			if (UI.droppingInEditarea) {
				UI.cleanDroppedTag(UI.editarea, UI.beforeDropEditareaHTML);
			}
            if(!UI.editarea.find('.locked').length) {
                UI.currentSegment.removeClass('hasTags');
            }
/*
			if (!UI.body.hasClass('searchActive'))
				setTimeout(function() {
					UI.lockTags(UI.editarea);
				}, 10);
*/
			UI.registerQACheck();
		}).on('input', '.editor .cc-search .input', function() {
			UI.markTagsInSearch($(this));
		}).on('click', '.editor .source .locked,.editor .editarea .locked', function(e) {
			e.preventDefault();
			e.stopPropagation();
			setCursorPosition(this);
			selectText(this);
			$(this).toggleClass('selected');
//		}).on('contextmenu', '.source', function(e) {
			// temporarily disabled
//            if(UI.viewConcordanceInContextMenu||UI.viewSpellCheckInContextMenu) e.preventDefault();
		}).on('mousedown', '.source', function(e) {
			if (e.button == 2) { // right click
				// temporarily disabled
				return true;
/*
				if ($('#contextMenu').css('display') == 'block')
					return true;

				var selection = window.getSelection();
				if (selection.type == 'Range') { // something is selected
					var str = selection.toString().trim();
					if (str.length) { // the trimmed string is not empty
						UI.currentSelectedText = str;

						UI.currentSearchInTarget = ($(this).hasClass('source')) ? 0 : 1;
						$('#contextMenu').attr('data-sid', $(this).parents('section').attr('id').split('-')[1]);

						if (UI.customSpellcheck) {
							var range = selection.getRangeAt(0);
							var tag = range.startContainer.parentElement;
							if (($(tag).hasClass('misspelled')) && (tag === range.endContainer.parentElement)) { // the selected element is in a misspelled element
								UI.selectedMisspelledElement = $(tag);
								var replacements = '';
								var words = $(tag).attr('data-replacements').split(',');
								$.each(words, function(item) {
									replacements += '<a class="words" href="#">' + this + '</a>';
								});
								if ((words.length == 1) && (words[0] == '')) {
									$('#spellCheck .label').hide();
								} else {
									$('#spellCheck .label').show();
								}
								$('#spellCheck .words').remove();
								$('#spellCheck').show().find('.label').after(replacements);
							} else {
								$('#spellCheck').hide();
							}
						}

						UI.showContextMenu(str, e.pageY, e.pageX);
					}
				}
				return false;
				*/
			}
			return true;
		}).on('dragstart', '.editor .editarea .locked', function() {
//			console.log('dragstart tag: ', $(this));
//			$(this).addClass('dragged');
			var selection = window.getSelection();
			var range = selection.getRangeAt(0);
			if (range.startContainer.data != range.endContainer.data)
				return false;

			UI.draggingInsideEditarea = true;
			UI.tagToDelete = $(this);
//		}).on('drop', '.editor .editarea .locked', function() {
//			console.log('dropped tag: ', $(this));
		}).on('drag', '.editarea .locked, .source .locked', function() {
//			console.log('a tag is dragged');
//			console.log('e: ', $(this).text());
			UI.draggingTagIsOpening = ($(this).text().match(/^<\//gi))? false : true;
			UI.draggingTagText = $(this).text();
		}).on('drop', '.editor .editarea', function(e) {
			if (e.stopPropagation) {
				e.stopPropagation(); // stops the browser from redirecting.
			}
			UI.beforeDropEditareaHTML = UI.editarea.html();
			UI.droppingInEditarea = true;

			$(window).trigger({
				type: "droppedInEditarea",
				segment: UI.currentSegment
			});
//			UI.saveInUndoStack('drop');
//			UI.beforeDropEditareaHTMLtreated = UI.editarea.html();
			$(this).css('float', 'left');
			setTimeout(function() {
				var strChunk = UI.editarea.html().replace(/(^.*?)&nbsp;(<span contenteditable\="false" class\="locked).*?$/gi, '$1');

				// Check if the browser has cancelled a space when dropping the tag (this happen when dropping near a space). 
				// In this case, we have to add it again because we are also deleting the &nbsp; added by the browser.
				// We cannot detect if the user has dropped immediately before or after the space, so we decide where to put it according if it is an opening tag or a closing tag,
				if(UI.beforeDropEditareaHTML.indexOf(strChunk + ' ') >= 0) {  
					toAddBefore = (UI.draggingTagIsOpening)? ' ' : ''; 
					toAddAfter = (UI.draggingTagIsOpening)? '' : ' ';
				} else {
					toAddBefore = toAddAfter = '';
				}
				UI.draggingTagIsOpening = null;
				UI.editarea.html(UI.editarea.html().replace(/&nbsp;(<span contenteditable\="false" class\="locked)/gi, toAddBefore + '$1').replace(/(&gt;<\/span>)&nbsp;/gi, '$1' + toAddAfter));
				var nn = 0;
				$('.locked', UI.editarea).each(function() {
					if($(this).text() == UI.draggingTagText) {
						uniqueEl = $(this);
						nn++;
						return false;
					}
				});
				if(nn > 0) {
					setCursorPosition(uniqueEl[0].nextSibling, 0);
				}
				
				UI.draggingTagText = null;
				UI.editarea.removeAttr('style');
				UI.saveInUndoStack('drop');
			}, 100);
		}).on('drop paste', '.editor .cc-search .input, .editor .gl-search .input', function() {
			UI.beforeDropSearchSourceHTML = UI.editarea.html();
			UI.currentConcordanceField = $(this);
			setTimeout(function() {
                console.log('sto per pulire');
				UI.cleanDroppedTag(UI.currentConcordanceField, UI.beforeDropSearchSourceHTML);
			}, 100);
		}).on('click', '.editor .editarea, .editor .source', function() {
			$('.selected', $(this)).removeClass('selected');
			UI.currentSelectedText = false;
			UI.currentSearchInTarget = false;
			$('#contextMenu').hide();
        }).on('blur', '.editor .editarea', function() {
            UI.hideEditToolbar();
		}).on('click', 'a.translated, a.next-untranslated', function(e) {
			var w = ($(this).hasClass('translated')) ? 'translated' : 'next-untranslated';
			e.preventDefault();
            UI.hideEditToolbar();

            UI.currentSegment.removeClass('modified');
			var skipChange = false;
			if (w == 'next-untranslated') {
				console.log('next-untranslated');
				if (!UI.segmentIsLoaded(UI.nextUntranslatedSegmentId)) {
					console.log('il nextuntranslated non è caricato: ', UI.nextUntranslatedSegmentId);
					UI.changeStatus(this, 'translated', 0);
					skipChange = true;
					if (!UI.nextUntranslatedSegmentId) {
//						console.log('a');
						$('#' + $(this).attr('data-segmentid') + '-close').click();
					} else {
//						console.log('b');
						UI.reloadWarning();
					}

				} else {
					console.log('il nextuntranslated è già caricato: ', UI.nextUntranslatedSegmentId);
				}
			} else {
				if (!$(UI.currentSegment).nextAll('section:not(.readonly)').length) {
					UI.changeStatus(this, 'translated', 0);
					skipChange = true;
					$('#' + $(this).attr('data-segmentid') + '-close').click();
				}
			}
			UI.checkHeaviness();
			if ((UI.blockButtons)&&(!UI.autoFailoverEnabled)) {
 //               console.log('Il segmento ' + UI.currentSegmentId + ' non è stato salvato, deve essere caricato in una coda');
				if (UI.segmentIsLoaded(UI.nextUntranslatedSegmentId) || UI.nextUntranslatedSegmentId === '') {
//					console.log('segment is already loaded');
				} else {
//					console.log('segment is not loaded');

					if (!UI.noMoreSegmentsAfter) {
						UI.reloadWarning();
					}
				}
 //               console.log('saltato ', UI.currentSegmentId);
				return;
			}
			UI.blockButtons = true;

			UI.unlockTags();
			UI.setStatusButtons(this);

            if (!skipChange)
                UI.changeStatus(this, 'translated', 0);

			if (w == 'translated') {
				UI.gotoNextSegment();
			} else {
				$(".editarea", UI.nextUntranslatedSegment).trigger("click", "translated");
			}

//			UI.markTags();
            console.log('ID DEL PRECEDENTE: ', $(this).attr('data-segmentid'));
            console.log($('#' + $(this).attr('data-segmentid') + ' .editarea'));
            console.log('prima: ', $('#' + $(this).attr('data-segmentid') + ' .editarea').html());

            UI.lockTags($('#' + $(this).attr('data-segmentid') + ' .editarea'));
            console.log('dopo: ', $('#' + $(this).attr('data-segmentid') + ' .editarea').html());
			UI.lockTags(UI.editarea);
			UI.changeStatusStop = new Date();
			UI.changeStatusOperations = UI.changeStatusStop - UI.buttonClickStop;
		}).on('click', 'a.approved', function() {
			UI.setStatusButtons(this);
			$(".editarea", UI.nextUntranslatedSegment).click();

			UI.changeStatus(this, 'approved', 0);
			UI.changeStatusStop = new Date();
			UI.changeStatusOperations = UI.changeStatusStop - UI.buttonClickStop;
		}).on('click', 'a.d, a.a, a.r, a.f', function() {
			var segment = $(this).parents("section");
			$("a.status", segment).removeClass("col-approved col-rejected col-done col-draft");
			$("ul.statusmenu", segment).toggle();
			return false;
		}).on('click', 'a.d', function() {
			UI.changeStatus(this, 'translated', 1);
		}).on('click', 'a.a', function() {
			UI.changeStatus(this, 'approved', 1);
		}).on('click', 'a.r', function() {
			UI.changeStatus(this, 'rejected', 1);
		}).on('click', 'a.f', function() {
			UI.changeStatus(this, 'draft', 1);
		}).on('click', '.editor .outersource .copy', function(e) {
//        }).on('click', 'a.copysource', function(e) {
			e.preventDefault();
			UI.copySource();
		}).on('click', '.tagmenu, .warning, .viewer, .notification-box li a', function() {
			return false;
		}).on('click', '.tab-switcher-tm', function(e) {
			e.preventDefault();
			$('.editor .submenu .active').removeClass('active');
			$(this).addClass('active');
			$('.editor .sub-editor').hide();
			$('.editor .sub-editor.matches').show();
		}).on('click', '.tab-switcher-cc', function(e) {
			e.preventDefault();
			$('.editor .submenu .active').removeClass('active');
			$(this).addClass('active');
			$('.editor .sub-editor').hide();
			$('.editor .sub-editor.concordances').show();
			$('.cc-search .search-source').focus();
//        }).on('keydown', '.sub-editor .cc-search .search-source', 'return', function(e) {
			//if($(this).text().length > 2) UI.getConcordance($(this).text(), 0);
		}).on('click', '.tab-switcher-gl', function(e) {
			e.preventDefault();
			$('.editor .submenu .active').removeClass('active');
			$(this).addClass('active');
			$('.editor .sub-editor').hide();
			$('.editor .sub-editor.glossary').show();
			$('.gl-search .search-source').focus();
		}).on('click', '.tab-switcher-al', function(e) {
			e.preventDefault();
			$('.editor .submenu .active').removeClass('active');
			$(this).addClass('active');
			$('.editor .sub-editor').hide();
			$('.editor .sub-editor.alternatives').show();
		}).on('click', '.alternatives a', function(e) {
			e.preventDefault();
			$('.editor .tab-switcher-al').click();
		}).on('click', '.sub-editor.glossary .overflow a.trash', function(e) {
			e.preventDefault();
			ul = $(this).parents('ul.graysmall').first();
			UI.deleteGlossaryItem($(this).parents('ul.graysmall').first());
		}).on('click', '.sub-editor.glossary .details .comment', function(e) {
			e.preventDefault();
			$(this).attr('contenteditable', true).focus();
		}).on('blur', '.sub-editor.glossary .details .comment', function(e) {
			e.preventDefault();
			$(this).attr('contenteditable', false);
			item = $(this).parents('.graysmall');
			APP.doRequest({
				data: {
					action: 'glossary',
					exec: 'update',
					segment: item.find('.suggestion_source').text(),
					translation: item.find('.translation').text(),
					comment: $(this).text(),
					id_item: item.attr('data-id'),
					id_job: config.job_id,
					password: config.password
				},
				error: function() {
					UI.failedConnection(0, 'glossary');
				},
				context: [UI.currentSegment, next]
			});
		}).on('keydown', '.sub-editor .cc-search .search-source', function(e) {
			if (e.which == 13) { // enter
				e.preventDefault();
				var txt = $(this).text();
				if (txt.length > 1)
					UI.getConcordance(txt, 0);
			} else {
				if ($('.editor .sub-editor .cc-search .search-target').text().length > 0) {
					$('.editor .sub-editor .cc-search .search-target').text('');
					$('.editor .sub-editor.concordances .results').empty();
				}
			}
		}).on('keydown', '.sub-editor .cc-search .search-target', function(e) {
			if (e.which == 13) {
				e.preventDefault();
				var txt = $(this).text();
				if (txt.length > 2)
					UI.getConcordance(txt, 1);
			} else {
				if ($('.editor .sub-editor .cc-search .search-source').text().length > 0) {
					$('.editor .sub-editor .cc-search .search-source').text('');
					$('.editor .sub-editor.concordances .results').empty();
				}
			}
		}).on('click', '.sub-editor .gl-search .search-glossary', function(e) {
			e.preventDefault();
			var txt = $(this).parents('.gl-search').find('.search-source').text();
			segment = $(this).parents('section').first();
			if (txt.length > 1) {
				UI.getGlossary(segment, false);
			} else {
				APP.alert({msg: 'Please insert a string of two letters at least!'});
			}

		}).on('keydown', '.sub-editor .gl-search .search-source', function(e) {
			if (e.which == 13) {
				e.preventDefault();
				var txt = $(this).text();
				if (txt.length > 2) {
					segment = $(this).parents('section').first();
					UI.getGlossary(segment, false);
				}
			}
		}).on('input', '.sub-editor .gl-search .search-target', function() {
			gl = $(this).parents('.gl-search').find('.set-glossary');	
			if($(this).text() === '') {
				gl.addClass('disabled');
			} else {
				gl.removeClass('disabled');
			}
		}).on('click', '.sub-editor .gl-search .set-glossary', function(e) {
			e.preventDefault();
		}).on('click', '.sub-editor .gl-search .set-glossary:not(.disabled)', function(e) {
			e.preventDefault();
			if($(this).parents('.gl-search').find('.search-source').text() === '') {
				APP.alert({msg: 'Please insert a glossary term.'});
				return false;
			} else {
				UI.setGlossaryItem();
			}
		}).on('click', '.sub-editor .gl-search .comment a', function(e) {
			e.preventDefault();
			$(this).parents('.comment').find('.gl-comment').toggle();
 /*
        }).on('mousedown', function(e) {

            console.log('mousedown');
            console.log('prima: ', UI.editarea.is(":focus"));
            saveSelection();
            $('.editor .rangySelectionBoundary').addClass('focusOut');
            hasFocusBefore = UI.editarea.is(":focus");
            setTimeout(function() {
                hasFocusAfter = UI.editarea.is(":focus");
                if(hasFocusBefore && !hasFocusAfter) {
                    console.log('blurred from editarea');
                } else if(!hasFocusBefore && hasFocusAfter) {
                    console.log('focused in editarea');
                    restoreSelection();
                } else {
                    $('.editor .rangySelectionBoundary.focusOut').remove();

                }
            }, 50);
            */
		}).on('paste', '.editarea', function(e) {
			console.log('paste in editarea');

			UI.saveInUndoStack('paste');
			$('#placeHolder').remove();
			var node = document.createElement("div");
			node.setAttribute('id', 'placeHolder');
			removeSelectedText();
			insertNodeAtCursor(node);
			if(UI.isFirefox) pasteHtmlAtCaret('<div id="placeHolder"></div>');
			var ev = (UI.isFirefox) ? e : event;
			handlepaste(this, ev);
            /*
			$(window).trigger({
				type: "pastedInEditarea",
				segment: segment
			});

			setTimeout(function() {
				UI.saveInUndoStack('paste');
			}, 100);
            UI.lockTags(UI.editarea);
			UI.currentSegmentQA();
 */
		}).on('click', 'a.close', function(e, param) {
			e.preventDefault();
			var save = (typeof param == 'undefined') ? 'noSave' : param;
			UI.closeSegment(UI.currentSegment, 1, save);
		}).on('click', '.concordances .more', function(e) {
			e.preventDefault();
			tab = $(this).parents('.concordances');
			container = $('.overflow', $(tab));
//			console.log($(container).height());
			if($(tab).hasClass('extended')) {
				UI.setExtendedConcordances(false);

/*				
				$(tab).removeClass('extended')
//				console.log(container.height());
				$(container).removeAttr('style');
//				console.log($(container).height());
				$(this).text('More');
*/
			} else {
				UI.setExtendedConcordances(true);
				
//				$(container).css('height', $(tab).height() + 'px');
//				$(tab).addClass('extended');
//				$(this).text('Less');
//				UI.custom.extended_concordance = true;
//				UI.saveCustomization();
			}
			$(this).parents('.matches').toggleClass('extended');
        }).on('keyup', '.editor .editarea', function(e) {
			if ( e.which == 13 ){
//				$(this).find( 'br:not([class])' ).replaceWith( $('<br class="' + config.crPlaceholderClass + '" />') );

                //replace all divs with a br and remove all br without a class
//                var divs = $( this ).find( 'div' );
//                if( divs.length ){
//					divs.each(function(){
//						$(this).find( 'br:not([class])' ).remove();
//						$(this).prepend( $('<br class="' + config.crPlaceholderClass + '" />' ) ).replaceWith( $(this).html() );
//					});
//                } else {
//                    $(this).find( 'br:not([class])' ).replaceWith( $('<br class="' + config.crPlaceholderClass + '" />') );
//                }
			}
		}).on('click', '.tagMode .crunched', function(e) {
            e.preventDefault();
            UI.setCrunchedTagMode();
//            UI.currentSegment.attr('data-tagMode', 'crunched');
        }).on('click', '.tagMode .extended', function(e) {
            e.preventDefault();
            UI.setExtendedTagMode();
//            UI.currentSegment.attr('data-tagMode', 'extended');
        });
		UI.toSegment = true;
		if (!this.segmentToScrollAtRender)
			UI.gotoSegment(this.startSegmentId);

		$(".end-message-box a.close").on('click', function(e) {
			e.preventDefault();
			UI.body.removeClass('justdone');
		});

		this.checkIfFinishedFirst();

		$("section .close").bind('keydown', 'Shift+tab', function(e) {
			e.preventDefault();
			$(this).parents('section').find('a.translated').focus();
		});

		$("a.translated").bind('keydown', 'tab', function(e) {
			e.preventDefault();
			$(this).parents('section').find('.close').focus();
		});

		$("#point2seg").bind('mousedown', function() {
			UI.setNextWarnedSegment();
		});
		
		$("#navSwitcher").on('click', function(e) {
			e.preventDefault();
		});
		$("#pname").on('click', function(e) {
			e.preventDefault();
			UI.toggleFileMenu();
		});
		$("#jobNav .jobstart").on('click', function(e) {
			e.preventDefault();
			UI.scrollSegment($('#segment-' + config.firstSegmentOfFiles[0].first_segment));
		});
		$("#jobMenu").on('click', 'li:not(.currSegment)', function(e) {
			e.preventDefault();
			UI.renderAndScrollToSegment($(this).attr('data-segment'));
		});
		$("#jobMenu").on('click', 'li.currSegment', function(e) {
			e.preventDefault();
			UI.pointToOpenSegment();
		});
		$("#jobNav .prevfile").on('click', function(e) {
			e.preventDefault();
			currArtId = $(UI.currentFile).attr('id').split('-')[1];
			$.each(config.firstSegmentOfFiles, function() {
				if (currArtId == this.id_file)
					firstSegmentOfCurrentFile = this.first_segment;
			});
			UI.scrollSegment($('#segment-' + firstSegmentOfCurrentFile));
		});
		$("#jobNav .currseg").on('click', function(e) {
			e.preventDefault();

			if (!($('#segment-' + UI.currentSegmentId).length)) {
				$('#outer').empty();
				UI.render({
					firstLoad: false
				});
			} else {
				UI.scrollSegment(UI.currentSegment);
			}
		});
		$("#jobNav .nextfile").on('click', function(e) {
			e.preventDefault();
			if (UI.tempViewPoint === '') { // the user have not used yet the Job Nav
				// go to current file first segment
				currFileFirstSegmentId = $(UI.currentFile).attr('id').split('-')[1];
				$.each(config.firstSegmentOfFiles, function() {
					if (this.id_file == currFileFirstSegmentId)
						firstSegId = this.first_segment;
				});
				UI.scrollSegment($('#segment-' + firstSegId));
				UI.tempViewPoint = $(UI.currentFile).attr('id').split('-')[1];
			}
			$.each(config.firstSegmentOfFiles, function() {
				console.log(this.id_file);
			});
		});

// Search and replace

		$(".searchbox input, .searchbox select").bind('keydown', 'return', function(e) {
			e.preventDefault();
			if ($("#exec-find").attr('disabled') != 'disabled')
				$("#exec-find").click();
		});

		$("#exec-find").click(function(e) {
			e.preventDefault();
			if ($(this).attr('data-func') == 'find') {
				UI.execFind();
			} else {
				if (!UI.goingToNext) {
					UI.goingToNext = true;
					UI.execNext();
				}

			}
		});
		$("#exec-cancel").click(function(e) {
			e.preventDefault();
			$("#filterSwitch").click();
			UI.body.removeClass('searchActive');
			UI.clearSearchMarkers();
			UI.clearSearchFields();
			UI.setFindFunction('find');
			$('#exec-find').removeAttr('disabled');
			$('#exec-replace, #exec-replaceall').attr('disabled', 'disabled');
			UI.enableTagMark();
			if (UI.segmentIsLoaded(UI.currentSegmentId)) {
				UI.gotoOpenSegment();
			} else {
				UI.render({
					firstLoad: false,
					segmentToOpen: UI.currentSegmentId
				});
			}

		});
		$("#exec-replaceall").click(function(e) {
			e.preventDefault();
			APP.confirm({
				name: 'confirmReplaceAll',
				cancelTxt: 'Cancel',
				callback: 'execReplaceAll',
				okTxt: 'Continue',
				msg: "Do you really want to replace this text in all search results? <br>(The page will be refreshed after confirm)"
			});
		});
		$("#exec-replace").click(function(e) {
            console.log('ddd');
			e.preventDefault();
            console.log('a');
			if ($('#search-target').val() == $('#replace-target').val()) {
				APP.alert({msg: 'Attention: you are replacing the same text!'});
				return false;
			}
            console.log('b');

			if (UI.searchMode == 'onlyStatus') {
				
//			} else if (UI.searchMode == 'source&target') {

			} else {
				txt = $('#replace-target').val();
				// todo: rifai il marksearchresults sul target

				$("mark.currSearchItem").text(txt);
				segment = $("mark.currSearchItem").parents('section');
                segment_id = $(segment).attr('id').split('-')[1];
                status = UI.getStatus(segment);
                byStatus = 0;

                UI.setTranslation($(segment).attr('id').split('-')[1], status, 'replace');
                UI.setContribution(segment_id, status, byStatus);

                UI.updateSearchDisplayCount(segment);
				$(segment).attr('data-searchItems', $('mark.searchMarker', segment).length);

				UI.gotoNextResultItem(true);
			}
            console.log('c');

        });
		$("#enable-replace").on('change', function() {
			if (($('#enable-replace').is(':checked')) && ($('#search-target').val() !== '')) {
				$('#replace-target, #exec-replace, #exec-replaceall').removeAttr('disabled');
			} else {
				$('#replace-target, #exec-replace, #exec-replaceall').attr('disabled', 'disabled');
			}
		});
		$("#search-source, #search-target").on('input', function() {
			if (UI.checkSearchChanges()) {
				UI.setFindFunction('find');
			}
		});
		$("#search-target").on('input', function() {
			if ($(this).val() === '') {
				$('#replace-target, #exec-replace, #exec-replaceall').attr('disabled', 'disabled');
			} else {
				if ($('#enable-replace').is(':checked'))
					$('#replace-target, #exec-replace, #exec-replaceall').removeAttr('disabled');
			}
		});
		$("#select-status").on('change', function() {
			if (UI.checkSearchChanges()) {
				UI.setFindFunction('find');
			}
		});
		$("#match-case, #exact-match").on('change', function() {
			UI.setFindFunction('find');
		});
		this.initEnd = new Date();
		this.initTime = this.initEnd - this.initStart;
		if (this.debug)
			console.log('Init time: ' + this.initTime);
		
	}
});


