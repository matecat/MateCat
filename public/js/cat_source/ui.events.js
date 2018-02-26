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
		}).on('keydown.shortcuts', null, UI.shortcuts.undoInSegment.keystrokes.standard, function(e) {
			e.preventDefault();
			UI.undoInSegment(UI.currentSegment);
			UI.closeTagAutocompletePanel();
		}).on('keydown.shortcuts', null, UI.shortcuts.undoInSegment.keystrokes.mac, function(e) {
			e.preventDefault();
			UI.undoInSegment(UI.currentSegment);
			UI.closeTagAutocompletePanel();
		}).on('keydown.shortcuts', null, UI.shortcuts.redoInSegment.keystrokes.standard, function(e) {
			e.preventDefault();
			UI.redoInSegment(UI.currentSegment);
		}).on('keydown.shortcuts', null, UI.shortcuts.redoInSegment.keystrokes.mac, function(e) {
			e.preventDefault();
			UI.redoInSegment(UI.currentSegment);
		}).on('keydown.shortcuts', null, UI.shortcuts.openSearch.keystrokes.standard, function(e) {
            if((UI.searchEnabled)&&($('#filterSwitch').length)) UI.toggleSearch(e);
		}).on('keydown.shortcuts', null, UI.shortcuts.openSearch.keystrokes.mac, function(e) {
            if((UI.searchEnabled)&&($('#filterSwitch').length)) UI.toggleSearch(e);
		});

		if (UI.isMac) {
			$("body").on('keydown.shortcuts', null, UI.shortcuts.copySource.keystrokes.mac, function(e) {
				e.preventDefault();
				UI.copySource();
			});
		} else {
			$("body").on('keydown.shortcuts', null, UI.shortcuts.copySource.keystrokes.standard, function(e) {
				e.preventDefault();
				UI.copySource();
			});
		}
	},
	unbindShortcuts: function() {
		$("body").off(".shortcuts").addClass('shortcutsDisabled');
	},
	setEvents: function() {
		this.bindShortcuts();
		this.setEditAreaEvents();
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
				SegmentActions.chooseContribution(UI.getSegmentId(UI.currentSegment), 1);
			} else if(active.hasClass('tab-switcher-al')) {
				tab = 'alternatives';
				$('.editor .tab.' + tab + ' .graysmall[data-item=1]').trigger('dblclick');
			}
		}).on('keydown', null, 'ctrl+2', function(e) {
			e.preventDefault();
			var tab;
			var active = $('.editor .submenu li.active');
			if(active.hasClass('tab-switcher-tm')) {
				SegmentActions.chooseContribution(UI.getSegmentId(UI.currentSegment), 2);
			} else if(active.hasClass('tab-switcher-al')) {
				tab = 'alternatives';
				$('.editor .tab.' + tab + ' .graysmall[data-item=2]').trigger('dblclick');
			}
		}).on('keydown', null, 'ctrl+3', function(e) {
			e.preventDefault();
			var tab;
			var active = $('.editor .submenu li.active');
			if(active.hasClass('tab-switcher-tm')) {
				SegmentActions.chooseContribution(UI.getSegmentId(UI.currentSegment), 3);
			} else if(active.hasClass('.tab-switcher-al')) {
				tab = 'alternatives';
				$('.editor .tab.' + tab + ' .graysmall[data-item=3]').trigger('dblclick');
			}
		});

		$("body").bind('keydown', 'Meta+shift+l', function() {
            UI.openLanguageResourcesPanel();
        }).bind('keydown', 'Meta+shift+s', function(e) {
            UI.body.toggleClass('tagmode-default-extended');
        }).on('click', '.tagModeToggle', function(e) {
            e.preventDefault();
            UI.toggleTagsMode(this);
            if(typeof UI.currentSegment != 'undefined') UI.pointToOpenSegment(true);
		} );

		$("body").on('click', '.autofillTag', function(e){
			e.preventDefault();

			//get source tags from the segment
            var sourceClone = $( '.source', UI.currentSegment ).clone();
            sourceClone.find('.locked.inside-attribute').remove();
			var sourceTags = sourceClone.html()
					.match( /(&lt;\s*\/*\s*(g|x|bx|ex|bpt|ept|ph|it|mrk)\s*.*?&gt;)/gi );

			//get target tags from the segment
            var targetClone =  $( '.targetarea', UI.currentSegment ).clone();
            targetClone.find('.locked.inside-attribute').remove();
			var targetTags = targetClone.html()
					.match( /(&lt;\s*\/*\s*(g|x|bx|ex|bpt|ept|ph|it|mrk)\s*.*?&gt;)/gi );

			if(targetTags == null ) {
				targetTags = [];
			} else {
                targetTags = targetTags.map(function(elem) {
                    return elem.replace(/<\/span>/gi, "").replace(/<span.*?>/gi, "");
                });
            }

			var missingTags = sourceTags.map(function(elem) {
                return elem.replace(/<\/span>/gi, "").replace(/<span.*?>/gi, "");
            });
			//remove from source tags all the tags in target segment
			for(var i = 0; i < targetTags.length; i++ ){
				var pos = missingTags.indexOf(targetTags[i]);
				if( pos > -1){
					missingTags.splice(pos,1);
				}
			}

			var undoCursorPlaceholder = $('.undoCursorPlaceholder', UI.currentSegment ).detach();
			var brEnd = $('br.end', UI.currentSegment ).detach();

            var newhtml = UI.editarea.html();
			//add tags into the target segment
			for(var i = 0; i < missingTags.length; i++){
				newhtml = newhtml + UI.transformTextForLockTags(missingTags[i]);
			}
            SegmentActions.replaceEditAreaTextContent(UI.getSegmentId(UI.editarea), UI.getSegmentFileId(UI.editarea), newhtml);
			//add again undoCursorPlaceholder
			UI.editarea.append(undoCursorPlaceholder )
					   .append(brEnd);

			//lock tags and run again getWarnings
            UI.segmentQA(UI.currentSegment);

		}).on('click', '.tagLockCustomize', function(e) {
			e.preventDefault();
			$(this).toggleClass('unlock');
			if ($(this).hasClass('unlock')) {
				UI.disableTagMark();
			} else {
				UI.enableTagMark();
			}
			UI.setTagLockCustomizeCookie(false);
		}).on('click', '.open-popup-addtm-tr', function(e) {
            e.preventDefault();
            UI.openLanguageResourcesPanel();
        }).on('click', '.modal .x-popup', function() {
			if($('body').hasClass('shortcutsDisabled')) {
				UI.bindShortcuts();
			}
		}).on('click', '#spellCheck .words', function(e) {
			e.preventDefault();
			UI.selectedMisspelledElement.replaceWith($(this).text());
		}).on('click', '#spellCheck .add', function(e) {
			e.preventDefault();
			UI.addWord(UI.selectedMisspelledElement.text());
		}).on('click', '.reloadPage', function() {
			location.reload(true);
		}).on('click', '.tag-autocomplete li', function(e) {
			e.preventDefault();

			UI.chooseTagAutocompleteOption($(this));

		});

		$(window).on('mousedown', function(e) {
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

            $('.editor .search-source .rangySelectionBoundary.focusOut,' +
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

		$("div.notification-box").mouseup(function() {
			return false;
		});

		$(".search-icon, .search-on").click(function(e) {
			e.preventDefault();
			$("#search").toggle();
		});

		//overlay

		$("#outer").on('click', 'a.sid', function(e) {
			e.preventDefault();
			e.stopPropagation();
			return false;
		}).on('click', 'a.status', function(e) {
			e.preventDefault();
			e.stopPropagation();
		}).on('click', 'section:not(.readonly, .ice-locked) a.status', function() {
			var section = $(this).closest("section");
			var statusMenu = $("ul.statusmenu", section);

			UI.createStatusMenu(statusMenu, section );

			statusMenu.show();

			$('html').bind("click.outOfStatusMenu", function() {
				$("ul.statusmenu").hide();
				$('html').unbind('click.outOfStatusMenu');
				UI.removeStatusMenu(statusMenu);
			});
		}).on('mousedown', 'section.readonly, section.readonly a.status', function() {
			sel = window.getSelection();
			UI.someUserSelection = (sel.type == 'Range') ? true : false;
		}).on('dblclick', 'section.readonly', function() {
			clearTimeout(UI.selectingReadonly);
		}).on('dblclick', '.alternatives .graysmall', function() {
			UI.chooseAlternative($(this));
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

		$('html').on('click', 'section .actions', function(e){
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

		}).on('dragstart', '.editor .editarea .locked', function() {
            // To stop the drag in tags elements
            return false;
		}).on('click', 'a.translated, a.next-untranslated', function(e) {
			UI.clickOnTranslatedButton(e, this);
		}).on('click', 'a.guesstags', function(e) {
			// Tag Projection: handle click on "GuesssTags" button, retrieve the translation and place it
			// in the current segment
			e.preventDefault();
			UI.startSegmentTagProjection();
			return false;
		}).on('click', 'a.translatedStatusMenu, a.approvedStatusMenu, a.rejectedStatusMenu, a.draftStatusMenu, a.fx, a.rb', function() {
			var segment = $(this).parents("section");
			$("a.status", segment).removeClass("col-approved col-rejected col-done col-draft");
			$("ul.statusmenu", segment).toggle();
			return false;
		}).on('click', 'a.translatedStatusMenu', function() {
			UI.changeStatus(this, 'translated', 1);
		}).on('click', 'a.approvedStatusMenu', function() {
			UI.changeStatus(this, 'approved', 1);
		}).on('click', 'a.rejectedStatusMenu', function() {
			UI.changeStatus(this, 'rejected', 1);
		}).on('click', 'a.draftStatusMenu', function() {
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
		}).on('keydown', function(e) {
            if((e.which == 27) && ($('.modal[data-name=confirmAutopropagation]').length)) {
                $('.modal[data-name=confirmAutopropagation] .btn-ok').click();
                e.preventDefault();
                e.stopPropagation();
            }
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
		});

		$("#outer").on('click', '.tab.alternatives .graysmall .goto a', function(e) {
			e.preventDefault();
			UI.scrollSegment($('#segment-' + $(this).attr('data-goto')), true);
			SegmentActions.highlightEditarea($(this).attr('data-goto'));
		});



		$(".end-message-box a.close").on('click', function(e) {
			e.preventDefault();
			UI.body.removeClass('justdone');
		});

		$("#point2seg").bind('mousedown', function(e) {
			e.preventDefault();
			if (UI.currentSegment) {
                UI.saveSegment(UI.currentSegment);
            }
			UI.closeAllMenus(e, true);
			QAComponent.togglePanel();
		});

		$("#navSwitcher").on('click', function(e) {
			e.preventDefault();
		});

		$("#pname").on('click', function(e) {
			UI.closeAllMenus(e);
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
				UI.unmountSegments();
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


        if (!this.segmentToScrollAtRender)
            UI.gotoSegment(this.startSegmentId);
        this.checkIfFinishedFirst();

		this.initEnd = new Date();
		this.initTime = this.initEnd - this.initStart;
		if (this.debug) { console.log('Init time: ' + this.initTime); }

    }

});

$(document).ready(function() {
	window.quality_report_btn_component = ReactDOM.render(
		React.createElement( Review_QualityReportButton, {
			vote                : config.overall_quality_class,
			quality_report_href : config.quality_report_href
		}), $('#quality-report-button')[0] );

});

