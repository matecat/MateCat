/*
	Component: ui.events
 */
$.extend(UI, {
	bindShortcuts: function() {
        $("body").on('keydown.shortcuts', null, Shortcuts.cattol.events.openShortcutsModal.keystrokes[Shortcuts.shortCutsKeyType], function(e) {
            APP.ModalWindow.showModalComponent(ShortCutsModal, null, 'Shortcuts');
        }).on('keydown.shortcuts', null, Shortcuts.cattol.events.copySource.keystrokes[Shortcuts.shortCutsKeyType], function(e) {
            e.preventDefault();
            SegmentActions.copySourceToTarget();
        }).on('keydown.shortcuts',null, Shortcuts.cattol.events.openSettings.keystrokes[Shortcuts.shortCutsKeyType], function(e) {
            UI.openLanguageResourcesPanel();
        }).on('keydown.shortcuts', null, Shortcuts.cattol.events.openSearch.keystrokes[Shortcuts.shortCutsKeyType], function(e) {
            if((SearchUtils.searchEnabled)&&($('#filterSwitch').length)) SearchUtils.toggleSearch(e);
        }).on('keydown.shortcuts', null, Shortcuts.cattol.events.redoInSegment.keystrokes[Shortcuts.shortCutsKeyType], function(e) {
            e.preventDefault();
            // UI.redoInSegment(UI.currentSegment);
            SegmentActions.redoInSegment();
        }).on('keydown.shortcuts', null, Shortcuts.cattol.events.undoInSegment.keystrokes[Shortcuts.shortCutsKeyType], function(e) {
            e.preventDefault();
            SegmentActions.undoInSegment();
            SegmentActions.closeTagsMenu();
        }).on('keydown.shortcuts', null, Shortcuts.cattol.events.gotoCurrent.keystrokes[Shortcuts.shortCutsKeyType], function(e) {
            e.preventDefault();
            SegmentActions.scrollToCurrentSegment();
            SegmentActions.setFocusOnEditArea();
        }).on('keydown.shortcuts', null, Shortcuts.cattol.events.openPrevious.keystrokes[Shortcuts.shortCutsKeyType], function(e) {
            e.preventDefault();
            e.stopPropagation();
            SegmentActions.selectPrevSegment();
            // UI.gotoPreviousSegment();
        }).on('keydown.shortcuts', null, Shortcuts.cattol.events.openNext.keystrokes[Shortcuts.shortCutsKeyType], function(e) {
            e.preventDefault();
            e.stopPropagation();
            SegmentActions.selectNextSegment();
            // UI.gotoNextSegment();
        }).on('keyup.shortcuts', null, 'ctrl', function(e) {
            e.preventDefault();
            e.stopPropagation();
            SegmentActions.openSelectedSegment();
        }).on('keydown.shortcuts', null, 'meta', function(e) {
            e.preventDefault();
            e.stopPropagation();
            SegmentActions.openSelectedSegment();
        }).on('keydown.shortcuts', null, Shortcuts.cattol.events.translate_nextUntranslated.keystrokes[Shortcuts.shortCutsKeyType], function(e) {
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
                    UI.startSegmentTagProjection(UI.currentSegmentId);
                }
            }
        }).on('keydown.shortcuts', null, Shortcuts.cattol.events.translate.keystrokes[Shortcuts.shortCutsKeyType], function(e) {
            e.preventDefault();
            e.stopPropagation();
            if ( config.isReview ) {
                UI.clickOnApprovedButton($('body.review .editor .approved:not(.disabled)'));
            } else {
                if ( $('.editor .translated:not(.disabled)').length > 0 ) {
                    UI.clickOnTranslatedButton($('.editor .translated'));
                } else if ( $('.editor .guesstags').length > 0 ) {
                    UI.startSegmentTagProjection(UI.currentSegmentId);
                }
            }
        }).on('keydown.shortcuts', null, Shortcuts.cattol.events.toggleTagDisplayMode.keystrokes[Shortcuts.shortCutsKeyType], function(e) {
            e.preventDefault();
            Customizations.toggleTagsMode();
        }).on('keydown.shortcuts', null, Shortcuts.cattol.events.openComments.keystrokes[Shortcuts.shortCutsKeyType], function(e) {
            e.preventDefault();
            var segment = SegmentStore.getCurrentSegment();
            if (segment) {
                SegmentActions.openSegmentComment(segment.sid);
                SegmentActions.scrollToSegment(segment.sid);
                CommentsActions.setFocusOnCurrentInput();
            }
        }).on('keydown.shortcuts', null, Shortcuts.cattol.events.openIssuesPanel.keystrokes[Shortcuts.shortCutsKeyType], function(e) {
            e.preventDefault();
            var segment = SegmentStore.getCurrentSegment();
            if (segment && Review.enabled()) {
                SegmentActions.openIssuesPanel({sid: segment.sid});
                SegmentActions.scrollToSegment(segment.sid);
            }
        }).on('keydown.shortcuts', null, Shortcuts.cattol.events.copyContribution1.keystrokes[Shortcuts.shortCutsKeyType], function(e) {
            e.preventDefault();
            SegmentActions.chooseContribution(UI.getSegmentId(UI.currentSegment), 1);
        }).on('keydown.shortcuts', null, Shortcuts.cattol.events.copyContribution2.keystrokes[Shortcuts.shortCutsKeyType], function(e) {
            e.preventDefault();
            SegmentActions.chooseContribution(UI.getSegmentId(UI.currentSegment), 2);
        }).on('keydown.shortcuts', null, Shortcuts.cattol.events.copyContribution3.keystrokes[Shortcuts.shortCutsKeyType], function(e) {
            e.preventDefault();
            SegmentActions.chooseContribution(UI.getSegmentId(UI.currentSegment), 3);
        }).on('keydown.shortcuts', null, Shortcuts.cattol.events.addNextTag.keystrokes[Shortcuts.shortCutsKeyType], function(e) {
            e.preventDefault();
            e.stopPropagation();
            var currentSegment = SegmentStore.getCurrentSegment();
            if ((UI.tagLockEnabled) && TagUtils.hasDataOriginalTags(currentSegment.segment)) {
                SegmentActions.showTagsMenu(currentSegment.sid);
            }
        }).on('keydown.shortcuts', null, Shortcuts.cattol.events.splitSegment.keystrokes[Shortcuts.shortCutsKeyType], function(e) {
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


		// $(window).on('mousedown', function(e) {
		// 	if ($(e.target).hasClass("editarea")) {
		// 		return true;
		// 	}
        //
        //     $('.editor .targetarea .rangySelectionBoundary').addClass('focusOut');
        //
        //     $('.editor .search-source .rangySelectionBoundary.focusOut,' +
        //         '.editor .search-target .rangySelectionBoundary.focusOut'
        //     ).remove();
        //
        //     if ( UI.editarea && UI.editarea != '') {
        //         var hasFocusBefore = UI.editarea.is(":focus");
        //         setTimeout(function() {
        //             var hasFocusAfter = UI.editarea && UI.editarea.is(":focus");
        //             if(hasFocusBefore && hasFocusAfter){
        //                 $('.editor .rangySelectionBoundary.focusOut').remove();
		// 				UI.editarea.get(0).normalize();
        //             }
        //         }, 600);
        //     }
        // });

		window.onbeforeunload = function(e) {
			return CommonUtils.goodbye(e);
		};
        //Header/Footer events
		$("#filterSwitch").bind('click', function(e) {
            SearchUtils.toggleSearch(e);
		});
		$("#advancedOptions").bind('click', function(e) {
			e.preventDefault();
			UI.openOptionsPanel();
		});

		$("div.notification-box").mouseup(function() {
			return false;
		});

		$(".search-icon, .search-on").click(function(e) {
			e.preventDefault();
			$("#search").toggle();
		});

		$("form#fileDownload").bind('submit', function(e) {
			e.preventDefault();
		});


		$('html').on('click', '#previewDropdown .downloadTranslation a', function(e) {
            e.preventDefault();
            UI.runDownload();
		}).on('click', '#previewDropdown .previewLink a', function(e) {
			e.preventDefault();
			UI.runDownload();
		}).on('click', '#previewDropdown a.tmx', function(e) {
			e.preventDefault();
			window.open($(this).attr('href'));
		}).on('click', '#downloadProject', function(e) {
            e.preventDefault();
            UI.runDownload();
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
		}).on('click', '#statistics .meter a, #statistics #stat-todo', function(e) {
			e.preventDefault();
			if ( config.isReview ) {
                UI.openNextTranslated();
            } else {
                SegmentActions.gotoNextUntranslatedSegment();
            }
		});
        $("#point2seg").bind('mousedown', function(e) {
            e.preventDefault();
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
            UI.renderAndScrollToSegment($(this).attr('data-segment'));
        }).on('click', 'li.currSegment:not(.disabled)', function(e) {
            e.preventDefault();
            SegmentActions.scrollToCurrentSegment();
            SegmentActions.setFocusOnEditArea();
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
                SegmentActions.scrollToSegment( current.original_sid );
            }
        });

        //###################################################

		$("#outer").on('click', '.editor .source .locked,.editor .editarea .locked, ' +
            '.editor .source .locked a,.editor .editarea .locked a', function(e) {
            e.preventDefault();
            e.stopPropagation();
            TagUtils.markSelectedTag( $( this ) );
        }).on('keydown', function(e) {
            if((e.which === 27) && ($('.modal[data-name=confirmAutopropagation]').length)) {
                $('.modal[data-name=confirmAutopropagation] .btn-ok').click();
                e.preventDefault();
                e.stopPropagation();
            }
		});

    }

});

