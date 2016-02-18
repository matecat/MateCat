/*
	Component: ui.init
 */
$.extend(UI, {
	init: function() {
		this.initStart = new Date();
		this.version = "0.5.9";
		if (this.debug)
			console.log('Render time: ' + (this.initStart - renderStart));
		this.numContributionMatchesResults = 3;
		this.numDisplayContributionMatches = 3;
		this.numMatchesResults = 10;
		this.numSegments = $('section').length;
		this.editarea = '';
		this.byButton = false;
		this.notYetOpened = true;
		this.pendingScroll = 0;
		this.firstScroll = true;
		this.blockGetMoreSegments = true;
		this.searchParams = {};
		this.searchParams.search = 0;
//		var bb = $.cookie('noAlertConfirmTranslation');
//		this.alertConfirmTranslationEnabled = (typeof bb == 'undefined') ? true : false;
		this.customSpellcheck = false;
		this.noGlossary = false;
		setTimeout(function() {
			UI.blockGetMoreSegments = false;
		}, 200);
		this.loadCustomization();
        $('html').trigger('init');
        this.setTagMode();
		this.detectFirstLast();
//		this.reinitMMShortcuts();
		this.initSegmentNavBar();
		rangy.init();
		this.savedSel = null;
		this.savedSelActiveElement = null;
		this.firstOpenedSegment = false;
		this.autoscrollCorrectionEnabled = true;
//        this.offlineModeEnabled = false;
        this.offline = false;
        this.searchEnabled = true;
		if (this.searchEnabled)
            $('#filterSwitch').show( 100, function(){ APP.fitText( $('.breadcrumbs'), $('#pname'), 30) } );
        this.fixHeaderHeightChange();
        this.setHideMatches();
		this.setTagLockCustomizeCookie(true);
		this.viewConcordanceInContextMenu = true;
		if (!this.viewConcordanceInContextMenu)
			$('#searchConcordance').hide();
		this.viewSpellCheckInContextMenu = true;
		if (!this.viewSpellCheckInContextMenu)
			$('#spellCheck').hide();
		setTimeout(function() {
			UI.autoscrollCorrectionEnabled = false;
		}, 2000);
		this.checkSegmentsArray = {};
		this.firstMarking = true;
//		this.markTags(true);
		this.firstMarking = false;
		this.surveyDisplayed = false;
		this.warningStopped = false;
		this.abortedOperations = [];
        this.propagationsAvailable = false;
        this.logEnabled = true;
        this.unsavedSegmentsToRecover = [];
        this.recoverUnsavedSegmentsTimer = false;
        this.savingMemoryErrorNotificationEnabled = false;
        this.setTranslationTail = [];
        this.setContributionTail = [];
        this.executingSetTranslation = false;
        this.executingSetContribution = false;
        this.executingSetContributionMT = false;
        this.localStorageArray = [];
        this.isPrivateSafari = (this.isSafari) && (!this.isLocalStorageNameSupported());
        this.consecutiveCopySourceNum = [];
        this.setComingFrom();
        setInterval(function() {
            UI.consecutiveCopySourceNum = [];
        }, config.copySourceInterval*1000);

        if(config.isAnonymousUser) this.body.addClass('isAnonymous');

		/**
		 * Global Warnings array definition.
		 */
		this.globalWarnings = [];

		this.shortcuts = {
			"translate": {
				"label" : "Confirm translation",
				"equivalent": "click on Translated",
				"keystrokes" : {
					"standard": "ctrl+return",
					"mac": "meta+return",
				}
			},
			"translate_nextUntranslated": {
				"label" : "Confirm translation and go to Next untranslated segment",
				"equivalent": "click on [T+>>]",
				"keystrokes" : {
					"standard": "ctrl+shift+return",
					"mac": "meta+shift+return",
				}
			},
			"openNext": {
				"label" : "Go to next segment",
				"equivalent": "",
				"keystrokes" : {
					"standard": "ctrl+down",
					"mac": "meta+down",
				}
			},
			"openPrevious": {
				"label" : "Go to previous segment",
				"equivalent": "",
				"keystrokes" : {
					"standard": "ctrl+up",
					"mac": "meta+up",
				}
			},
			"gotoCurrent": {
				"label" : "Go to current segment",
				"equivalent": "",
				"keystrokes" : {
					"standard": "ctrl+home",
					"mac": "meta+shift+up",
				}
			},
			"copySource": {
				"label" : "Copy source to target",
				"equivalent": "click on > between source and target",
				"keystrokes" : {
					"standard": "alt+ctrl+i",
				}
			},
			"undoInSegment": {
				"label" : "Undo in segment",
				"equivalent": "",
				"keystrokes" : {
					"standard": "ctrl+z",
					"mac": "meta+z",
				}
			},
			"redoInSegment": {
				"label" : "Undo in segment",
				"equivalent": "",
				"keystrokes" : {
					"standard": "ctrl+y",
					"mac": "meta+shift+z",
				}
			},
			"openSearch": {
				"label" : "Open/Close search panel",
				"equivalent": "",
				"keystrokes" : {
					"standard": "ctrl+f",
					"mac": "meta+f",
				}
			},
			"searchInConcordance": {
				"label" : "Perform Concordance search on word(s) selected in the source or target segment",
				"equivalent": "",
				"keystrokes" : {
					"standard": "alt+k",
					"mac": "alt+k",
				}
			},
		};
		this.setShortcuts();
		this.setContextMenu();
		this.createJobMenu();
		$('#alertConfirmTranslation p').text('To confirm your translation, please press on Translated or use the shortcut ' + ((UI.isMac) ? 'CMD' : 'CTRL') + '+Enter.');
		APP.initMessageBar();
		this.checkVersion();
        this.initTM();
        this.storeClientInfo();

        // SET EVENTS
		this.setEvents();
		if(this.surveyAlreadyDisplayed()) {
			this.surveyDisplayed = true;
		}
	},
});

/**
 * Register tabs in segment footer
 */

UI.SegmentFooter.registerTab({
    code                : 'cc',
    tab_class           : 'concordances',
    label               : 'Concordance',
    activation_priority : 10,
    tab_position        : 20,
    is_enabled    : function(segment) {
        return true;
    },
    tab_markup          : function(segment) {
        return this.label ;
    },
    content_markup      : function(segment) {
        var template = MateCat.Templates['segment_footer/tabs/concordances_body'];
        return template({ tms_enabled : config.tms_enabled });
    },
    is_hidden    : function(segment) {
        return false;
    },
    on_activation : function( footer ) {
        $('.cc-search .search-source', footer.segment.el).focus();
    }
});

UI.SegmentFooter.registerTab({
    code                : 'nw',
    tab_class           : 'new',
    label               : 'New',
    activation_priority : 100,
    tab_position        : 100,
    is_enabled    : function(segment) {
        return true;
    },
    tab_markup          : function(segment) {
        return this.label ;
    },
    content_markup      : function(segment) {
        var template = MateCat.Templates['segment_footer/tabs/new_body'];
        return template();
    },
    is_hidden    : function(segment) {
        return false;
    },
    on_activation : function( footer ) {
        $('.cc-search .search-source', footer.segment.el).focus();
    }
});

UI.SegmentFooter.registerTab({
    code                : 'tm',
    tab_class           : 'matches',
    label               : 'Translation Matches',
    activation_priority : 50,
    tab_position        : 10,
    is_enabled          : function(segment) {
        return true;
    },
    tab_markup          : function(segment) {
        if ( config.mt_enabled ) {
            return this.label ;
        }
        else {
            return this.label + " (No MT) ";
        }
    },
    content_markup      : function(segment) {
        return '<div class="overflow"></div>' +
            '<div class="engine-errors"></div>' ;
    },
    is_hidden    : function(segment) {
        return false;
    }
});

UI.SegmentFooter.registerTab({
    code                : 'gl',
    tab_class           : 'glossary',
    label               : 'Glossary',
    activation_priority : 30,
    tab_position        : 30,
    is_enabled          : function(segment) {
        return true;
    },
    tab_markup          : function(segment) {
        return this.label ;
    },
    content_markup      : function(segment) {
        var template = MateCat.Templates['segment_footer/tabs/glossary_body'];
        return template({ tms_enabled : config.tms_enabled });
    },
    is_hidden    : function(segment) {
        return false;
    },
    on_activation : function( footer ) {
        $('.gl-search .search-source', footer.segment.el).focus();
    }
});

UI.SegmentFooter.registerTab({
    code                : 'al',
    tab_class           : 'alternatives',
    label               : 'Translation conflicts',
    activation_priority : 30,
    tab_position        : 40,
    is_enabled          : function(segment) {
        return true;
    },
    tab_markup          : function(segment) {
        return this.label ;
    },
    content_markup      : function(segment) {
        return '<div class="overflow"></div>' ;
    },
    is_hidden    : function(segment) {
        return true;
    }
});
