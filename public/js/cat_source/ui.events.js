/*
	Component: ui.events
 */
$.extend(UI, {
	bindShortcuts: function() {
		$("body").removeClass('shortcutsDisabled');

		$("body").on('keydown.shortcuts', null, "alt+h", function(e) {
            UI.openShortcutsModal();
		});


        this.shortCutskey = "standard";
		if (UI.isMac) {
            this.shortCutskey = "mac";
        }
        $("body").on('keydown.shortcuts', null, UI.shortcuts.cattol.events.copySource.keystrokes[this.shortCutskey], function(e) {
            e.preventDefault();
            UI.copySource();
        }).on('keydown.shortcuts',null, UI.shortcuts.cattol.events.openSettings.keystrokes[this.shortCutskey], function(e) {
            UI.openLanguageResourcesPanel();
        }).on('keydown.shortcuts', null, UI.shortcuts.cattol.events.openSearch.keystrokes[this.shortCutskey], function(e) {
            if((SearchUtils.searchEnabled)&&($('#filterSwitch').length)) SearchUtils.toggleSearch(e);
        }).on('keydown.shortcuts', null, UI.shortcuts.cattol.events.redoInSegment.keystrokes[this.shortCutskey], function(e) {
            e.preventDefault();
            UI.redoInSegment(UI.currentSegment);
        }).on('keydown.shortcuts', null, UI.shortcuts.cattol.events.undoInSegment.keystrokes[this.shortCutskey], function(e) {
            e.preventDefault();
            UI.undoInSegment(UI.currentSegment);
            UI.closeTagAutocompletePanel();
        }).on('keydown.shortcuts', null, UI.shortcuts.cattol.events.gotoCurrent.keystrokes[this.shortCutskey], function(e) {
            e.preventDefault();
            UI.pointToOpenSegment();
        }).on('keydown.shortcuts', null, UI.shortcuts.cattol.events.openPrevious.keystrokes[this.shortCutskey], function(e) {
            e.preventDefault();
            e.stopPropagation();
            UI.gotoPreviousSegment();
        }).on('keydown.shortcuts', null, UI.shortcuts.cattol.events.openNext.keystrokes[this.shortCutskey], function(e) {
            e.preventDefault();
            e.stopPropagation();
            UI.gotoNextSegment();
        }).on('keydown.shortcuts', null, UI.shortcuts.cattol.events.translate_nextUntranslated.keystrokes[this.shortCutskey], function(e) {
            e.preventDefault();
            e.stopPropagation();
            if ( config.isReview ) {
                UI.clickOnApprovedButton($('.editor .next-unapproved:not(.disabled)'));
            } else {
                if ( $('.editor .next-untranslated:not(.disabled)').length > 0 ) {
                    UI.clickOnTranslatedButton($('.editor .next-untranslated:not(.disabled)'));
                } else if ( $('.editor .translated:not(.disabled)').length > 0 ) {
                    UI.clickOnTranslatedButton($('.editor .translated'));
                } else if ( $('.editor .guesstags').length > 0 ) {
                    UI.startSegmentTagProjection();
                }
            }
        }).on('keydown.shortcuts', null, UI.shortcuts.cattol.events.translate.keystrokes[this.shortCutskey], function(e) {
            e.preventDefault();
            e.stopPropagation();
            if ( config.isReview ) {
                UI.clickOnApprovedButton($('body.review .editor .approved:not(.disabled)'));
            } else {
                if ( $('.editor .translated:not(.disabled)').length > 0 ) {
                    UI.clickOnTranslatedButton($('.editor .translated'));
                } else if ( $('.editor .guesstags').length > 0 ) {
                    UI.startSegmentTagProjection();
                }
            }
        }).on('keydown.shortcuts', null, UI.shortcuts.cattol.events.toggleTagDisplayMode.keystrokes[this.shortCutskey], function(e) {
            e.preventDefault();
            UI.toggleTagsMode();
        }).on('keydown.shortcuts', null, UI.shortcuts.cattol.events.copyContribution1.keystrokes[this.shortCutskey], function(e) {
            e.preventDefault();
            SegmentActions.chooseContribution(UI.getSegmentId(UI.currentSegment), 1);
        }).on('keydown.shortcuts', null, UI.shortcuts.cattol.events.copyContribution2.keystrokes[this.shortCutskey], function(e) {
            e.preventDefault();
            SegmentActions.chooseContribution(UI.getSegmentId(UI.currentSegment), 2);
        }).on('keydown.shortcuts', null, UI.shortcuts.cattol.events.copyContribution3.keystrokes[this.shortCutskey], function(e) {
            e.preventDefault();
            SegmentActions.chooseContribution(UI.getSegmentId(UI.currentSegment), 3);
        }).on('keydown.shortcuts', null, UI.shortcuts.cattol.events.addNextTag.keystrokes[this.shortCutskey], function(e) {
            e.preventDefault();
            e.stopPropagation();
            if ((UI.tagLockEnabled) && UI.hasDataOriginalTags(UI.currentSegment)) {
                SegmentActions.showTagsMenu(UI.getSegmentId(UI.currentSegment));
            }
        }).on('keydown.shortcuts', null, UI.shortcuts.cattol.events.splitSegment.keystrokes[this.shortCutskey], function(e) {
            e.preventDefault();
            e.stopPropagation();
            SegmentActions.openSplitSegment(UI.currentSegmentId);
        }).on('keydown.shortcuts', null, "ctrl+u", function(e) {
            // to prevent the underline shortcut
            e.preventDefault();
        }).on('keydown.shortcuts', null, "ctrl+b", function(e) {
            // to prevent the underline shortcut
            e.preventDefault();
        });
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


		$("body").on('click', '.tagModeToggle', function(e) {
            e.preventDefault();
            UI.toggleTagsMode();
            if(typeof UI.currentSegment != 'undefined') UI.pointToOpenSegment(true);
		} );

		$("body").on('click', '.autofillTag', function(e){
			e.preventDefault();
			UI.autoFillTagsInTarget();

		}).on('click', '.tagLockCustomize', function(e) {
			e.preventDefault();
			if (UI.tagLockEnabled) {
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
		});

		$(window).on('mousedown', function(e) {
			if ($(e.target).hasClass("editarea")) {
				return true;
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

            if(!$('.editor .targetarea .rangySelectionBoundary.focusOut').length) {
                saveSelection();
            }

            $('.editor .targetarea .rangySelectionBoundary').addClass('focusOut');

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
            SearchUtils.toggleSearch(e);
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
		}).on('mousedown', 'section.readonly, section.readonly a.status', function() {
			sel = window.getSelection();
			UI.someUserSelection = (sel.type == 'Range') ? true : false;
		}).on('dblclick', 'section.readonly', function() {
			clearTimeout(UI.selectingReadonly);
		}).on('dblclick', '.alternatives .graysmall', function(e) {
            if ($(e.target).closest('li').hasClass('goto')) {
                return;
            }
			UI.chooseAlternative($(this));
        });

		$("form#fileDownload").bind('submit', function(e) {
			e.preventDefault();
		});


		$('html')
            .on('keydown', function(e) {
            var esc = 27 ;

            // ESC should close the current segment only if `article` is not
            // resized to let space to the tools on the sidebar.

            var handleEscPressed = function() {
                var segment = SegmentStore.getCurrentSegment();
                if ( segment &&
                    !UI.body.hasClass('side-tools-opened') &&
					!UI.body.hasClass("side-popup" ) &&
                    !UI.body.hasClass('search-open') &&
                    !UI.tagMenuOpen ) {
                        SegmentActions.closeSegment(UI.currentSegmentId);
                    }
            };

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
		}).on('click', '#statistics .meter a, #statistics #stat-todo', function(e) {
			e.preventDefault();
			if ( config.isReview ) {
                UI.openNextTranslated();
            } else {
                UI.gotoNextUntranslatedSegment();
            }
		})

		$("#outer").on('click', 'a.percentuage', function(e) {
			e.preventDefault();
			e.stopPropagation();
		}).on('mousedown', '.editToolbar .uppercase', function() {
			UI.formatSelection('uppercase');
		}).on('mousedown', '.editToolbar .lowercase', function() {
			UI.formatSelection('lowercase');
		}).on('mousedown', '.editToolbar .capitalize', function() {
			UI.formatSelection('capitalize');
		}).on('click', '.editor .source .locked,.editor .editarea .locked, ' +
            '.editor .source .locked a,.editor .editarea .locked a', function(e) {
            e.preventDefault();
            e.stopPropagation();
            UI.markSelectedTag( $( this ) );
        }).on('click', '.editor .outersource .copy', function(e) {
			e.preventDefault();
			UI.copySource();
		}).on('click', '.tagmenu, .warning, .viewer, .notification-box li a', function() {
			return false;
        }).on('keydown', function(e) {
            if((e.which == 27) && ($('.modal[data-name=confirmAutopropagation]').length)) {
                $('.modal[data-name=confirmAutopropagation] .btn-ok').click();
                e.preventDefault();
                e.stopPropagation();
            }
		});

		$("#point2seg").bind('mousedown', function(e) {
			e.preventDefault();
			if (UI.currentSegment  && (!config.isReview) && UI.getStatus(UI.currentSegment) !== 'approved') {
                UI.saveSegment(UI.currentSegment);
            }
			CatToolActions.toggleQaIssues();
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
			if (UI.currentSegment) {
                UI.saveSegment(UI.currentSegment);
            }
			UI.renderAndScrollToSegment($(this).attr('data-segment'));
		});
		$("#jobMenu").on('click', 'li.currSegment:not(.disabled)', function(e) {
			e.preventDefault();
			UI.pointToOpenSegment();
		});

		$("#jobNav .currseg").on('click', function(e) {
			e.preventDefault();
            var current = SegmentStore.getCurrentSegment();
			if ( !current ) {
				UI.unmountSegments();
                UI.render({
                    firstLoad: false
                });
			} else {
				UI.scrollSegment(current.original_sid);
			}
		});
		this.initEnd = new Date();
		this.initTime = this.initEnd - this.initStart;
		if (this.debug) { console.log('Init time: ' + this.initTime); }

    },

    openShortcutsModal: function (  ) {
        var props = {
            shortcuts: UI.shortcuts
        };
        APP.ModalWindow.showModalComponent(ShortCutsModal, props, 'Shortcuts');
    },

});

$(document).ready(function() {
    var revision_number = (config.revisionNumber) ? config.revisionNumber : '1';
    var qrParam = (config.secondRevisionsCount) ? '?revision_type=' + revision_number : '' ;
	window.quality_report_btn_component = ReactDOM.render(
		React.createElement( Review_QualityReportButton, {
			vote                : config.overall_quality_class,
			quality_report_href : config.quality_report_href + qrParam
		}), $('#quality-report-button')[0] );
	if ( config.secondRevisionsCount ) {
        UI.reloadQualityReport();
    }
});

