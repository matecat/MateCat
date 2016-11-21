/*
	Component: ui.init
 */
$.extend(UI, {
	init: function() {

		this.registerFooterTabs();

		this.isMac = (navigator.platform == 'MacIntel')? true : false;
		this.shortcutLeader = (this.isMac) ? 'CMD' : 'CTRL' ;

		this.initStart = new Date();
		this.version = "x.x.x";
		this.numContributionMatchesResults = 3;
		this.numDisplayContributionMatches = 3;
		this.numMatchesResults = 10;
		this.editarea = '';
		this.byButton = false;
		this.blockGetMoreSegments = true;
		this.searchParams = {};
		this.searchParams.search = 0;
		this.customSpellcheck = false;
		this.noGlossary = false;
		this.displayedMessages = [];
		setTimeout(function() {
			UI.blockGetMoreSegments = false;
		}, 200);
		this.loadCustomization();
        $('html').trigger('init');
        this.setTagMode();
		this.detectFirstLast();
		rangy.init();
		this.savedSel = null;
		this.savedSelActiveElement = null;
		this.firstOpenedSegment = false;
		this.autoscrollCorrectionEnabled = true;
        this.offline = false;
        this.searchEnabled = true;
		if (this.searchEnabled)
            $('#filterSwitch').show( 100, function(){ APP.fitText( $('.breadcrumbs'), $('#pname'), 30) } );
        this.fixHeaderHeightChange();
        this.setHideMatches();

		setTimeout(function() {
			UI.autoscrollCorrectionEnabled = false;
		}, 2000);
		this.checkSegmentsArray = {};
		this.surveyDisplayed = false;
		this.warningStopped = false;
		this.abortedOperations = [];
        this.logEnabled = true;
        this.unsavedSegmentsToRecover = [];
        this.recoverUnsavedSegmentsTimer = false;
        this.setTranslationTail = [];
        this.executingSetTranslation = false;
        this.localStorageArray = [];
        this.isPrivateSafari = (this.isSafari) && (!this.isLocalStorageNameSupported());
        this.consecutiveCopySourceNum = [];
        this.setComingFrom();
        setInterval(function() {
            UI.consecutiveCopySourceNum = [];
        }, config.copySourceInterval*1000);

        if (!config.isLoggedIn) this.body.addClass('isAnonymous');

		/**
		 * Global Warnings array definition.
		 */
		this.globalWarnings = [];

		UI.shortcuts = UI.shortcuts || {} ;

		$.extend(UI.shortcuts,  {
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
					"standard": "ctrl+i",
					"mac": "alt+ctrl+i"
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
			}
		});

		this.setShortcuts();
		this.createJobMenu();
		APP.initMessageBar();
		this.checkVersion();
        this.initTM();
		this.initAdvanceOptions();
        this.storeClientInfo();

        // SET EVENTS
		this.setEvents();
		if(this.surveyAlreadyDisplayed()) {
			this.surveyDisplayed = true;
		}
	},
	/**
	 * Register tabs in segment footer
	 */
	registerFooterTabs: function () {
        SegmentActions.registerTab('concordances', true, false);
        SegmentActions.registerTab('matches', true, true);
        SegmentActions.registerTab('glossary', true, false);
        SegmentActions.registerTab('alternatives', false, false);
	}
});