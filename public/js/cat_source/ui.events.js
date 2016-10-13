/*
	Component: ui.events
 */
$.extend(UI, {
	bindShortcuts: function() {
		$("body").removeClass('shortcutsDisabled');
		$("body").on('keydown.shortcuts', null, UI.shortcuts.translate.keystrokes.standard, function(e) {
			e.preventDefault();
			if ( config.isReview ) {
				$('body.review .editor .approved').click();
			} else {
				if ( $('.editor .translated').length > 0 ) {
					$('.editor .translated').click();
				} else if ( $('.editor .guesstags').length > 0 ) {
					$('.editor .guesstags').click();
				}
			}
		}).on('keydown.shortcuts', null, UI.shortcuts.translate.keystrokes.mac, function(e) {
			e.preventDefault();
			if ($('.editor .translated').length > 0) {
				$('.editor .translated').click();
			} else {
				$('.editor .guesstags').click();
			}
            $('body.review .editor .approved').click();
		}).on('keydown.shortcuts', null, UI.shortcuts.translate_nextUntranslated.keystrokes.standard, function(e) {
			e.preventDefault();
			$('.editor .next-untranslated').click();
			$('.editor .next-unapproved').click();
		}).on('keydown.shortcuts', null, UI.shortcuts.translate_nextUntranslated.keystrokes.mac, function(e) {
			e.preventDefault();
			$('.editor .next-untranslated').click();
			$('.editor .next-unapproved').click();
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

        var resetTextArea = _.debounce( function () {
            console.debug( 'resetting') ;
            var $this = $(this);
            var maxHeight = $this.data('maxheight');
            var minHeight = $this.data('minheight');

            var borderTopWidth = parseFloat( $this.css( "borderTopWidth" ) );
            var borderBottomWidth = parseFloat( $this.css( "borderBottomWidth" ) );
            var borders = borderTopWidth + borderBottomWidth;
            var scrollHeightWithBorders = this.scrollHeight + borders;

            while ( scrollHeightWithBorders > $this.outerHeight() && $this.height() < maxHeight ) {
                $this.height( $this.height() + 10 );
            }
            ;

            while ( scrollHeightWithBorders <= $this.outerHeight() && $this.height() > minHeight ) {
                $this.height( $this.height() - 10 );
            }

            if ( $this.height() >= maxHeight ) {
                $this.css( "overflow-y", "auto" );
            } else {
                $this.css( "overflow-y", "hidden" );
            }
        }, 100 );

        $( document ).on( 'keydown', '.mc-resizable-textarea', resetTextArea );
        $( document ).on( 'paste', '.mc-resizable-textarea', function () {
            setTimeout( function ( el ) {
                resetTextArea.call( el );
            }, 100, this );
        } );

        $(document).on('segment:status:change', function(e, segment, options) {
            var status = options.status ;
            var next = UI.getNextSegment( segment.el, 'untranslated' );

            if ( ! next ) {
                $(window).trigger({
                    type: "allTranslated"
                });
            }
        });

		$("body").on('keydown', null, 'ctrl+1', function(e) {
			e.preventDefault();
			var tab;
			var active = $('.editor .submenu li.active');
			if(active.hasClass('tab-switcher-tm')) {
				tab = 'matches';
				$('.editor .tab.' + tab + ' .graysmall[data-item=1]').trigger('dblclick');
			} else if(active.hasClass('tab-switcher-al')) {
				tab = 'alternatives';
				$('.editor .tab.' + tab + ' .graysmall[data-item=1]').trigger('dblclick');
			}
		}).on('keydown', null, 'ctrl+2', function(e) {
			e.preventDefault();
			var tab;
			var active = $('.editor .submenu li.active');
			if(active.hasClass('tab-switcher-tm')) {
				tab = 'matches';
				$('.editor .tab.' + tab + ' .graysmall[data-item=2]').trigger('dblclick');
			} else if(active.hasClass('tab-switcher-al')) {
				tab = 'alternatives';
				$('.editor .tab.' + tab + ' .graysmall[data-item=2]').trigger('dblclick');
			}
		}).on('keydown', null, 'ctrl+3', function(e) {
			e.preventDefault();
			var tab;
			var active = $('.editor .submenu li.active');
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

                var node = document.createElement("span");
                node.setAttribute('class', 'marker monad space-marker lastInserted');
                node.setAttribute('contenteditable', 'false');
                node.textContent = htmlDecode(" ");

                insertNodeAtCursor(node);
                UI.unnestMarkers();
            }

		})
                .on('keydown', '.editor .editarea', 'ctrl+shift+space', function(e) {
            if(!UI.hiddenTextEnabled) return;
			e.preventDefault();
            UI.editarea.find('.lastInserted').removeClass('lastInserted');

			var node = document.createElement("span");
			node.setAttribute('class', 'marker monad nbsp-marker lastInserted ' + config.nbspPlaceholderClass);
			node.setAttribute('contenteditable', 'false');
			node.textContent = htmlDecode("&nbsp;");
			insertNodeAtCursor(node);
			UI.unnestMarkers();

        });

		$("body").bind('keydown', 'Ctrl+c', function() {
			UI.tagSelection = false;
		}).bind('keydown', 'Meta+shift+l', function() {
            UI.openLanguageResourcesPanel();
        }).bind('keydown', 'Meta+c', function() {
			UI.tagSelection = false;
        }).bind('keydown', 'Meta+shift+s', function(e) {
            UI.body.toggleClass('tagmode-default-extended');
        }).on('click','#cmn-toggle-1',function(e){
            LXQ.toogleHighlighting();
        }).on('click', '.tagModeToggle', function(e) {
            e.preventDefault();
            UI.toggleTagsMode(this);
            if(typeof UI.currentSegment != 'undefined') UI.pointToOpenSegment(true);
		} );

		$("body").on('click', '.autofillTag', function(e){
			e.preventDefault();

			//get source tags from the segment
			var sourceTags = $( '.source', UI.currentSegment ).html()
					.match( /(&lt;\s*\/*\s*(g|x|bx|ex|bpt|ept|ph|it|mrk)\s*.*?&gt;)/gi );

			//get target tags from the segment
			var targetTags = $( '.target', UI.currentSegment ).html()
					.match( /(&lt;\s*\/*\s*(g|x|bx|ex|bpt|ept|ph|it|mrk)\s*.*?&gt;)/gi );

			if(targetTags == null ) {
				targetTags = [];
			}

			var missingTags = sourceTags;
			//remove from source tags all the tags in target segment
			for(var i = 0; i < targetTags.length; i++ ){
				var pos = missingTags.indexOf(targetTags[i]);
				if( pos > -1){
					missingTags.splice(pos,1);
				}
			}

			var undoCursorPlaceholder = $('.undoCursorPlaceholder', UI.currentSegment ).detach();
			var brEnd = $('br.end', UI.currentSegment ).detach();

			//add tags into the target segment
			for(var i = 0; i < missingTags.length; i++){
				var addTagClosing = false;

				UI.editarea.html(
					UI.editarea.html() + missingTags[i]
				);
			}

			//add again undoCursorPlaceholder
			UI.editarea.append(undoCursorPlaceholder )
					   .append(brEnd);

			//lock tags and run again getWarnings
			UI.lockTags(UI.editarea);
			UI.currentSegmentQA();

		}).on('click', '.tagLockCustomize', function(e) {
			e.preventDefault();
			$(this).toggleClass('unlock');
			if ($(this).hasClass('unlock')) {
				UI.disableTagMark();
			} else {
				UI.enableTagMark();
			}
			UI.setTagLockCustomizeCookie(false);
		}).on('click', '#settingsSwitcher', function(e) {
			e.preventDefault();
			UI.unbindShortcuts();
			$('.popup-settings').show();

        // start addtmx
        }).on('click', '.open-popup-addtm-tr', function(e) {
            e.preventDefault();
            UI.openLanguageResourcesPanel();
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

            UI.editarea.html(UI.editarea.html().replace(/<span class="tag-autocomplete-endcursor"><\/span>&lt;/gi, '&lt;<span class="tag-autocomplete-endcursor"></span>'));

            UI.editarea.find('.rangySelectionBoundary').before(UI.editarea.find('.rangySelectionBoundary + .tag-autocomplete-endcursor'));

            UI.editarea.html(UI.editarea.html().replace(/&lt;(?:[a-z]*(?:&nbsp;)*["<\->\w\s\/=]*)?(<span class="tag-autocomplete-endcursor">)/gi, '$1'));

            UI.editarea.html(UI.editarea.html().replace(/&lt;(?:[a-z]*(?:&nbsp;)*["\w\s\/=]*)?(<span class="tag-autocomplete-endcursor"\>)/gi, '$1'));

            UI.editarea.html(UI.editarea.html().replace(/&lt;(?:[a-z]*(?:&nbsp;)*["\w\s\/=]*)?(<span class="undoCursorPlaceholder monad" contenteditable="false"><\/span><span class="tag-autocomplete-endcursor"\>)/gi, '$1'));

            UI.editarea.html(UI.editarea.html().replace(/(<span class="tag-autocomplete-endcursor"\><\/span><span class="undoCursorPlaceholder monad" contenteditable="false"><\/span>)&lt;/gi, '$1'));

			saveSelection();
			if(!$('.rangySelectionBoundary', UI.editarea).length) { // click, not keypress
				setCursorPosition(document.getElementsByClassName("tag-autocomplete-endcursor")[0]);
				saveSelection();
			}
			var ph = $('.rangySelectionBoundary', UI.editarea)[0].outerHTML;
			$('.rangySelectionBoundary', UI.editarea).remove();
			$('.tag-autocomplete-endcursor', UI.editarea).after(ph);
			$('.tag-autocomplete-endcursor').before(htmlEncode($(this).text()));
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
				s.html(s.html() + '<span class="char">' + UI.viewShortcutSymbols('&#' + symbol) + '</span>' + '+');
				console.log(s.html());
			}
			if($('span', s).length > 2) {
				UI.writeNewShortcut(c, s, this);
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
		}).on('cachedSegmentObjects', function() {
            if(UI.currentSegmentId == UI.firstWarnedSegment) UI.setNextWarnedSegment();
		}).on('allTranslated', function() {
			if(config.survey) UI.displaySurvey(config.survey);
		}).on('mousedown', function(e) {
			if ($(e.target).hasClass("editarea")) {
				return;
			}
            //when the catoool is not loaded because of the job is archived,
            // saveSelection leads to a javascript error
            //so, add a check to see if the cattool page is really created/loaded
            if( $('body' ).hasClass( '.job_archived' ) || $('body' ).hasClass( '.job_cancelled' ) ){
                return false;
            }
			/*Show the cursor position to paste the glossary item (Ex: check dbclick)
			 We have to know the old cursor position when clicking for example
			 on a glossary item to paste the text in the correct position
			 */

            if(!$('.editor .rangySelectionBoundary.focusOut').length) {
                if(!UI.isSafari) saveSelection();
            }

            $('.editor .rangySelectionBoundary').addClass('focusOut');

            $(
                '.editor .search-source .rangySelectionBoundary.focusOut,' +
                '.editor .search-target .rangySelectionBoundary.focusOut'
            ).remove();

            if ( UI.editarea != '') {
                var hasFocusBefore = UI.editarea.is(":focus");
                setTimeout(function() {
                    var hasFocusAfter = UI.editarea.is(":focus");
                    if(hasFocusBefore && hasFocusAfter){
                        $('.editor .rangySelectionBoundary.focusOut').remove();
						UI.editarea.get(0).normalize();
                    }
                }, 600);
            }
        });

		window.onbeforeunload = function(e) {
			goodbye(e);
		};

		$("#filterSwitch").bind('click', function(e) {
			UI.toggleSearch(e);
		});
		$("#advancedOptions").bind('click', function(e) {
			e.preventDefault();
			UI.openOptionsPanel();
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

		//overlay

		$(".x-stats").click(function() {
			$(".stats").toggle();
		});

		$("#outer").on('click', 'a.sid', function(e) {
			e.preventDefault();
			e.stopPropagation();
			return false;
		}).on('click', 'a.status', function(e) {
			e.preventDefault();
			e.stopPropagation();
		}).on('click', 'section:not(.readonly) a.status', function() {
			var section = $(this).closest("section");
			var statusMenu = $("ul.statusmenu", section);

			UI.createStatusMenu(statusMenu, section );

			statusMenu.show();

			$('html').bind("click.outOfStatusMenu", function() {
				$("ul.statusmenu").hide();
				$('html').unbind('click.outOfStatusMenu');
				UI.removeStatusMenu(statusMenu);
			});
		}).on('click', 'section.readonly, section.readonly a.status', function(e) {
            e.preventDefault();
			var section = $(e.target).closest('section');
			UI.handleClickOnReadOnly( section );
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
		}).on('click', '.glossary .switch-editing', function() {
			UI.updateGlossary($(this).closest(".graysmall"));
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


        // This is where we decide if a segment is to close or not.
        // Beware that closeSegment is also called on openSegment
        // ( other segments are closed when a new one is opened ).
        //
        $('#outer').click(function(e) {

             var close = function() {
                UI.setEditingSegment( null );
                UI.closeSegment(UI.currentSegment, 1);
            };

            if ( $(e.target).parents('body') ) return ; // detatched from DOM
            if ( eventFromReact(e) ) return;

            if ( $(e.target).closest('section .sid').length ) close()  ;
            if ( $(e.target).closest('section .segment-side-buttons').length ) close();

            if ( !$(e.target).closest('section').length ) close();
        });

		$('html').click(function() {
			$(".menucolor").hide();
        // }).on('click', 'section .sid, section .segment-side-buttons', function(e){
        //     // TODO: investigate the neeed for '.segment-side-buttons'
        //     if ( ! eventFromReact(e) ) {
        //         UI.closeSegment(UI.currentSegment, 1);
        //     }
        }).on('click', 'section .actions', function(e){
            e.stopPropagation();
        }).on('click', '#quality-report', function(e){
            var win = window.open( $('#quality-report' ).data('url') , '_self');
            win.focus();
        }).on('keydown', function(e) {

            var esc = 27 ;

            // ESC should close the current segment only if `article` is not
            // resized to let space to the tools on the sidebar.

            var handleEscPressed = function() {
                if ( UI.body.hasClass('editing') &&
                    !UI.body.hasClass('side-tools-opened') ) {
                        UI.setEditingSegment( null );
                        UI.closeSegment(UI.currentSegment, 1);
                    }
            }

            if ( e.which == esc ) handleEscPressed() ;

        }).on('click', '#previewDropdown .downloadTranslation a', function(e) {
            e.preventDefault();
            runDownload();
		}).on('click', '#previewDropdown .previewLink a', function(e) {
			e.preventDefault();
			runDownload();
		}).on('click', '#previewDropdown a.tmx', function(e) {
			e.preventDefault();
			window.open($(this).attr('href'));
		}).on('click', '#downloadProject', function(e) {
            e.preventDefault();
            runDownload();
		}).on('mousedown', '.header-menu .originalDownload, .header-menu .sdlxliff, .header-menu .omegat', function( e ){
            if( e.which == 1 ){ // left click
                e.preventDefault();
                var iFrameDownload = $( document.createElement( 'iframe' ) ).hide().prop( {
                    id: 'iframeDownload_' + new Date().getTime() + "_" + parseInt( Math.random( 0, 1 ) * 10000000 ),
                    src: $( e.currentTarget ).attr( 'href' )
                } );
                $( "body" ).append( iFrameDownload );

                //console.log( $( e.currentTarget ).attr( 'href' ) );
            }
        }).on('click', '#previewDropdown .originalsGDrive a', function(e) {
            UI.continueDownloadWithGoogleDrive( 1 );
        }).on('click', '.alert .close', function(e) {
			e.preventDefault();
			$('.alert').remove();
		}).on('click', '#contextMenu #searchConcordance', function() {
			if ($('#contextMenu').attr('data-sid') == UI.currentSegmentId) {
				UI.openConcordance();
			} else {
				$('#segment-' + $('#contextMenu').attr('data-sid') + ' .editarea').trigger('click', ['clicking', 'openConcordance']);
			}
		}).on('click', '#checkConnection', function(e) {
			e.preventDefault();
			UI.checkConnection( 'Click from Human Authorized' );
		}).on('click', '#statistics .meter a', function(e) {
			e.preventDefault();
			UI.gotoNextUntranslatedSegment();
		});

		$("#outer").on('click', 'a.percentuage', function(e) {
			e.preventDefault();
			e.stopPropagation();
		}).on('mouseup', '.editarea', function() { //mouseupeditarea
            if(UI.editarea != '' && !UI.editarea.find('.locked.selected').length) {
                if(!$(window.getSelection().getRangeAt(0))[0].collapsed) { // there's something selected
                    UI.showEditToolbar();
                }
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
        }).on('click', '.footerSwitcher', function(e) {
            UI.switchFooter();
		}).on('click', '.editarea', UI.editAreaClick
        ).on('keydown', '.editor .source, .editor .editarea', UI.shortcuts.searchInConcordance.keystrokes.mac, function(e) {
			e.preventDefault();
			UI.preOpenConcordance();
		}).on('keydown', '.editor .source, .editor .editarea', UI.shortcuts.searchInConcordance.keystrokes.standard, function(e) {
			e.preventDefault();
			UI.preOpenConcordance();

        }).on('keyup', '.editor .editarea', 'return', function(e) {
            e.preventDefault();
            return false;
        }).on('keydown', '.editor .editarea', 'return', function(e) {
            e.preventDefault();
        }).on('keypress', '.editor .editarea', function(e) {

			if((e.which == 60)&&(UI.taglockEnabled)) { // opening tag sign
				if($('.tag-autocomplete').length) {
					e.preventDefault();
					return false;
				}
				UI.openTagAutocompletePanel();
            }
			if((e.which == 62)&&(UI.taglockEnabled)) { // closing tag sign
				if($('.tag-autocomplete').length) {
					e.preventDefault();
					return false;
				}
			}
			setTimeout(function() {
				if($('.tag-autocomplete').length) {
                    tempStr = UI.editarea.html().match(/<span class="tag-autocomplete-endcursor"\><\/span>&lt;/gi);
                    UI.stripAngular = (!tempStr)? false : (!tempStr.length)? false : true;

					if(UI.editarea.html().match(/^(<span class="tag-autocomplete-endcursor"\><\/span>&lt;)/gi) !== null) {
						UI.editarea.html(UI.editarea.html().replace(/^(<span class="tag-autocomplete-endcursor"\><\/span>&lt;)/gi, '&lt;<span class="tag-autocomplete-endcursor"><\/span>'));
					}
					UI.checkAutocompleteTags();
				}
			}, 50);
            if (!UI.body.hasClass('searchActive')) {

                if(UI.isCJK && ( (e.which == '60') || (e.which == '62') ) ) {
                } else {
                    setTimeout(function() {
                        UI.lockTags(UI.editarea);
                    }, 10);
                }
            }
		}).on('keydown', '.editor .editarea', function(e) {

            if ((e.which == 8) && (!UI.body.hasClass('tagmode-default-extended'))) {
				return true;
				// ONly for console.log
                // var rangeObject = getRangeObject(window.getSelection());
                // for(var key in rangeObject.endContainer) {
                //     console.log('key: ' + key + '\n' + 'value: "' + rangeObject[key] + '"');
                // }
            }

			if ((e.which == 8) || (e.which == 46)) { // backspace e canc(mac)
				if ($('.selected', $(this)).length) {
					e.preventDefault();
					$('.selected', $(this)).remove();
					UI.saveInUndoStack('cancel');
					UI.currentSegmentQA();
				} else {
					var numTagsBefore = (UI.editarea.text().match(/<.*?\>/gi) !== null)? UI.editarea.text().match(/<.*?\>/gi).length : 0;
                    var numSpacesBefore = $('.space-marker', UI.editarea).length;
					saveSelection();

					parentTag = $('span.locked', UI.editarea).has('.rangySelectionBoundary');
					isInsideTag = $('span.locked .rangySelectionBoundary', UI.editarea).length;
					parentMark = $('.searchMarker', UI.editarea).has('.rangySelectionBoundary');
					isInsideMark = $('.searchMarker .rangySelectionBoundary', UI.editarea).length;

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
						parentTag.remove();
						e.preventDefault();
					}

					setTimeout(function() {
                        saveSelection();
                        // detect if selection ph is inside a monad tag
                        if($('.monad .rangySelectionBoundary', UI.editarea).length) {
                            $('.monad:has(.rangySelectionBoundary)', UI.editarea).after($('.monad .rangySelectionBoundary', UI.editarea));
                            // move selboundary after the
                        }
                        restoreSelection();
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
                setTimeout(function() {
                    UI.checkTagProximity();
                }, 10);

				if (range.startOffset != range.endOffset) { // if something is selected when the left button is pressed...
					r = range.startContainer.data;
					if ((r[0] == '<') && (r[r.length - 1] == '>')) { // if a tag is selected
                        e.preventDefault();


                        saveSelection();
                        rr = document.createRange();
                        referenceNode = $('.rangySelectionBoundary', UI.editarea).first().get(0);
                        rr.setStartBefore(referenceNode);
                        rr.setEndBefore(referenceNode);
                        $('.rangySelectionBoundary', UI.editarea).remove();

					}
				} else { // there's nothing selected
                }
				UI.closeTagAutocompletePanel();
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
                setTimeout(function() {
                    UI.checkTagProximity();
                }, 10);
			}
			if (e.which == 39) { // right arrow
				selection = window.getSelection();
				range = selection.getRangeAt(0);
                setTimeout(function() {
                    UI.checkTagProximity();
                }, 10);

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
                setTimeout(function() {
                    UI.checkTagProximity();
                }, 10);
			}

            if (((e.which == 37) || (e.which == 38) || (e.which == 39) || (e.which == 40) || (e.which == 8))) { // not arrows, backspace, canc
                UI.saveInUndoStack('arrow');
            }

			if (!((e.which == 37) || (e.which == 38) || (e.which == 39) || (e.which == 40) || (e.which == 8) || (e.which == 46) || (e.which == 91))) { // not arrows, backspace, canc or cmd
				if (UI.body.hasClass('searchActive')) {
					UI.resetSearch();
				}
			}
			// if (e.which == 32) { // space
			// 	setTimeout(function() {
			// 		UI.saveInUndoStack('space');
			// 	}, 100);
			// }

			if (e.which == 13) { // return
				if($('.tag-autocomplete').length) {
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

		}).on('input', '.editarea', function( e ) { //inputineditarea
			UI.currentSegment.addClass('modified').removeClass('waiting_for_check_result');
			UI.currentSegment.data('modified', true);
			UI.currentSegment.trigger('modified');

			if (UI.droppingInEditarea) {
				UI.cleanDroppedTag(UI.editarea, UI.beforeDropEditareaHTML);
			}

			if ( UI.hasSourceOrTargetTags( e.target ) ) {
				UI.currentSegment.addClass( 'hasTagsToggle' );
			} else {
				UI.currentSegment.removeClass( 'hasTagsToggle' );
			}

			if ( UI.hasMissingTargetTags( $(e.target).closest('section') ) ) {
				UI.currentSegment.addClass( 'hasTagsAutofill' );
			} else {
				UI.currentSegment.removeClass( 'hasTagsAutofill' );
			}

			UI.registerQACheck();
                if(UI.isKorean && ( (e.which == '60') || (e.which == '62') || (e.which == '32')) ) {
                } else {
                    UI.lockTags(UI.editarea);
                }
        }).on('input', '.editor .cc-search .input', function() {
			UI.markTagsInSearch($(this));
		}).on('click', '.editor .source .locked,.editor .editarea .locked', function(e) {
			e.preventDefault();
			e.stopPropagation();
            if($(this).hasClass('selected')) {
                $(this).removeClass('selected');
                setCursorPosition(this, 'end');
            } else {
                setCursorPosition(this);
                selectText(this);
                $(this).toggleClass('selected');
				if(!UI.body.hasClass('tagmode-default-extended')) $('.editor .tagModeToggle').click();
            }

		}).on('mousedown', '.source', function(e) {
			if (e.button == 2) { // right click
				// temporarily disabled
				return true;
			}
			return true;
		}).on('dragstart', '.editor .editarea .locked', function() {
            // To stop the drag in tags elements
            return false;
		}).on('drop', '.editor .editarea', function(e) {
			if (e.stopPropagation) {
				e.stopPropagation(); // stops the browser from redirecting.
			}
			UI.beforeDropEditareaHTML = UI.editarea.html();
			UI.droppingInEditarea = true;
			setTimeout(function() {
                UI.lockTags(UI.editarea);
                UI.saveInUndoStack('drop');
            }, 100);
		}).on('drop paste', '.editor .cc-search .input, .editor .gl-search .input', function() {
			UI.beforeDropSearchSourceHTML = UI.editarea.html();
			UI.currentConcordanceField = $(this);
			setTimeout(function() {
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
			UI.clickOnTranslatedButton(e, this);
		}).on('click', 'a.guesstags', function(e) {
			// Tag Projection: handle click on "GuesssTags" button, retrieve the translation and place it
			// in the current segment
			e.preventDefault();
			UI.hideEditToolbar();
			UI.startSegmentTagProjection();
			return false;
		}).on('click', 'a.d, a.a, a.r, a.f, a.fx, a.rb', function() {
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
        }).on('click', 'a.fx', function() {
			UI.changeStatus(this, 'fixed', 1);
        }).on('click', 'a.rb', function() {
			UI.changeStatus(this, 'rebutted', 1);
		}).on('click', '.editor .outersource .copy', function(e) {
			e.preventDefault();
			UI.copySource();
		}).on('click', '.tagmenu, .warning, .viewer, .notification-box li a', function() {
			return false;
        }).on('click', 'section .footer .tab-switcher', function(e) {
            e.preventDefault();
			if(UI.body.hasClass('hideMatches')) UI.switchFooter();

        }).on('showMatchesLocal', '.editor', function(e) {
            UI.currentSegment.find('.footer').addClass('showMatches');
        }).on('click', '.tab-switcher-tm', function(e) {
			e.preventDefault();
			$('.editor .submenu .active').removeClass('active');
			$(this).addClass('active');
			$('.editor .sub-editor').removeClass('open');
			$('.editor .sub-editor.matches').addClass('open');
		}).on('click', '.alternatives a', function(e) {
			e.preventDefault();
			$('.editor .tab-switcher-al').click();
		}).on('click', '.sub-editor.glossary .overflow a.trash', function(e) {
			e.preventDefault();
			ul = $(this).parents('ul.graysmall').first();
			UI.deleteGlossaryItem($(this).parents('ul.graysmall').first());
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
        }).on('keydown', function(e) {
            if((e.which == 27) && ($('.modal[data-name=confirmAutopropagation]').length)) {
                $('.modal[data-name=confirmAutopropagation] .btn-ok').click();
                e.preventDefault();
                e.stopPropagation();
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
		}).on('keydown', '.sub-editor .gl-search .search-source', function(e) {
			if (e.which == 13) {
				e.preventDefault();
				var txt = $(this).text();
				if (txt.length > 2) {
					segment = $(this).parents('section').first();
					UI.getGlossary(segment, false);
				}
			}
		}).on('keydown', '.sub-editor .gl-search .search-target, .sub-editor .gl-search .comment .gl-comment', function(e) {
			if (e.which == 13) {
				e.preventDefault();
				UI.setGlossaryItem();
			}
		}).on('keydown', '.sub-editor .glossary-add-comment .gl-comment', function(e) {
			if (e.which == 13) {
				e.preventDefault();
				UI.addGlossaryComment($(this));
			}
		}).on('input', '.sub-editor .gl-search .search-target', function() {
			gl = $(this).parents('.gl-search').find('.set-glossary');
			if($(this).text() === '') {
				gl.addClass('disabled');
			} else {
				gl.removeClass('disabled');
			}
		}).on('click', '.sub-editor .gl-search .set-glossary:not(.disabled)', function(e) {
			e.preventDefault();
			UI.setGlossaryItem();
		}).on('click', '.sub-editor .gl-search .comment a, .sub-editor .glossary-add-comment a', function(e) {
			e.preventDefault();
			$(this).parents('.comment, .glossary-add-comment').find('.gl-comment').toggle().focus();
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
            UI.lockTags(UI.editarea);

		}).on('click', 'a.close', function(e, param) {
			e.preventDefault();
			var save = (typeof param == 'undefined') ? 'noSave' : param;
			UI.closeSegment(UI.currentSegment, 1, save);
		}).on('click', '.concordances .more', function(e) {
			e.preventDefault();
			tab = $(this).parents('.concordances');
			container = $('.overflow', $(tab));
			if($(tab).hasClass('extended')) {
				UI.setExtendedConcordances(false);
			} else {
				UI.setExtendedConcordances(true);
			}
			$(this).parents('.matches').toggleClass('extended');
		}).on('click', '.tagMode .crunched', function(e) {
            e.preventDefault();
            UI.setCrunchedTagMode();
        }).on('click', '.tagMode .extended', function(e) {
            e.preventDefault();
            UI.setExtendedTagMode();
        });

		$("#outer").on('click', '.tab.alternatives .graysmall .goto a', function(e) {
			e.preventDefault();
			UI.scrollSegment($('#segment-' + $(this).attr('data-goto')), true);
			UI.highlightEditarea($('#segment-' + $(this).attr('data-goto')));
		});
		UI.toSegment = true;

        if(!$('#segment-' + this.startSegmentId).length) {
            if($('#segment-' + this.startSegmentId + '-1').length) {
                if ( typeof this.startSegmentId != 'undefined' ) {
                    this.startSegmentId = this.startSegmentId + '-1';
                }
            }
        }

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

		$("#point2seg").bind('mousedown', function(e) {
			e.preventDefault();
            UI.saveSegment(UI.currentSegment);
			UI.scrollSegment($('#segment-' + $(this).attr('data-segment')));
            UI.setNextWarnedSegment();
		});

		$("#navSwitcher").on('click', function(e) {
			e.preventDefault();
		});

		$("#pname").on('click', function(e) {
			e.preventDefault();
			UI.toggleFileMenu();
		});

		$("#jobMenu").on('click', 'li:not(.currSegment)', function(e) {
			e.preventDefault();
            UI.saveSegment(UI.currentSegment);
			UI.renderAndScrollToSegment($(this).attr('data-segment'));
		});
		$("#jobMenu").on('click', 'li.currSegment', function(e) {
			e.preventDefault();
			UI.pointToOpenSegment();
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
            UI.markGlossaryItemsInSource(UI.cachedGlossaryData);

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
			e.preventDefault();
			var replaceTarget = $('#replace-target').val();
			if ($('#search-target').val() == replaceTarget) {
				APP.alert({msg: 'Attention: you are replacing the same text!'});
				return false;
			}

			if (UI.searchMode !== 'onlyStatus') {

				// todo: redo marksearchresults on the target

				$("mark.currSearchItem").text(replaceTarget);
				var segment = $("mark.currSearchItem").parents('section');
                var status = UI.getStatus(segment);

                UI.setTranslation({
                    id_segment: $(segment).attr('id').split('-')[1],
                    status: status,
                    caller: 'replace'
                });

                UI.updateSearchDisplayCount(segment);
				$(segment).attr('data-searchItems', $('mark.searchMarker', segment).length);

                if(UI.numSearchResultsSegments > 1) UI.gotoNextResultItem(true);
			}

        });
		$("#enable-replace").on('change', function() {
			if ($('#enable-replace').is(':checked') && $('#search-target').val() != "") {
				$('#exec-replace, #exec-replaceall').removeAttr('disabled');
			} else {
				$('#exec-replace, #exec-replaceall').attr('disabled', 'disabled');
			}
		});
		$("#search-source, #search-target").on('input', function() {
			if (UI.checkSearchChanges()) {
				UI.setFindFunction('find');
				$("#enable-replace").change();
			}
		});
        $('#replace-target').on('focus', function() {
            if(!$('#enable-replace').prop('checked')) {
                $('label[for=enable-replace]').trigger('click');
                $('#replace-target').focus();
            }
        });
        $('#replace-target').on('input', function() {
            if($(this).val() != '') {
                if(!$('#enable-replace').prop('checked')) $('label[for=enable-replace]').trigger('click');
            }
            UI.checkReplaceAvailability();
        });
		$("#search-target").on('input', function() {
            UI.checkReplaceAvailability();
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
		if (this.debug) { console.log('Init time: ' + this.initTime); }

    }
});

$(document).on('ready', function() {
	window.quality_report_btn_component = ReactDOM.render(
		React.createElement( Review_QualityReportButton, {
			vote                : config.overall_quality_class,
			quality_report_href : config.quality_report_href
		}), $('#quality-report-button')[0] );

});

